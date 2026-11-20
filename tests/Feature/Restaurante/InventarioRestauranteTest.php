<?php

namespace Tests\Feature\Restaurante;

use App\Models\Kardex;
use App\Models\ProductoPresentacion;
use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Services\Caja\GestionarTurnoCajaService;
use App\Services\Inventario\GestionarExistenciaService;
use App\Services\Inventario\OrigenMovimiento;
use App\Services\Restaurante\EnviarComandaService;
use App\Services\Restaurante\GestionarPedidoService;
use App\Services\Restaurante\PresentarRestauranteService;
use App\Services\Restaurante\RecetasService;
use App\Services\Restaurante\VentaPosService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Stock real del restaurante:
 * - Variante física: Inca Kola 500 ml y 1 L tienen inventario propio.
 * - Insumo comprado por caja y consumido por unidad: filtros de manzanilla.
 * - Presentaciones con receta: mate en taza (1 filtro) o jarra (2 filtros).
 */
class InventarioRestauranteTest extends TestCase
{
    use PreparaCartaEstructurada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCartaEstructurada();
        Bus::fake();

        DB::table('catalogos.presentaciones')->insert([
            ['id_presentacion' => 2, 'nombre' => '500 ml'], ['id_presentacion' => 3, 'nombre' => '1 L'],
            ['id_presentacion' => 4, 'nombre' => 'Caja x25'], ['id_presentacion' => 5, 'nombre' => 'Taza'], ['id_presentacion' => 6, 'nombre' => 'Jarra'],
        ]);
        DB::table('catalogos.productos')->insert([
            ['id_producto' => 40, 'nombre' => 'Inca Kola', 'tipo_producto' => 'DIRECTO'],
            ['id_producto' => 41, 'nombre' => 'Inca Kola botella 500 ml', 'tipo_producto' => 'INSUMO'],
            ['id_producto' => 43, 'nombre' => 'Inca Kola botella 1 L', 'tipo_producto' => 'INSUMO'],
            ['id_producto' => 50, 'nombre' => 'Filtro de manzanilla', 'tipo_producto' => 'INSUMO'],
            ['id_producto' => 52, 'nombre' => 'Mate de manzanilla', 'tipo_producto' => 'PREPARADO'],
        ]);
        DB::table('inventario.precio_producto')->insert([
            ['id_precio' => 40, 'id_producto' => 40, 'id_presentacion' => 2, 'factor_conversion' => 1, 'precio_venta' => 3, 'id_producto_stock' => 41],
            ['id_precio' => 42, 'id_producto' => 40, 'id_presentacion' => 3, 'factor_conversion' => 1, 'precio_venta' => 6, 'id_producto_stock' => 43],
            ['id_precio' => 41, 'id_producto' => 41, 'id_presentacion' => 1, 'factor_conversion' => 1, 'precio_venta' => 0, 'id_producto_stock' => null],
            ['id_precio' => 43, 'id_producto' => 43, 'id_presentacion' => 1, 'factor_conversion' => 1, 'precio_venta' => 0, 'id_producto_stock' => null],
            ['id_precio' => 50, 'id_producto' => 50, 'id_presentacion' => 1, 'factor_conversion' => 1, 'precio_venta' => 0, 'id_producto_stock' => null],
            ['id_precio' => 51, 'id_producto' => 50, 'id_presentacion' => 4, 'factor_conversion' => 25, 'precio_venta' => 0, 'id_producto_stock' => null],
            ['id_precio' => 53, 'id_producto' => 52, 'id_presentacion' => 5, 'factor_conversion' => 1, 'precio_venta' => 3, 'id_producto_stock' => null],
            ['id_precio' => 54, 'id_producto' => 52, 'id_presentacion' => 6, 'factor_conversion' => 1, 'precio_venta' => 8, 'id_producto_stock' => null],
        ]);
        DB::table('inventario.kardex')->insert([
            ['id_sucursal' => 1, 'id_producto' => 41, 'stock_actual' => 24, 'precio_costo' => 1.5],
            ['id_sucursal' => 1, 'id_producto' => 43, 'stock_actual' => 12, 'precio_costo' => 3],
        ]);
    }

    private function saldo(int $producto, int $sucursal = 1): float
    {
        return (float) Kardex::query()->where('id_producto', $producto)->where('id_sucursal', $sucursal)->latest('id_kardex')->value('stock_actual');
    }

    private function recetasMate(): void
    {
        $recetas = app(RecetasService::class);
        $recetas->guardar(53, 1, [['id_precio_insumo' => 50, 'cantidad' => 1]]);
        $recetas->guardar(54, 1, [['id_precio_insumo' => 50, 'cantidad' => 2]]);
    }

    private function comprarFiltros(int $cajas): void
    {
        app(GestionarExistenciaService::class)->increase(ProductoPresentacion::query()->findOrFail(51), 1, $cajas,
            new OrigenMovimiento(OrigenMovimiento::INGRESO, 1, 'ING-1', 10), 10);
    }

    public function test_vender_la_variante_de_1_litro_descuenta_solo_botellas_de_1_litro(): void
    {
        app(GestionarTurnoCajaService::class)->abrir(10, 1, 0);
        $carta = app(PresentarRestauranteService::class)->cartaPedido(1);
        $presentaciones = collect($carta['productos'])->firstWhere('id_producto', 40)['presentaciones'];
        $this->assertEquals([['500 ml', 3.0, 24.0], ['1 L', 6.0, 12.0]],
            collect($presentaciones)->map(fn ($p) => [$p['nombre'], $p['precio_venta'], $p['stock']])->all());

        app(VentaPosService::class)->cobrar(null, [
            'clave' => 'incakola00000000001', 'tipo' => Pedido::PARA_LLEVAR,
            'items' => [['clave' => 'ik1Laaaaaaaaaaaaaa', 'id_precio' => 42, 'cantidad' => 1]],
            'tipo_comprobante' => 'BOLETA', 'pagos' => [['id_metodo' => 1, 'monto' => 6, 'monto_recibido' => 10]],
        ], 10, 1, false);

        $this->assertSame(11.0, $this->saldo(43));
        $this->assertSame(24.0, $this->saldo(41), 'La botella de 500 ml no se toca.');
        $this->assertFalse(Kardex::query()->where('id_producto', 40)->exists(), 'El producto de carta no tiene stock propio.');
    }

    public function test_los_filtros_entran_por_caja_y_se_consumen_por_taza_y_jarra(): void
    {
        $this->comprarFiltros(2);
        $this->assertSame(50.0, $this->saldo(50), '2 cajas x25 = 50 filtros en unidad base.');
        $this->recetasMate();

        $pedido = app(GestionarPedidoService::class)->abrir(['tipo' => Pedido::MESA, 'id_mesa' => 1, 'clave_idempotencia' => 'mate'], 10, 1);
        app(GestionarPedidoService::class)->agregarItems($pedido->id_pedido, [
            ['clave' => 'taza', 'id_precio' => 53, 'cantidad' => 1],
            ['clave' => 'jarra', 'id_precio' => 54, 'cantidad' => 1],
        ], 10, 1);
        app(EnviarComandaService::class)->enviar($pedido->id_pedido, 'envio-mate', 10, 1);

        $this->assertSame(47.0, $this->saldo(50));
    }

    public function test_reenviar_la_misma_comanda_no_consume_otra_vez(): void
    {
        $this->comprarFiltros(1);
        $this->recetasMate();
        $pedido = app(GestionarPedidoService::class)->abrir(['tipo' => Pedido::MESA, 'id_mesa' => 1, 'clave_idempotencia' => 'mate'], 10, 1);
        app(GestionarPedidoService::class)->agregarItems($pedido->id_pedido, [['clave' => 'jarra', 'id_precio' => 54, 'cantidad' => 2]], 10, 1);

        $envios = app(EnviarComandaService::class);
        $envios->enviar($pedido->id_pedido, 'envio-jarras', 10, 1);
        $envios->enviar($pedido->id_pedido, 'envio-jarras', 10, 1);

        $this->assertSame(21.0, $this->saldo(50));
        $this->assertSame(1, DB::table('restaurante.consumos_insumos')->count());
    }

    public function test_anular_un_item_enviado_devuelve_sus_filtros_y_la_merma_es_idempotente(): void
    {
        $this->comprarFiltros(1);
        $this->recetasMate();
        $servicio = app(GestionarPedidoService::class);
        $pedido = $servicio->abrir(['tipo' => Pedido::MESA, 'id_mesa' => 1, 'clave_idempotencia' => 'mate'], 10, 1);
        [$jarra] = $servicio->agregarItems($pedido->id_pedido, [['clave' => 'jarra', 'id_precio' => 54, 'cantidad' => 1]], 10, 1);
        app(EnviarComandaService::class)->enviar($pedido->id_pedido, 'envio', 10, 1);
        $this->assertSame(23.0, $this->saldo(50));

        $servicio->anularItem($jarra->id_detalle_pedido, 'Cliente cambió de pedido', true, 10, 1, true);
        $this->assertSame(25.0, $this->saldo(50));
        $this->assertSame(PedidoDetalle::ANULADO, $jarra->refresh()->estado);

        $merma = ['id_precio' => 50, 'cantidad' => 1.5, 'clave' => 'merma-filtros', 'motivo' => 'Filtros húmedos'];
        app(RecetasService::class)->registrarMerma(1, 10, $merma);
        app(RecetasService::class)->registrarMerma(1, 10, $merma);
        $this->assertSame(23.5, $this->saldo(50), 'Cantidades fraccionarias y una sola salida por merma.');
    }

    public function test_el_stock_y_el_consumo_son_por_sucursal(): void
    {
        $this->comprarFiltros(1);
        DB::table('inventario.kardex')->insert(['id_sucursal' => 2, 'id_producto' => 50, 'stock_actual' => 5, 'precio_costo' => 0.4]);
        app(RecetasService::class)->guardar(54, 2, [['id_precio_insumo' => 50, 'cantidad' => 2]]);
        $servicio = app(GestionarPedidoService::class);
        $pedido = $servicio->abrir(['tipo' => Pedido::MESA, 'id_mesa' => 3, 'clave_idempotencia' => 'norte'], 10, 2);
        $servicio->agregarItems($pedido->id_pedido, [['clave' => 'jarra-n', 'id_precio' => 54, 'cantidad' => 1]], 10, 2);
        app(EnviarComandaService::class)->enviar($pedido->id_pedido, 'envio-n', 10, 2);

        $this->assertSame(3.0, $this->saldo(50, 2));
        $this->assertSame(25.0, $this->saldo(50, 1), 'La otra sucursal no se toca.');
    }
}
