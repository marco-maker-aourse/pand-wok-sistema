<?php

namespace Tests\Feature\Restaurante;

use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Models\User;
use App\Models\Venta;
use App\Services\Caja\GestionarTurnoCajaService;
use App\Services\Restaurante\OpcionesSucursalService;
use App\Services\Restaurante\PresentarRestauranteService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** El POS recibe y envía opciones por sucursal; el servidor decide precios y disponibilidad. */
class PosOpcionesTest extends TestCase
{
    use PreparaCartaEstructurada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCartaEstructurada();
        Bus::fake();
        app(GestionarTurnoCajaService::class)->abrir(10, 1, 0);
    }

    private function carta(int $sucursal = 1): array
    {
        return app(PresentarRestauranteService::class)->cartaPedido($sucursal);
    }

    private function opcionesDelGrupo(array $carta, int $grupo): array
    {
        return collect(collect($carta['grupos'])->firstWhere('id_grupo', $grupo)['opciones'] ?? [])->pluck('precio_adicional', 'nombre')->all();
    }

    public function test_la_carta_de_cada_sucursal_solo_ofrece_lo_que_se_puede_vender_alli(): void
    {
        $opciones = app(OpcionesSucursalService::class);
        $opciones->fijar(12, 1, null, false);                // Kion desactivada en la sucursal 1.
        $opciones->fijar(21, 2, 4, true);                    // Wantán frito +S/ 4 en la sucursal 2.
        DB::table('restaurante.productos_agotados')->insert(['id_sucursal' => 1, 'id_producto' => 14, 'id_usuario' => 10, 'marcado_en' => now()]);

        $this->assertSame(['Sopa de pollo' => 0.0, 'Sopa wantán' => 0.0], $this->opcionesDelGrupo($this->carta(1), 10));
        $this->assertCount(4, $this->opcionesDelGrupo($this->carta(2), 10));
        $this->assertSame(['Wantán frito x3' => 3.0, 'Papa frita' => 0.0], $this->opcionesDelGrupo($this->carta(1), 11));
        $this->assertSame(['Wantán frito x3' => 4.0, 'Papa frita' => 0.0], $this->opcionesDelGrupo($this->carta(2), 11));

        $menu = collect($this->carta(1)['productos'])->firstWhere('id_producto', 10);
        $this->assertSame([['id_grupo' => 10, 'seleccion_minima' => 1, 'seleccion_maxima' => 1]], $menu['reglas']);
        $this->assertFalse($menu['agotado']);
    }

    public function test_un_menu_sin_ninguna_sopa_disponible_queda_fuera_de_venta(): void
    {
        foreach ([11, 12, 13, 14] as $sopa) {
            app(OpcionesSucursalService::class)->fijar($sopa, 1, null, false);
        }

        $this->assertTrue(collect($this->carta(1)['productos'])->firstWhere('id_producto', 10)['agotado']);
        $this->assertFalse(collect($this->carta(2)['productos'])->firstWhere('id_producto', 10)['agotado']);
    }

    public function test_enviar_por_http_persiste_las_elecciones_ignora_precios_y_no_duplica(): void
    {
        $admin = User::query()->findOrFail(10);
        $datos = [
            'clave' => 'posmenu00000000001', 'tipo' => 'MESA', 'id_mesa' => 1,
            'items' => [[
                'clave' => 'menuhttpaaaaaaaaa1', 'id_precio' => 10, 'cantidad' => 2, 'precio_unitario' => 1,
                'selecciones' => [['id_opcion' => 13, 'cantidad' => 1], ['id_opcion' => 11, 'cantidad' => 1]],
            ]],
        ];

        $this->actingAs($admin)->post('/pos/enviar', $datos)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/pos/enviar', $datos)->assertSessionHasNoErrors();

        $item = PedidoDetalle::query()->sole();
        $this->assertSame('15.0000', $item->precio_unitario, 'El precio lo pone el servidor.');
        $this->assertSame(PedidoDetalle::ENVIADO, $item->estado);
        $this->assertSame(1, Pedido::query()->count());
        $this->assertSame(1, Comanda::query()->count());
        $this->assertSame([['Sopa de pollo', '1.000'], ['Sopa wantán', '1.000']],
            $item->modificadores->map(fn ($m) => [$m->nombre_opcion, $m->cantidad])->all());

        $this->actingAs($admin)->get('/pos?cuenta='.$item->id_pedido)->assertInertia(fn (Assert $page) => $page
            ->where('cuenta.detalles.0.modificadores.0.etiqueta', '1 × Sopa de pollo'));
    }

    public function test_una_opcion_desactivada_en_la_sucursal_se_rechaza_aunque_la_peticion_la_traiga(): void
    {
        app(OpcionesSucursalService::class)->fijar(13, 1, null, false);

        $this->actingAs(User::query()->findOrFail(10))->post('/pos/enviar', [
            'clave' => 'posmenu00000000002', 'tipo' => 'PARA_LLEVAR',
            'items' => [['clave' => 'menuhttpaaaaaaaaa2', 'id_precio' => 10, 'cantidad' => 1, 'selecciones' => [['id_opcion' => 13, 'cantidad' => 1]]]],
        ])->assertSessionHasErrors();

        $this->assertSame(0, PedidoDetalle::query()->count());
    }

    public function test_cobrar_un_menu_en_el_pos_registra_una_sola_linea_con_la_sopa_incluida(): void
    {
        $this->actingAs(User::query()->findOrFail(10))->post('/pos/cobrar', [
            'clave' => 'posmenu00000000003', 'tipo' => 'PARA_LLEVAR',
            'items' => [
                ['clave' => 'menuhttpaaaaaaaaa3', 'id_precio' => 10, 'cantidad' => 1, 'selecciones' => [['id_opcion' => 11, 'cantidad' => 1]]],
                ['clave' => 'combhttpaaaaaaaaa3', 'id_precio' => 20, 'cantidad' => 1, 'selecciones' => [['id_opcion' => 21, 'cantidad' => 1]]],
            ],
            'tipo_comprobante' => 'BOLETA', 'pagos' => [['id_metodo' => 1, 'monto' => 36, 'monto_recibido' => 50]],
        ])->assertSessionHasNoErrors();

        $venta = Venta::query()->sole();
        $this->assertSame('36.00', number_format((float) $venta->total_venta, 2), 'Menú S/ 15 + combinación S/ 18 + wantán frito S/ 3.');
        $this->assertSame(2, DB::table('operaciones.detalle_ventas')->count());
        $this->assertSame(Pedido::CERRADO, Pedido::query()->sole()->estado);
    }

    public function test_las_sugerencias_salen_de_pedidos_reales_y_estan_vacias_sin_historial(): void
    {
        $sugerencias = app(\App\Services\Restaurante\SugerenciasPosService::class);
        $this->assertSame(['frecuentes' => [], 'ultimos' => [], 'acompanan' => []], $sugerencias->calcular(1, 10));

        $pedidos = app(\App\Services\Restaurante\GestionarPedidoService::class);
        $a = $pedidos->abrir(['tipo' => Pedido::MESA, 'id_mesa' => 1, 'clave_idempotencia' => 'sa'], 10, 1);
        $pedidos->agregarItems($a->id_pedido, [$this->menu('s1', 3, [13 => 3]), $this->gaseosa('s2')], 10, 1);
        $b = $pedidos->abrir(['tipo' => Pedido::MESA, 'id_mesa' => 2, 'clave_idempotencia' => 'sb'], 30, 1);
        $pedidos->agregarItems($b->id_pedido, [$this->combinacion('s3'), $this->gaseosa('s4')], 30, 1);

        $resultado = $sugerencias->calcular(1, 30);
        $this->assertSame([10, 2, 20], $resultado['frecuentes'], 'Por unidades pedidas: 3 menús, 2 gaseosas, 1 combinación.');
        $this->assertSame([2, 20], $resultado['ultimos']);
        $this->assertSame([10, 20], $resultado['acompanan'][2]);
        $this->assertSame([], $sugerencias->calcular(2, 10)['frecuentes'], 'Cada sucursal con su propio historial.');
    }
}
