<?php

namespace Tests\Feature;

use App\Models\TurnoCaja;
use App\Services\Caja\GestionarTurnoCajaService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** El historial de caja debe cuadrar con el arqueo individual del mismo turno. */
class CuadreCajaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['catalogos', 'operaciones'] as $esquema) {
            DB::statement("ATTACH DATABASE ':memory:' AS {$esquema}");
        }
        DB::statement('CREATE TABLE catalogos.metodos_pago (id_metodo INTEGER PRIMARY KEY, nombre TEXT, tipo TEXT)');
        DB::statement('CREATE TABLE operaciones.turnos_caja (id_turno INTEGER PRIMARY KEY, id_usuario INTEGER, id_sucursal INTEGER, id_terminal INTEGER NULL, monto_apertura NUMERIC, monto_cierre_declarado NUMERIC NULL, fecha_apertura DATETIME, fecha_cierre DATETIME NULL, estado TEXT)');
        DB::statement('CREATE TABLE operaciones.ventas (id_venta INTEGER PRIMARY KEY, id_turno INTEGER)');
        DB::statement('CREATE TABLE operaciones.pagos_ventas (id_pago INTEGER PRIMARY KEY, id_venta INTEGER, id_metodo INTEGER, monto NUMERIC, comision_pasarela NUMERIC DEFAULT 0, estado_pago TEXT)');
        DB::statement('CREATE TABLE operaciones.movimientos_caja (id_movimiento INTEGER PRIMARY KEY, id_turno INTEGER, tipo_movimiento TEXT, monto NUMERIC)');

        DB::table('catalogos.metodos_pago')->insert([
            ['id_metodo' => 1, 'nombre' => 'EFECTIVO', 'tipo' => 'EFECTIVO'],
            ['id_metodo' => 2, 'nombre' => 'YAPE', 'tipo' => 'BILLETERA'],
        ]);
        DB::table('operaciones.turnos_caja')->insert([
            'id_turno' => 1, 'id_usuario' => 1, 'id_sucursal' => 1, 'monto_apertura' => 100,
            'monto_cierre_declarado' => 150, 'fecha_apertura' => now(), 'estado' => 'CERRADO',
        ]);
        DB::table('operaciones.ventas')->insert([['id_venta' => 1, 'id_turno' => 1], ['id_venta' => 2, 'id_turno' => 1]]);
        DB::table('operaciones.pagos_ventas')->insert([
            ['id_venta' => 1, 'id_metodo' => 1, 'monto' => 60, 'estado_pago' => 'COMPLETADO'],
            ['id_venta' => 2, 'id_metodo' => 2, 'monto' => 40, 'estado_pago' => 'COMPLETADO'],
            // Un pago no completado nunca entra al cuadre.
            ['id_venta' => 2, 'id_metodo' => 1, 'monto' => 500, 'estado_pago' => 'ANULADO'],
        ]);
        DB::table('operaciones.movimientos_caja')->insert([
            ['id_turno' => 1, 'tipo_movimiento' => 'INGRESO', 'monto' => 5],
            ['id_turno' => 1, 'tipo_movimiento' => 'EGRESO', 'monto' => 20],
        ]);
    }

    public function test_historial_y_arqueo_usan_la_misma_formula_de_gaveta(): void
    {
        $caja = app(GestionarTurnoCajaService::class);
        $turno = TurnoCaja::query()->findOrFail(1);

        $resumen = $caja->resumen($turno);
        $historial = $caja->conDiferenciaDeCuadre(TurnoCaja::query()->get())->first();

        // 100 apertura + 60 efectivo + 5 ingreso - 20 egreso; Yape no entra a gaveta.
        $this->assertSame(145.0, $resumen['esperado_gaveta']);
        $this->assertSame(40.0, $resumen['ventas_digitales_netas']);
        $this->assertSame(round(150 - $resumen['esperado_gaveta'], 2), $historial['diferencia_cuadre']);
        $this->assertSame(1, $historial['id_turno']);
    }

    public function test_turno_sin_cierre_declarado_no_tiene_diferencia(): void
    {
        DB::table('operaciones.turnos_caja')->where('id_turno', 1)->update(['monto_cierre_declarado' => null]);

        $historial = app(GestionarTurnoCajaService::class)->conDiferenciaDeCuadre(TurnoCaja::query()->get())->first();

        $this->assertNull($historial['diferencia_cuadre']);
    }
}
