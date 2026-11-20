<?php

namespace Tests\Feature\Restaurante;

use App\Models\Kardex;
use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Services\Comprobantes\DescripcionItems;
use App\Services\Restaurante\ArmarItemPedidoService;
use App\Services\Restaurante\CocinaService;
use App\Services\Restaurante\EnviarComandaService;
use App\Services\Restaurante\GestionarPedidoService;
use App\Services\Restaurante\OpcionesSucursalService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Menú con sopa incluida y combinaciones con acompañamientos, por sucursal. */
class MenusYCombinacionesTest extends TestCase
{
    use PreparaCartaEstructurada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCartaEstructurada();
    }

    private function pedido(int $sucursal = 1): Pedido
    {
        return app(GestionarPedidoService::class)->abrir(['tipo' => Pedido::MESA, 'id_mesa' => $sucursal === 1 ? 1 : 3, 'clave_idempotencia' => "p{$sucursal}"], 10, $sucursal);
    }

    /** @return array<int, PedidoDetalle> */
    private function agregar(array $items, int $sucursal = 1, ?Pedido $pedido = null): array
    {
        $pedido ??= $this->pedido($sucursal);

        return app(GestionarPedidoService::class)->agregarItems($pedido->id_pedido, $items, 10, $sucursal);
    }

    private function armar(array $item, int $sucursal = 1): array
    {
        return app(ArmarItemPedidoService::class)->armar($item, $sucursal);
    }

    private function saldo(int $producto): float
    {
        return (float) Kardex::query()->where('id_producto', $producto)->where('id_sucursal', 1)->latest('id_kardex')->value('stock_actual');
    }

    // ---------- Menú con sopa ----------

    public function test_la_sopa_va_incluida_sin_recargo_y_queda_registrada_en_el_detalle(): void
    {
        [$item] = $this->agregar([$this->menu('m1')]);

        $this->assertSame('15.0000', $item->precio_unitario);
        $this->assertSame('15.00', $item->subtotal);
        $this->assertSame(1, PedidoDetalle::query()->count(), 'La sopa no es una línea aparte: no se cobra dos veces.');
        $this->assertDatabaseHas('restaurante.pedido_detalle_modificadores', [
            'id_detalle_pedido' => $item->id_detalle_pedido, 'id_opcion' => 13, 'id_precio' => 13,
            'nombre_grupo' => 'Sopa del menú', 'nombre_opcion' => 'Sopa wantán', 'precio_adicional' => 0, 'cantidad' => 1,
        ]);
    }

    public function test_la_sopa_es_obligatoria_salvo_que_el_plato_la_haga_opcional(): void
    {
        try {
            $this->armar(['id_precio' => 10, 'cantidad' => 1]);
            $this->fail('Se aceptó un menú sin sopa.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Sopa del menú', $e->getMessage());
        }

        DB::table('restaurante.producto_grupo_modificador')->where('id_producto', 10)->update(['seleccion_minima' => 0]);
        $this->assertSame(15.0, $this->armar(['id_precio' => 10, 'cantidad' => 1])['detalle']['precio_unitario']);
    }

    public function test_una_sopa_desactivada_en_la_sucursal_no_se_ofrece_ni_se_acepta(): void
    {
        app(OpcionesSucursalService::class)->fijar(13, 1, null, false);

        $this->assertSame(OpcionesSucursalService::NO_DISPONIBLE, app(OpcionesSucursalService::class)->opciones([10], 1)[13]->estado);
        $this->assertSame(OpcionesSucursalService::DISPONIBLE, app(OpcionesSucursalService::class)->opciones([10], 2)[13]->estado);
        $this->expectExceptionMessage('Sopa wantán no está disponible en esta sucursal');
        $this->armar($this->menu('x'));
    }

    public function test_una_sopa_agotada_no_se_acepta_y_las_demas_si(): void
    {
        DB::table('restaurante.productos_agotados')->insert(['id_sucursal' => 1, 'id_producto' => 13, 'id_usuario' => 10, 'marcado_en' => now()]);

        $this->assertSame(15.0, $this->armar($this->menu('a', 1, [11 => 1]))['detalle']['precio_unitario']);
        $this->assertSame(15.0, $this->armar($this->menu('b'), 2)['detalle']['precio_unitario'], 'En otra sucursal sigue disponible.');
        $this->expectExceptionMessage('Sopa wantán está agotado');
        $this->armar($this->menu('c'));
    }

    public function test_dos_menus_con_sopas_distintas_se_ven_asi_en_cocina_y_en_el_comprobante(): void
    {
        $pedido = $this->pedido();
        [$item] = $this->agregar([$this->menu('m2', 2, [13 => 1, 11 => 1])], 1, $pedido);
        $this->assertSame('30.00', $item->subtotal);

        app(EnviarComandaService::class)->enviar($pedido->id_pedido, 'envio-menus', 10, 1);
        $cocina = app(CocinaService::class)->tablero(1, null);
        $opciones = collect($cocina)->flatMap(fn ($c) => $c['items'])->sole()['opciones'];

        $this->assertSame(['1 × Sopa de pollo', '1 × Sopa wantán'], $opciones);
        $this->assertSame('Menú arroz chaufa (1 × Sopa de pollo, 1 × Sopa wantán)',
            DescripcionItems::describir('Menú arroz chaufa', DescripcionItems::opciones([$item->id_detalle_pedido])[$item->id_detalle_pedido]));
    }

    public function test_la_sopa_se_puede_cambiar_antes_de_enviar_y_no_despues(): void
    {
        $pedido = $this->pedido();
        [$item] = $this->agregar([$this->menu('m3')], 1, $pedido);
        $servicio = app(GestionarPedidoService::class);

        $servicio->actualizarItem($item->id_detalle_pedido, ['selecciones' => [['id_opcion' => 12, 'cantidad' => 1]]], 10, 1);
        $this->assertSame(['Sopa de kion'], $item->modificadores()->pluck('nombre_opcion')->all());

        // Subir la cantidad lleva la sopa a todas las unidades.
        $servicio->actualizarItem($item->id_detalle_pedido, ['cantidad' => 2], 10, 1);
        $this->assertSame('2.000', $item->modificadores()->sole()->cantidad);
        $this->assertSame('30.00', $item->refresh()->subtotal);

        app(EnviarComandaService::class)->enviar($pedido->id_pedido, 'envio-m3', 10, 1);
        $this->expectExceptionMessage('Solo se pueden editar ítems pendientes');
        $servicio->actualizarItem($item->id_detalle_pedido, ['selecciones' => [['id_opcion' => 11, 'cantidad' => 2]]], 10, 1);
    }

    public function test_la_receta_de_la_sopa_se_descuenta_junto_con_la_del_menu_una_sola_vez(): void
    {
        $pedido = $this->pedido();
        $this->agregar([$this->menu('m4', 2, [11 => 1, 13 => 1])], 1, $pedido);
        $envios = app(EnviarComandaService::class);

        $envios->enviar($pedido->id_pedido, 'envio-m4', 10, 1);
        $envios->enviar($pedido->id_pedido, 'envio-m4', 10, 1);

        // Pollo: 2 menús × 0.2 + 1 sopa de pollo × 0.1 = 0.5 en un solo consumo.
        $this->assertEqualsWithDelta(9.5, $this->saldo(30), 0.0001);
        // Masa: 1 sopa wantán × 3.
        $this->assertEqualsWithDelta(47.0, $this->saldo(31), 0.0001);
        $this->assertSame(2, DB::table('restaurante.consumos_insumos')->count());
        $this->assertSame(2, Kardex::query()->where('tipo_operacion', 'CONSUMO_RECETA')->count());
    }

    // ---------- Combinaciones ----------

    public function test_acompanamiento_incluido_no_cambia_el_precio(): void
    {
        $this->assertSame(18.0, $this->armar($this->combinacion('c', 1, [22 => 1]))['detalle']['precio_unitario']);
    }

    public function test_acompanamiento_con_recargo_suma_al_total(): void
    {
        $armado = $this->armar($this->combinacion('c', 1, [21 => 1, 22 => 1]));

        $this->assertSame(21.0, $armado['detalle']['precio_unitario']);
        $this->assertSame(3.0, $armado['modificadores'][0]['precio_adicional']);
    }

    public function test_el_recargo_depende_de_la_sucursal(): void
    {
        app(OpcionesSucursalService::class)->fijar(21, 2, 4, true);

        $this->assertSame(21.0, $this->armar($this->combinacion('c', 1, [21 => 1]), 1)['detalle']['precio_unitario']);
        $this->assertSame(22.0, $this->armar($this->combinacion('c', 1, [21 => 1]), 2)['detalle']['precio_unitario']);
    }

    public function test_un_acompanamiento_deshabilitado_en_la_sucursal_se_rechaza(): void
    {
        app(OpcionesSucursalService::class)->fijar(21, 1, null, false);

        $this->expectExceptionMessage('no está disponible en esta sucursal');
        $this->armar($this->combinacion('c', 1, [21 => 1]));
    }

    public function test_total_con_cantidad_y_recargo_en_una_sola_unidad(): void
    {
        [$item] = $this->agregar([$this->combinacion('c2', 2, [21 => 1])]);

        // 2 × S/ 18 + un wantán frito S/ 3 = S/ 39.
        $this->assertSame('19.5000', $item->precio_unitario);
        $this->assertSame('39.00', $item->subtotal);
    }

    public function test_el_navegador_no_puede_fijar_precios_ni_opciones_ajenas(): void
    {
        $armado = $this->armar([...$this->combinacion('c', 1, [21 => 1]), 'precio_unitario' => 1, 'precio_adicional' => 0]);
        $this->assertSame(21.0, $armado['detalle']['precio_unitario']);

        $this->expectExceptionMessage('no corresponde');
        $this->armar($this->combinacion('c', 1, [13 => 1]));
    }

    public function test_no_se_puede_elegir_mas_de_lo_permitido_ni_repetir_por_unidad(): void
    {
        try {
            $this->armar($this->combinacion('c', 1, [21 => 2]));
            $this->fail('Se aceptó una opción más veces que la cantidad del plato.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('más veces', $e->getMessage());
        }

        $this->expectExceptionMessage('como máximo 1');
        $this->armar($this->menu('m', 1, [11 => 1, 12 => 1]));
    }
}
