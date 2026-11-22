<?php

namespace Tests\Feature\Restaurante;

use App\Models\Restaurante\Pedido;
use App\Models\User;
use App\Models\Venta;
use App\Services\Caja\GestionarTurnoCajaService;
use App\Services\Restaurante\VentaPosService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * El POS no debe bloquear el cobro en silencio: el cliente "público general" se crea solo
 * y cualquier requisito que falte (caja, métodos de pago, comprobantes, series) se explica.
 */
class RequisitosCobroPosTest extends TestCase
{
    use PreparaOperacionRestaurante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararOperacionRestaurante();
        Bus::fake();
        DB::table('catalogos.tipos_documento')->insert(['id_tipo_documento' => 3, 'codigo_sunat' => '1']);
        // Instalación nueva: todavía no existe el cliente de público general.
        DB::table('catalogos.clientes')->where('num_documento', '00000000')->delete();
    }

    private function pos()
    {
        return $this->actingAs(User::findOrFail(10))->get('/pos');
    }

    public function test_sin_cliente_de_publico_general_el_pos_lo_crea_y_lo_preselecciona(): void
    {
        $this->pos()->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('clienteInicial.num_documento', '00000000')
            ->where('clienteInicial.nombre_razon_social', 'PÚBLICO EN GENERAL'));

        $this->pos();
        $this->assertSame(1, DB::table('catalogos.clientes')->where('num_documento', '00000000')->count(), 'Se crea una sola vez.');
    }

    public function test_cobrar_sin_elegir_cliente_funciona_aunque_no_existiera_el_publico_general(): void
    {
        app(GestionarTurnoCajaService::class)->abrir(10, 1, 0);

        $resultado = app(VentaPosService::class)->cobrar(null, [
            'clave' => 'requisitos00000001', 'tipo' => Pedido::PARA_LLEVAR,
            'items' => [$this->gaseosa('gaseosareq00000001')],
            'tipo_comprobante' => 'BOLETA', 'pagos' => [['id_metodo' => 1, 'monto' => 5, 'monto_recibido' => 10]],
        ], 10, 1, false);

        $this->assertSame('00000000', $resultado['venta']->cliente->num_documento);
        $this->assertSame(1, Venta::query()->count());
    }

    public function test_el_pos_explica_que_falta_para_cobrar(): void
    {
        DB::table('catalogos.metodos_pago')->update(['estado' => false]);
        DB::table('catalogos.tipos_comprobante')->where('codigo_sunat', '03')->update(['estado' => false]);
        DB::table('configuracion.series_sucursal')->update(['serie_boleta' => null]);

        $this->pos()->assertInertia(fn (Assert $page) => $page->where('requisitosCobro', fn ($requisitos) => collect($requisitos)->pluck('clave')->sort()->values()->all()
            === ['caja', 'comprobantes', 'metodos_pago', 'series']));

        app(GestionarTurnoCajaService::class)->abrir(10, 1, 0);
        DB::table('catalogos.metodos_pago')->update(['estado' => true]);
        DB::table('catalogos.tipos_comprobante')->update(['estado' => true]);
        DB::table('configuracion.series_sucursal')->update(['serie_boleta' => 'B001']);
        $this->pos()->assertInertia(fn (Assert $page) => $page->where('requisitosCobro', []));
    }
}
