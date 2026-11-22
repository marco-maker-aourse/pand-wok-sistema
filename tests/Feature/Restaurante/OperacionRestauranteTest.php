<?php

namespace Tests\Feature\Restaurante;

use App\Jobs\EmitirComprobanteSunatJob;
use App\Models\Kardex;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Models\User;
use App\Services\Caja\GestionarTurnoCajaService;
use App\Services\Restaurante\CocinaService;
use App\Services\Restaurante\CobrarPedidoService;
use App\Services\Restaurante\EnviarComandaService;
use App\Services\Restaurante\GestionarPedidoService;
use App\Services\Restaurante\RecetasService;
use App\Services\Restaurante\ReportesRestauranteService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperacionRestauranteTest extends TestCase
{
    use PreparaOperacionRestaurante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararOperacionRestaurante();
    }

    private function pedido(array $lineas, int $sucursal = 1): Pedido
    {
        $servicio = app(GestionarPedidoService::class);
        $pedido = $servicio->abrir(['tipo' => Pedido::MESA, 'id_mesa' => $sucursal === 1 ? 1 : 3, 'clave_idempotencia' => "pedido-{$sucursal}"], 10, $sucursal);
        $servicio->agregarItems($pedido->id_pedido, $lineas, 10, $sucursal);

        return $pedido;
    }

    private function cobro(PedidoDetalle $item, string $clave, float $cantidad, float $cobrada, float $monto): array
    {
        return [
            'clave_cobro' => $clave, 'tipo_comprobante' => 'BOLETA',
            'items' => [['id_detalle_pedido' => $item->getKey(), 'cantidad' => $cantidad, 'cobrada' => $cobrada]],
            'pagos' => [['id_metodo' => 1, 'monto' => $monto, 'monto_recibido' => 100]],
        ];
    }

    private function saldo(int $producto): float
    {
        return (float) Kardex::query()->where('id_producto', $producto)->where('id_sucursal', 1)->latest('id_kardex')->value('stock_actual');
    }

    public function test_cobro_parcial_y_total_con_precio_congelado_turno_e_idempotencia(): void
    {
        $caja = app(GestionarTurnoCajaService::class);
        $turno = $caja->abrir(10, 1, 20);
        $pedido = $this->pedido([$this->lomo('L1', 2, [1, 3])]);
        $item = $pedido->detalles()->sole();
        app(EnviarComandaService::class)->enviar($pedido->getKey(), 'envio', 10, 1);
        DB::table('catalogos.producto_presentaciones')->where('id_precio', 1)->update(['precio_venta' => 42]);

        $cobrar = app(CobrarPedidoService::class);
        $parcial = $this->cobro($item, 'pasada-1', 1, 0, 32.5);
        $venta = $cobrar->ejecutar($pedido->getKey(), 1, 10, $parcial, false)['venta'];
        $this->assertSame('32.50', $venta->total_venta);
        $this->assertSame('1.000', $item->refresh()->cantidad_cobrada);
        $this->assertSame('OCUPADA', $this->estadoMesa(1));

        $repetida = $cobrar->ejecutar($pedido->getKey(), 1, 10, $parcial, false);
        $this->assertFalse($repetida['nueva']);
        $this->assertSame($venta->getKey(), $repetida['venta']->getKey());

        try {
            $cobrar->ejecutar($pedido->getKey(), 1, 10, [...$parcial, 'clave_cobro' => 'otra-clave'], false);
            $this->fail('Se aceptó una vista obsoleta del saldo.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('saldo', $e->getMessage());
        }

        $resto = $this->cobro($item, 'pasada-2', 1, 1, 30);
        $resto['tipo_comprobante'] = 'FACTURA';
        $resto['id_cliente'] = 2;
        $resto['descuento_global'] = 2.5;
        $resto['pagos'] = [
            ['id_metodo' => 1, 'monto' => 20, 'monto_recibido' => 25],
            ['id_metodo' => 2, 'monto' => 10, 'nro_referencia' => 'TEST-1'],
        ];
        $final = $cobrar->ejecutar($pedido->getKey(), 1, 10, $resto, true)['venta'];
        $this->assertSame('F001', $final->serie);
        $this->assertSame('30.00', $final->total_venta);
        $this->assertSame(Pedido::CERRADO, $pedido->refresh()->estado);
        $this->assertSame('DISPONIBLE', $this->estadoMesa(1));
        $this->assertDatabaseCount('operaciones.ventas', 2);
        $this->assertSame(0.0, $caja->cerrar($turno->getKey(), 10, 1, 72.5));
        $this->assertFalse($cobrar->ejecutar($pedido->getKey(), 1, 10, $resto, true)['nueva']);
    }

    public function test_errores_de_pago_y_descuento_no_alteran_saldo_stock_ni_correlativo(): void
    {
        $pedido = $this->pedido([$this->gaseosa('G1', 2)]);
        $item = $pedido->detalles()->sole();
        $cobrar = app(CobrarPedidoService::class);
        $comando = $this->cobro($item, 'compra', 2, 0, 9.99);
        $intentar = function (array $datos, string $mensaje) use ($cobrar, $pedido): void {
            try {
                $cobrar->ejecutar($pedido->getKey(), 1, 10, $datos, false);
                $this->fail('Se registró un cobro inválido.');
            } catch (DomainException $e) {
                $this->assertStringContainsString($mensaje, $e->getMessage());
            }
        };
        $intentar($comando, 'turno');
        app(GestionarTurnoCajaService::class)->abrir(10, 1, 0);
        $intentar([...$comando, 'descuento_global' => 1], 'permiso');
        $intentar($comando, 'suma de pagos');

        $this->assertDatabaseCount('operaciones.ventas', 0);
        $this->assertSame(20.0, $this->saldo(2));
        $this->assertSame('0.000', $item->refresh()->cantidad_cobrada);
        $this->assertSame(0, DB::table('configuracion.series_sucursal')->value('ult_nro_boleta'));
    }

    public function test_receta_convierte_unidades_registra_consumo_unico_y_repone_stock(): void
    {
        DB::table('catalogos.producto_presentaciones')->where('id_precio', 3)->update(['factor_conversion' => 0.5]);
        app(RecetasService::class)->guardar(1, 1, [['id_precio_insumo' => 3, 'cantidad' => 2]]);
        $pedido = $this->pedido([$this->lomo('L1', 2)]);
        $envios = app(EnviarComandaService::class);
        $envios->enviar($pedido->getKey(), 'envio', 10, 1);
        $envios->enviar($pedido->getKey(), 'envio', 10, 1);
        $this->assertDatabaseCount('restaurante.consumos_insumos', 1);
        $this->assertSame(8.0, $this->saldo(3));
        $item = $pedido->detalles()->sole();
        app(GestionarPedidoService::class)->anularItem($item->getKey(), 'Cancelado antes de preparar', true, 10, 1, true);
        app(GestionarPedidoService::class)->anularItem($item->getKey(), 'Cancelado antes de preparar', true, 10, 1, true);
        $this->assertSame(10.0, $this->saldo(3));
        $this->assertDatabaseHas('restaurante.consumos_insumos', ['estado' => 'REVERTIDO', 'costo_unitario' => 4]);
        $this->assertSame(1, Kardex::query()->where('tipo_operacion', 'REVERSION_RECETA')->count());
    }

    public function test_merma_y_anulacion_en_preparacion_no_duplican_salida(): void
    {
        $recetas = app(RecetasService::class);
        $recetas->guardar(1, 1, [['id_precio_insumo' => 3, 'cantidad' => 1]]);
        $pedido = $this->pedido([$this->lomo('L1')]);
        $comanda = app(EnviarComandaService::class)->enviar($pedido->getKey(), 'envio', 10, 1)['comandas']->sole();
        app(CocinaService::class)->cambiarEstado($comanda->getKey(), Comanda::EN_PREPARACION, 1);
        app(GestionarPedidoService::class)->anularPedido($pedido->getKey(), 'Ya comenzó la preparación', true, 10, 1, true);
        $this->assertSame(9.0, $this->saldo(3));
        $this->assertDatabaseHas('restaurante.consumos_insumos', ['estado' => 'MERMA']);

        $datos = ['id_precio' => 3, 'cantidad' => 2, 'clave' => 'merma-1', 'motivo' => 'Producto deteriorado'];
        $this->assertSame($recetas->registrarMerma(1, 10, $datos), $recetas->registrarMerma(1, 10, $datos));
        $this->assertSame(7.0, $this->saldo(3));
        $this->assertDatabaseCount('restaurante.mermas', 1);
    }

    public function test_stock_insuficiente_revierte_la_comanda_y_el_snapshot(): void
    {
        app(RecetasService::class)->guardar(1, 1, [['id_precio_insumo' => 3, 'cantidad' => 12]]);
        $pedido = $this->pedido([$this->lomo('L1')]);
        try {
            app(EnviarComandaService::class)->enviar($pedido->getKey(), 'envio', 10, 1);
            $this->fail('Se envió una comanda sin existencias.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Stock insuficiente', $e->getMessage());
        }
        $this->assertSame(PedidoDetalle::PENDIENTE, $pedido->detalles()->sole()->estado);
        $this->assertDatabaseCount('restaurante.comandas', 0);
        $this->assertDatabaseCount('restaurante.consumos_insumos', 0);
    }

    public function test_el_tipo_original_del_plato_evita_un_segundo_descuento_de_stock_al_cobrar(): void
    {
        app(RecetasService::class)->guardar(1, 1, [['id_precio_insumo' => 3, 'cantidad' => 1]]);
        app(GestionarTurnoCajaService::class)->abrir(10, 1, 0);
        $pedido = $this->pedido([$this->lomo('L1', 2)]);
        app(EnviarComandaService::class)->enviar($pedido->getKey(), 'envio', 10, 1);
        DB::table('catalogos.productos')->where('id_producto', 1)->update(['tipo_producto' => 'DIRECTO']);
        $item = $pedido->detalles()->sole();
        app(CobrarPedidoService::class)->ejecutar($pedido->getKey(), 1, 10, $this->cobro($item, 'un-plato', 1, 0, 30), false);

        $this->assertSame(8.0, $this->saldo(3));
        $this->assertFalse(Kardex::query()->where('id_producto', 1)->exists());
        $hoy = CarbonImmutable::today('America/Lima');
        $reporte = app(ReportesRestauranteService::class)->consultar([1], $hoy, $hoy);
        $this->assertSame(4.0, $reporte['resumen']['costo_registrado']);
        $this->assertSame('Lomo saltado', $reporte['platos'][0]->nombre);
    }

    public function test_anular_el_ultimo_item_sin_cobrar_cierra_una_cuenta_saldada(): void
    {
        app(GestionarTurnoCajaService::class)->abrir(10, 1, 0);
        $pedido = $this->pedido([$this->gaseosa('G1'), $this->gaseosa('G2')]);
        [$pagado, $pendiente] = $pedido->detalles()->orderBy('id_detalle_pedido')->get();
        app(CobrarPedidoService::class)->ejecutar($pedido->getKey(), 1, 10, $this->cobro($pagado, 'pagado', 1, 0, 5), false);
        app(GestionarPedidoService::class)->anularItem($pendiente->getKey(), null, false, 10, 1);

        $this->assertSame(Pedido::CERRADO, $pedido->refresh()->estado);
        $this->assertSame('DISPONIBLE', $this->estadoMesa(1));
        $this->assertSame(19.0, $this->saldo(2));
    }

    public function test_rutas_de_recetas_y_mermas_aplican_sucursal_y_permisos(): void
    {
        $this->actingAs(User::findOrFail(30))->get('/recetas')->assertForbidden();
        $this->get('/mermas')->assertForbidden();

        $this->actingAs(User::findOrFail(10))->from('/recetas');
        $this->put('/recetas/1', ['ingredientes' => [['id_precio_insumo' => 3, 'cantidad' => '0.3333']]])
            ->assertRedirect('/recetas')->assertSessionHasNoErrors();
        $this->get('/recetas')->assertInertia(fn (Assert $page) => $page
            ->component('Restaurante/Recetas')->has('recetas.1', 1)->has('insumos', 1));

        $this->from('/mermas')->post('/mermas', ['id_precio' => 3, 'cantidad' => '0.5000', 'motivo' => 'Merma de prueba', 'clave' => str_repeat('m', 20)])
            ->assertRedirect('/mermas')->assertSessionHasNoErrors();
        $this->assertSame(9.5, $this->saldo(3));
        $this->actingAs(User::findOrFail(10))->withSession(['sucursal_activa' => 2])->get('/mermas')
            ->assertInertia(fn (Assert $page) => $page->component('Restaurante/Recetas')->has('mermas', 0));
    }

    public function test_rutas_de_cobro_reportes_y_sucursal(): void
    {
        Bus::fake();
        $pedido = $this->pedido([$this->gaseosa('G1')]);
        $otro = $this->pedido([$this->gaseosa('G2')], 2);
        $url = "/pedidos/{$pedido->getKey()}/cobrar";
        $this->actingAs(User::findOrFail(30))->get($url)->assertForbidden();
        $this->get('/reportes/restaurante')->assertForbidden();

        $this->actingAs(User::findOrFail(20));
        app(GestionarTurnoCajaService::class)->abrir(20, 1, 0);
        $this->get("/pedidos/{$otro->getKey()}/cobrar")->assertNotFound();
        $this->get('/reportes/restaurante?sucursal=2')->assertForbidden();
        $this->from($url)->get($url)->assertInertia(fn (Assert $page) => $page->component('Restaurante/CobrarPedido')->has('items', 1));
        $datos = $this->cobro($pedido->detalles()->sole(), str_repeat('a', 24), 1, 0, 5);
        $this->post($url, $datos)->assertRedirect($url);
        Bus::assertDispatchedAfterResponse(EmitirComprobanteSunatJob::class);
        $this->assertSame(19.0, $this->saldo(2));
        $this->assertDatabaseCount('operaciones.ventas', 1);
        $this->get('/reportes/restaurante?sucursal=0')->assertInertia(fn (Assert $page) => $page
            ->component('Restaurante/Reportes')->where('reporte.resumen.total', 5)->where('reporte.resumen.costo_registrado', 2)
            ->has('reporte.sucursales', 1));

        $hoy = CarbonImmutable::today('America/Lima');
        $datosNorte = app(ReportesRestauranteService::class)->consultar([2], $hoy, $hoy);
        $this->assertSame(0, $datosNorte['resumen']['cobros']);
    }
}
