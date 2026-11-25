<?php

namespace Tests\Feature;

use App\Http\Requests\Configuracion\StoreSucursalRequest;
use App\Models\Rol;
use App\Models\User;
use App\Models\Venta;
use App\Services\Sucursales\ContextoSucursalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Sucursal de trabajo por sesión: acceso validado en el servidor y aplicado a los scopes. */
class ContextoSucursalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['inventario', 'seguridad', 'configuracion'] as $esquema) {
            DB::statement("ATTACH DATABASE ':memory:' AS {$esquema}");
        }
        DB::statement('CREATE TABLE configuracion.sucursales (id_sucursal INTEGER PRIMARY KEY, nombre TEXT, codigo_establecimiento TEXT NULL, estado INTEGER)');
        DB::statement('CREATE TABLE seguridad.usuario_sucursales (id_usuario INTEGER, id_sucursal INTEGER)');

        DB::table('configuracion.sucursales')->insert([
            ['id_sucursal' => 1, 'nombre' => 'PRINCIPAL', 'estado' => 1],
            ['id_sucursal' => 2, 'nombre' => 'ANEXO NORTE', 'estado' => 1],
            ['id_sucursal' => 3, 'nombre' => 'ANEXO CERRADO', 'estado' => 0],
        ]);
        // El cajero 20 tiene además acceso asignado al anexo norte.
        DB::table('seguridad.usuario_sucursales')->insert(['id_usuario' => 20, 'id_sucursal' => 2]);

        $this->app['request']->setLaravelSession($this->app['session']->driver());
    }

    public function test_sin_eleccion_se_trabaja_en_la_sede_del_usuario(): void
    {
        $this->assertSame(1, $this->usuario('Cajero', 10)->idSucursalActiva());
    }

    public function test_administrador_puede_trabajar_en_cualquier_sucursal_activa(): void
    {
        $admin = $this->usuario('Administrador General', 1);

        $this->assertSame(['ANEXO NORTE', 'PRINCIPAL'], $admin->sucursalesDisponibles()->pluck('nombre')->all());

        app(ContextoSucursalService::class)->cambiar($admin, 2);

        $this->assertSame(2, $admin->idSucursalActiva());
        $this->assertSame(2, $this->usuario('Administrador General', 1)->idSucursalActiva(), 'La elección persiste en la sesión.');
    }

    public function test_usuario_sin_acceso_no_puede_cambiar_ni_forzar_la_sesion(): void
    {
        $cajero = $this->usuario('Cajero', 10);

        try {
            app(ContextoSucursalService::class)->cambiar($cajero, 2);
            $this->fail('Un cajero sin asignación no debe cambiar de sucursal.');
        } catch (ValidationException $exception) {
            $this->assertSame('No tienes acceso a la sucursal seleccionada.', $exception->errors()['id_sucursal'][0]);
        }

        // Aunque la sesión se manipule, el servidor vuelve a la sede y descarta la elección.
        session([ContextoSucursalService::CLAVE_SESION => 2]);
        $this->assertSame(1, $this->usuario('Cajero', 10)->idSucursalActiva());
        $this->assertNull(session(ContextoSucursalService::CLAVE_SESION));
    }

    public function test_usuario_con_sucursal_asignada_puede_cambiar_a_ella(): void
    {
        $cajero = $this->usuario('Cajero', 20);

        $this->assertSame(['ANEXO NORTE', 'PRINCIPAL'], $cajero->sucursalesDisponibles()->pluck('nombre')->all());

        app(ContextoSucursalService::class)->cambiar($cajero, 2);
        $this->assertSame(2, $this->usuario('Cajero', 20)->idSucursalActiva());
    }

    public function test_sucursal_desactivada_deja_de_ser_la_sucursal_de_trabajo(): void
    {
        session([ContextoSucursalService::CLAVE_SESION => 3]);

        $this->assertSame(1, $this->usuario('Administrador General', 1)->idSucursalActiva());
    }

    public function test_scope_de_sucursal_filtra_por_la_sucursal_de_trabajo(): void
    {
        $admin = $this->usuario('Administrador General', 1);
        $this->actingAs($admin);
        app(ContextoSucursalService::class)->cambiar($admin, 2);

        // El scope solo actúa en peticiones web (no en consola): se simula una petición HTTP.
        $consola = new \ReflectionProperty($this->app, 'isRunningInConsole');
        $consola->setValue($this->app, false);

        try {
            $this->assertSame([2], Venta::query()->getBindings());
        } finally {
            $consola->setValue($this->app, true);
        }
    }

    public function test_solo_un_administrador_gestiona_sucursales(): void
    {
        foreach (['Administrador General' => true, 'Administrador' => true, 'Cajero' => false] as $rol => $esperado) {
            $request = StoreSucursalRequest::create('/sucursales', 'POST');
            $request->setUserResolver(fn (): User => $this->usuario($rol, 1));
            $this->assertSame($esperado, $request->authorize(), $rol);
        }
    }

    private function usuario(string $rol, int $id): User
    {
        $user = (new User)->forceFill(['id_usuario' => $id, 'id_sucursal' => 1, 'id_rol' => 1]);
        $user->setRelation('rol', new Rol(['nombre' => $rol, 'estado' => true]));

        return $user;
    }
}
