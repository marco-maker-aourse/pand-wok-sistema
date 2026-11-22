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
