<?php

namespace Tests\Feature;

use App\Http\Controllers\Configuracion\DatosGeneralesController;
use App\Http\Requests\Configuracion\ActualizarDatosGeneralesRequest;
use App\Models\Empresa;
use App\Models\Rol;
use App\Models\User;
use App\Services\Comprobantes\ReservarCorrelativoService;
use App\Services\Configuracion\DatosGeneralesService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Datos Generales: una empresa para todas sus sucursales, series propias, autorización y caché. */
class DatosGeneralesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement("ATTACH DATABASE ':memory:' AS configuracion");
        DB::statement('CREATE TABLE configuracion.sucursales (id_sucursal INTEGER PRIMARY KEY, nombre TEXT, direccion TEXT NULL, telefono TEXT NULL, codigo_establecimiento TEXT NULL)');
        DB::statement('CREATE TABLE configuracion.empresa (id_empresa INTEGER PRIMARY KEY, ruc_empresa TEXT NULL, razon_social TEXT NULL, nombre_comercial TEXT NULL, direccion_fiscal TEXT NULL, pie_ticket TEXT NULL, logo_path TEXT NULL, igv_porcentaje NUMERIC DEFAULT 18, uit_valor NUMERIC NULL, color_primario TEXT NULL, color_secundario TEXT NULL, texto_login_titulo TEXT NULL, texto_login_descripcion TEXT NULL, created_at DATETIME, updated_at DATETIME)');
        DB::statement('CREATE TABLE configuracion.series_sucursal (id_sucursal INTEGER PRIMARY KEY, serie_boleta TEXT UNIQUE, serie_factura TEXT UNIQUE, serie_guia TEXT UNIQUE, ult_nro_boleta INTEGER DEFAULT 0, ult_nro_factura INTEGER DEFAULT 0, created_at DATETIME, updated_at DATETIME)');

        DB::table('configuracion.sucursales')->insert([
            ['id_sucursal' => 1, 'nombre' => 'PRINCIPAL', 'codigo_establecimiento' => '0000'],
            ['id_sucursal' => 3, 'nombre' => 'TAMBO', 'codigo_establecimiento' => '0002'],
        ]);
        DB::table('configuracion.empresa')->insert([
            'id_empresa' => 1, 'ruc_empresa' => '20111111111', 'razon_social' => 'PRINCIPAL SAC', 'color_primario' => '#222222', 'pie_ticket' => 'GRACIAS',
        ]);
        DB::table('configuracion.series_sucursal')->insert(['id_sucursal' => 1, 'serie_boleta' => 'B001', 'ult_nro_boleta' => 19]);
    }

    public function test_solo_un_administrador_puede_actualizar_datos_generales(): void
    {
        $this->assertTrue($this->solicitud($this->usuarioConRol('Administrador General'))->authorize());
        $this->assertTrue($this->solicitud($this->usuarioConRol('Administrador'))->authorize());
        $this->assertFalse($this->solicitud($this->usuarioConRol('Cajero'))->authorize());
        $this->assertFalse($this->solicitud($this->usuarioConRol('Almacenero'))->authorize());
    }

    public function test_todas_las_sucursales_usan_la_misma_empresa(): void
    {
        $servicio = app(DatosGeneralesService::class);

        $this->assertSame('PRINCIPAL SAC', $servicio->paraEdicion()->razon_social);
        $this->assertSame('#222222', $servicio->identidad()['color_primario']);
        $this->assertSame('PRINCIPAL SAC', $servicio->emisor(1)->razon_social);
        $this->assertSame('PRINCIPAL SAC', $servicio->emisor(3)->razon_social);
        // Consultar no crea registros.
        $this->assertSame(1, Empresa::query()->count());
    }

    public function test_la_pestana_del_navegador_usa_el_logo_y_el_nombre_de_la_empresa(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('<link rel="icon" href="/favicon.ico" data-marca>', false)
            ->assertSee('<meta name="theme-color" content="#222222">', false);

        DB::table('configuracion.empresa')->update(['logo_path' => 'logos/pand-wok.png', 'nombre_comercial' => 'Pand Wok']);
        app(DatosGeneralesService::class)->olvidarIdentidad();

        $this->get('/login')->assertOk()
            ->assertSee('<link rel="icon" href="/storage/logos/pand-wok.png" data-marca>', false)
            ->assertSee('<title inertia>Pand Wok</title>', false);
    }

    public function test_emisor_combina_datos_de_empresa_con_el_establecimiento_de_la_sucursal(): void
    {
        $emisor = app(DatosGeneralesService::class)->emisor(3);

        $this->assertSame('PRINCIPAL SAC', $emisor->razon_social);
        $this->assertSame('20111111111', $emisor->ruc_empresa);
        $this->assertSame('GRACIAS', $emisor->pie_ticket);
        $this->assertSame('0002', $emisor->codigo_establecimiento);
        $this->assertSame('TAMBO', $emisor->establecimiento_nombre);
    }

    public function test_sin_ninguna_configuracion_no_se_inventan_datos(): void
    {
        DB::table('configuracion.empresa')->delete();
        $servicio = app(DatosGeneralesService::class);

        $datos = $servicio->paraEdicion();
        $this->assertFalse($datos->exists);
        $this->assertNull($datos->ruc_empresa);
        $this->assertNull($datos->razon_social);
        $this->assertNull($servicio->emisor(3));
        $this->assertNull($servicio->identidad());
    }

    public function test_guardar_invalida_la_identidad(): void
    {
        $servicio = app(DatosGeneralesService::class);

        $this->assertSame('PRINCIPAL SAC', $servicio->identidad()['razon_social']);

        DB::table('configuracion.empresa')->update(['razon_social' => 'PRINCIPAL EIRL']);
        // Mientras no se guarde por la aplicación, se sirve la caché vigente.
        $this->assertSame('PRINCIPAL SAC', $servicio->identidad()['razon_social']);

        $servicio->olvidarIdentidad();

        $this->assertSame('PRINCIPAL EIRL', $servicio->identidad()['razon_social']);
    }

    public function test_guardar_desde_una_sucursal_actualiza_la_empresa_y_solo_sus_series(): void
    {
        $usuario = $this->usuarioConRol('Administrador General');
        $usuario->usarSucursalActiva(3);
        $request = \Mockery::mock(ActualizarDatosGeneralesRequest::class);
        $request->shouldReceive('user')->andReturn($usuario);
        $request->shouldReceive('validated')->withNoArgs()->andReturn([
            'ruc_empresa' => '20111111111', 'razon_social' => 'PRINCIPAL EIRL', 'igv_porcentaje' => 18,
            'serie_boleta' => 'B003', 'serie_factura' => 'F003', 'codigo_establecimiento' => '0002',
        ]);
        $request->shouldReceive('validated')->with('codigo_establecimiento')->andReturn('0002');
        $request->shouldReceive('hasFile')->with('logo')->andReturnFalse();

        app(DatosGeneralesController::class)->update($request);

        $this->assertSame(1, Empresa::query()->count());
        $this->assertDatabaseHas('configuracion.empresa', ['id_empresa' => 1, 'razon_social' => 'PRINCIPAL EIRL']);
        $this->assertDatabaseHas('configuracion.series_sucursal', ['id_sucursal' => 3, 'serie_boleta' => 'B003', 'serie_factura' => 'F003']);
        // La numeración de la otra sucursal no se toca.
        $this->assertDatabaseHas('configuracion.series_sucursal', ['id_sucursal' => 1, 'serie_boleta' => 'B001', 'ult_nro_boleta' => 19]);
        $reserva = DB::transaction(fn () => app(ReservarCorrelativoService::class)->execute(3, '03'));
        $this->assertSame('B003', $reserva['serie']);
        $this->assertSame(1, $reserva['correlativo']);
    }

    public function test_una_serie_no_se_repite_entre_sucursales(): void
    {
        $usuario = $this->usuarioConRol('Administrador General');
        $usuario->usarSucursalActiva(3);
        $request = \Mockery::mock(ActualizarDatosGeneralesRequest::class);
        $request->shouldReceive('user')->andReturn($usuario);
        $request->shouldReceive('validated')->withNoArgs()->andReturn(['ruc_empresa' => '20111111111', 'razon_social' => 'X', 'serie_boleta' => 'B001']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(DatosGeneralesController::class)->update($request);
    }

    private function usuarioConRol(string $nombreRol): User
    {
        $user = new User();
        $user->setRelation('rol', new Rol(['nombre' => $nombreRol, 'estado' => true]));

        return $user;
    }

    private function solicitud(User $user): ActualizarDatosGeneralesRequest
    {
        $request = ActualizarDatosGeneralesRequest::create('/datos-generales', 'PUT');
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }
}
