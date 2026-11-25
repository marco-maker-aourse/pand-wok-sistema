<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\AgenteImpresionController;
use App\Models\TerminalPos;
use App\Services\Impresion\GestionarVinculacionAgenteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class VinculacionAgenteTest extends TestCase
{
    use PreparaBaseRestaurante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararBaseRestaurante();
        DB::table('catalogos.impresoras')->insert([
            ['id_impresora' => 1, 'id_sucursal' => 1, 'nombre' => 'Caja 80', 'ancho_papel_mm' => 80],
            ['id_impresora' => 2, 'id_sucursal' => 1, 'nombre' => 'Caja 58', 'ancho_papel_mm' => 58],
            ['id_impresora' => 3, 'id_sucursal' => 2, 'nombre' => 'Otra sucursal', 'ancho_papel_mm' => 80],
        ]);
    }

    private function vincular(?int $impresora, int $sucursal = 1): array
    {
        DB::table('catalogos.terminales_pos')->insert(['id_terminal' => 1, 'id_sucursal' => $sucursal, 'id_impresora' => $impresora, 'nombre' => 'Caja']);
        $terminal = TerminalPos::withoutGlobalScopes()->findOrFail(1);
        $servicio = Mockery::mock(GestionarVinculacionAgenteService::class);
        $servicio->shouldReceive('confirmar')->once()->andReturn(['token' => str_repeat('a', 64), 'terminal' => $terminal]);

        $request = Request::create('/api/agente-impresion/v1/vinculaciones', 'POST', ['codigo' => '123456', 'identificador_agente' => 'android-1']);

        return app(AgenteImpresionController::class)->vincular($request, $servicio)->getData(true);
    }

    public function test_la_vinculacion_informa_el_ancho_de_papel_de_la_impresora_de_la_terminal(): void
    {
        $this->assertSame(['ancho_papel_mm' => 80], $this->vincular(1)['impresora']);
    }

    public function test_informa_58_mm_cuando_la_impresora_es_de_58(): void
    {
        $this->assertSame(['ancho_papel_mm' => 58], $this->vincular(2)['impresora']);
    }

    public function test_sin_impresora_o_de_otra_sucursal_no_informa_ancho(): void
    {
        $this->assertNull($this->vincular(3)['impresora']);
    }

    private function terminalConCola(array $minutosEnCola): void
    {
        DB::table('catalogos.terminales_pos')->insert(['id_terminal' => 1, 'id_sucursal' => 1, 'id_impresora' => 1, 'nombre' => 'Caja']);
        DB::table('operaciones.trabajos_impresion')->insert(array_map(fn (int $id, int $minutos) => [
            'id_trabajo_impresion' => $id, 'id_sucursal' => 1, 'id_terminal' => 1, 'tipo' => 'PRUEBA', 'estado' => 'PENDIENTE',
            'clave_idempotencia' => "prueba-{$id}", 'contenido' => '{}',
            'disponible_desde' => now()->subMinutes($minutos), 'created_at' => now()->subMinutes($minutos),
        ], array_keys($minutosEnCola), $minutosEnCola));
    }

    private function siguiente(): ?array
    {
        $request = Request::create('/api/agente-impresion/v1/trabajos/siguiente', 'GET');
        $request->attributes->set('terminal_pos', TerminalPos::withoutGlobalScopes()->findOrFail(1));

        return app(AgenteImpresionController::class)->siguiente($request)->getData(true)['data'];
    }

    private function informar(int $id, array $resultado): void
    {
        $request = Request::create("/api/agente-impresion/v1/trabajos/{$id}/resultado", 'POST', $resultado);
        $request->attributes->set('terminal_pos', TerminalPos::withoutGlobalScopes()->findOrFail(1));
        app(AgenteImpresionController::class)->resultado($request, $id);
    }

    private function estado(int $id): object
    {
        return DB::table('operaciones.trabajos_impresion')->where('id_trabajo_impresion', $id)->first();
    }

    public function test_al_reconectar_no_imprime_los_trabajos_que_esperaron_demasiado(): void
    {
        // El celular estuvo apagado: dos trabajos viejos y uno reciente esperan en la cola.
        $this->terminalConCola([1 => 40, 2 => 16, 3 => 2]);

        $this->assertSame(3, $this->siguiente()['id']);
        foreach ([1, 2] as $id) {
            $this->assertSame('CANCELADO', $this->estado($id)->estado);
            $this->assertStringContainsString('No se imprimió a tiempo', $this->estado($id)->detalle_error);
        }
    }

    public function test_sin_conexion_con_la_impresora_el_trabajo_espera_y_sale_al_reconectar(): void
    {
        $this->terminalConCola([1 => 0]);
        $this->assertSame(1, $this->siguiente()['id']);

        // La impresora estaba apagada: no salió papel, el trabajo vuelve a la cola un momento.
        $this->informar(1, ['estado' => 'FALLIDO', 'detalle_error' => 'No se pudo conectar', 'resultado_dispositivo' => 'SIN_CONEXION']);
        $this->assertSame('PENDIENTE', $this->estado(1)->estado);
        $this->assertNull($this->siguiente(), 'no se reintenta en el mismo instante');

        $this->travel(30)->seconds();
        $this->assertSame(1, $this->siguiente()['id'], 'al reconectar la impresora, sale solo');

        // Con la impresora apagada más que la vigencia, ya no se imprime tarde.
        $this->informar(1, ['estado' => 'FALLIDO', 'resultado_dispositivo' => 'SIN_CONEXION']);
        $this->travel(16)->minutes();
        $this->assertNull($this->siguiente());
        $this->assertSame('CANCELADO', $this->estado(1)->estado);
    }

    public function test_un_fallo_al_imprimir_no_se_reintenta_solo(): void
    {
        $this->terminalConCola([1 => 0]);
        $this->siguiente();
        // Pudo salir parte del ticket: reintentar en automático podría duplicarlo.
        $this->informar(1, ['estado' => 'FALLIDO', 'detalle_error' => 'Se interrumpió', 'resultado_dispositivo' => 'ANDROID']);
        $this->assertSame('FALLIDO', $this->estado(1)->estado);
    }

    public function test_cada_sondeo_informa_el_ancho_vigente_para_que_el_celular_lo_adopte(): void
    {
        DB::table('catalogos.terminales_pos')->insert(['id_terminal' => 1, 'id_sucursal' => 1, 'id_impresora' => 1, 'nombre' => 'Caja']);
        $sondear = function (): array {
            $request = Request::create('/api/agente-impresion/v1/trabajos/siguiente', 'GET');
            $request->attributes->set('terminal_pos', TerminalPos::withoutGlobalScopes()->findOrFail(1));

            return app(AgenteImpresionController::class)->siguiente($request)->getData(true);
        };

        $this->assertSame(['data' => null, 'terminal' => ['ancho_papel_mm' => 80]], $sondear());

        DB::table('catalogos.impresoras')->where('id_impresora', 1)->update(['ancho_papel_mm' => 58]);
        $this->assertSame(58, $sondear()['terminal']['ancho_papel_mm']);
    }
}
