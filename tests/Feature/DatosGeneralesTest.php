<?php

namespace Tests\Feature;

use App\Http\Controllers\Configuracion\DatosGeneralesController;
use App\Http\Requests\Configuracion\ActualizarDatosGeneralesRequest;
use App\Models\DatosGen;
use App\Models\Rol;
use App\Models\User;
use App\Services\Comprobantes\ReservarCorrelativoService;
use App\Services\Configuracion\DatosGeneralesService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Datos Generales: empresa global para sus sucursales, autorización y caché coherente. */
class DatosGeneralesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['operaciones', 'inventario'] as $esquema) {
            DB::statement("ATTACH DATABASE ':memory:' AS {$esquema}");
        }
        DB::statement('CREATE TABLE inventario.sucursales (id_sucursal INTEGER PRIMARY KEY, nombre TEXT, codigo_establecimiento TEXT NULL)');
        DB::statement('CREATE TABLE operaciones.datos_gen (id_datos_gen INTEGER PRIMARY KEY, id_sucursal INTEGER, ruc_empresa TEXT NULL, razon_social TEXT NULL, nombre_comercial TEXT NULL, direccion_fiscal TEXT NULL, pie_ticket TEXT NULL, logo_path TEXT NULL, serie_boleta TEXT NULL, serie_factura TEXT NULL, serie_guia TEXT NULL, ult_nro_boleta INTEGER DEFAULT 0, ult_nro_factura INTEGER DEFAULT 0, igv_porcentaje NUMERIC DEFAULT 18, color_primario TEXT NULL, color_secundario TEXT NULL, texto_login_titulo TEXT NULL, texto_login_descripcion TEXT NULL)');

        DB::statement('CREATE TABLE operaciones.series_sucursal (id_sucursal INTEGER PRIMARY KEY, serie_boleta TEXT UNIQUE, serie_factura TEXT UNIQUE, serie_guia TEXT UNIQUE, ult_nro_boleta INTEGER DEFAULT 0, ult_nro_factura INTEGER DEFAULT 0, created_at DATETIME, updated_at DATETIME)');

        DB::table('inventario.sucursales')->insert([
            ['id_sucursal' => 1, 'nombre' => 'PRINCIPAL', 'codigo_establecimiento' => '0000'],
            ['id_sucursal' => 2, 'nombre' => 'OTRA EMPRESA', 'codigo_establecimiento' => '0001'],
            // Sucursal de la empresa principal sin registro propio (el RUC es único por registro).
            ['id_sucursal' => 3, 'nombre' => 'TAMBO', 'codigo_establecimiento' => '0002'],
        ]);
        // La sucursal 2 se registró primero, pero la principal es la del establecimiento 0000.
        DB::table('operaciones.datos_gen')->insert([
            ['id_datos_gen' => 3, 'id_sucursal' => 2, 'ruc_empresa' => '20999999991', 'razon_social' => 'OTRA SAC', 'color_primario' => '#111111', 'pie_ticket' => 'OTRA'],
            ['id_datos_gen' => 5, 'id_sucursal' => 1, 'ruc_empresa' => '20111111111', 'razon_social' => 'PRINCIPAL SAC', 'color_primario' => '#222222', 'pie_ticket' => 'GRACIAS'],
        ]);
    }

    public function test_solo_un_administrador_puede_actualizar_datos_generales(): void
    {
        $this->assertTrue($this->solicitud($this->usuarioConRol('Administrador General'))->authorize());
        $this->assertTrue($this->solicitud($this->usuarioConRol('Administrador'))->authorize());
        $this->assertFalse($this->solicitud($this->usuarioConRol('Cajero'))->authorize());
        $this->assertFalse($this->solicitud($this->usuarioConRol('Almacenero'))->authorize());
    }

    public function test_sucursal_sin_registro_propio_usa_los_datos_de_la_empresa_principal(): void
    {
        $servicio = app(DatosGeneralesService::class);

        $this->assertSame('PRINCIPAL SAC', $servicio->paraEdicion(3)->razon_social);
        $this->assertSame(1, (int) $servicio->paraEdicion(3)->id_sucursal);
        $this->assertSame('#222222', $servicio->identidad(3)['color_primario']);
        // Consultar no crea registros.
        $this->assertSame(2, DatosGen::query()->count());
    }

    public function test_sucursal_con_registro_propio_conserva_su_empresa(): void
    {
        $servicio = app(DatosGeneralesService::class);

        $this->assertSame('OTRA SAC', $servicio->identidad(2)['razon_social']);
        $this->assertSame('OTRA SAC', $servicio->emisor(2)->razon_social);
    }

    public function test_emisor_combina_datos_de_empresa_con_el_establecimiento_de_la_sucursal(): void
    {
        $emisor = app(DatosGeneralesService::class)->emisor(3);

        $this->assertSame('PRINCIPAL SAC', $emisor->razon_social);
        $this->assertSame('20111111111', $emisor->ruc_empresa);
        $this->assertSame('GRACIAS', $emisor->pie_ticket);
        $this->assertSame('0002', $emisor->codigo_establecimiento);
    }

    public function test_sin_ninguna_configuracion_no_se_inventan_datos(): void
    {
        DB::table('operaciones.datos_gen')->delete();
        $servicio = app(DatosGeneralesService::class);

        $datos = $servicio->paraEdicion(3);
        $this->assertFalse($datos->exists);
        $this->assertNull($datos->ruc_empresa);
        $this->assertNull($datos->razon_social);
        $this->assertNull($servicio->emisor(3));
        $this->assertNull($servicio->identidad(3));
    }

    public function test_guardar_invalida_la_identidad_de_todas_las_sucursales(): void
    {
        $servicio = app(DatosGeneralesService::class);

        $this->assertSame('PRINCIPAL SAC', $servicio->identidad(null)['razon_social']);
        $this->assertSame('PRINCIPAL SAC', $servicio->identidad(3)['razon_social']);

        DB::table('operaciones.datos_gen')->where('id_sucursal', 1)->update(['razon_social' => 'PRINCIPAL EIRL']);
        // Mientras no se guarde por la aplicación, se sirve la caché vigente.
        $this->assertSame('PRINCIPAL SAC', $servicio->identidad(3)['razon_social']);

        $servicio->olvidarIdentidad();

        $this->assertSame('PRINCIPAL EIRL', $servicio->identidad(3)['razon_social']);
        $this->assertSame('PRINCIPAL EIRL', $servicio->identidad(null)['razon_social']);
    }

    public function test_guardar_series_de_sede_secundaria_preserva_la_numeracion_principal(): void
    {
        DB::table('operaciones.datos_gen')->where('id_sucursal', 1)->update(['serie_boleta' => 'B001', 'ult_nro_boleta' => 19]);
        $usuario = $this->usuarioConRol('Administrador General');
        $usuario->usarSucursalActiva(3);
        $request = \Mockery::mock(ActualizarDatosGeneralesRequest::class);
        $request->shouldReceive('user')->andReturn($usuario);
        $request->shouldReceive('validated')->withNoArgs()->andReturn(['serie_boleta' => 'B003', 'serie_factura' => 'F003']);
        $request->shouldReceive('validated')->with('codigo_establecimiento')->andReturn('0002');
        $request->shouldReceive('hasFile')->with('logo')->andReturnFalse();

        app(DatosGeneralesController::class)->update($request);

        $this->assertDatabaseHas('operaciones.series_sucursal', ['id_sucursal' => 3, 'serie_boleta' => 'B003', 'serie_factura' => 'F003']);
        $this->assertDatabaseHas('operaciones.datos_gen', ['id_sucursal' => 1, 'serie_boleta' => 'B001', 'ult_nro_boleta' => 19]);
        $reserva = DB::transaction(fn () => app(ReservarCorrelativoService::class)->execute(3, '03'));
        $this->assertSame('B003', $reserva['serie']);
        $this->assertSame(1, $reserva['correlativo']);
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
