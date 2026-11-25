<?php

namespace Tests\Feature\Restaurante;

use App\Jobs\EmitirComprobanteSunatJob;
use App\Models\Kardex;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\Pedido;
use App\Models\User;
use App\Models\Venta;
use App\Services\Caja\GestionarTurnoCajaService;
use App\Services\Restaurante\RecetasService;
use App\Services\Restaurante\VentaPosService;
use DomainException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Punto de venta único: mostrador, delivery y mesa sobre las mismas cuentas, comandas y cobros. */
class PosRestauranteTest extends TestCase
{
    use PreparaOperacionRestaurante;

    private VentaPosService $pos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararOperacionRestaurante();
        app(GestionarTurnoCajaService::class)->abrir(10, 1, 0);
        $this->pos = app(VentaPosService::class);
    }

    private function lineas(string $sufijo = ''): array
    {
        // Lomo (S/ 30, plato de cocina) + 2 gaseosas (S/ 5, venta directa con stock) = S/ 40.
        return [$this->lomo("lomo{$sufijo}aaaaaaaaaaaa"), $this->gaseosa("gase{$sufijo}aaaaaaaaaaaa", 2)];
    }

    private function pago(float $monto, string $comprobante = 'BOLETA'): array
    {
        return ['tipo_comprobante' => $comprobante, 'pagos' => [['id_metodo' => 1, 'monto' => $monto, 'monto_recibido' => 50]]];
    }

    private function stock(int $producto): float
    {
        return (float) Kardex::query()->where('id_producto', $producto)->where('id_sucursal', 1)->latest('id_kardex')->value('stock_actual');
    }

    public function test_identificar_cliente_en_el_pos_respeta_el_catalogo_y_permite_nombre_manual(): void
    {
        $this->actingAs(User::findOrFail(10));
        config()->set('services.api_peru.dni_url', 'https://proveedor.test/dni');
        config()->set('services.api_peru.token', 'token-de-prueba');
        DB::table('catalogos.tipos_documento')->insert(['id_tipo_documento' => 3, 'codigo_sunat' => '1']);
        Http::fake(['proveedor.test/dni/12345678' => Http::response([], 503), 'proveedor.test/dni/87654321' => Http::response([], 404)]);

        $this->postJson('/pos/clientes', ['documento' => '12345678'])->assertUnprocessable()->assertJsonPath('requiere_nombre', true);
        $datos = ['documento' => '12345678', 'nombre' => 'Persona de prueba'];
        $this->postJson('/pos/clientes', $datos)->assertOk()->assertJsonPath('origen', 'manual');
        $this->postJson('/pos/clientes', $datos)->assertOk()->assertJsonPath('origen', 'local');
        $this->postJson('/pos/clientes', ['documento' => '87654321', 'nombre' => 'Persona de prueba'])
            ->assertUnprocessable()->assertJsonMissingPath('requiere_nombre');
        $this->assertDatabaseCount('catalogos.clientes', 3);
    }

    public function test_venta_de_mostrador_envia_a_cocina_si_se_solicita(): void
    {
        $resultado = $this->pos->cobrar(null, [
            'clave' => 'mostrador0000000001', 'tipo' => Pedido::PARA_LLEVAR, 'nombre_contacto' => 'Ana',
            'enviar_a_cocina' => true,
            'items' => $this->lineas(), ...$this->pago(40),
        ], 10, 1, false);

        $this->assertTrue($resultado['nueva']);
        $this->assertSame('40.00', number_format((float) $resultado['venta']->total_venta, 2));
        $this->assertSame(Pedido::CERRADO, $resultado['pedido']->estado);
        $this->assertSame(1, Comanda::query()->count(), 'El plato llega a cocina.');
        $this->assertSame(18.0, $this->stock(2), 'Las bebidas descuentan su stock al cobrar.');
        $this->assertFalse(Kardex::query()->where('id_producto', 1)->exists(), 'El plato no tiene stock propio.');
    }

    public function test_reintentar_el_mismo_cobro_no_duplica_nada(): void
    {
        $datos = ['clave' => 'reintento000000001', 'tipo' => Pedido::PARA_LLEVAR, 'items' => $this->lineas(), 'enviar_a_cocina' => true, ...$this->pago(40)];

        $primero = $this->pos->cobrar(null, $datos, 10, 1, false);
        $segundo = $this->pos->cobrar(null, $datos, 10, 1, false);

        $this->assertFalse($segundo['nueva']);
        $this->assertSame($primero['venta']->id_venta, $segundo['venta']->id_venta);
        $this->assertSame(1, Venta::query()->count());
        $this->assertSame(1, Pedido::query()->count());
        $this->assertSame(1, Comanda::query()->count());
        $this->assertSame(18.0, $this->stock(2));
    }

    public function test_un_cobro_que_no_cuadra_no_deja_nada_a_medias(): void
    {
        app(RecetasService::class)->guardar(1, 1, [['id_precio_insumo' => 3, 'cantidad' => 1]]);

        try {
            $this->pos->cobrar(null, ['clave' => 'nocuadra00000000001', 'tipo' => Pedido::PARA_LLEVAR, 'items' => $this->lineas(), ...$this->pago(39)], 10, 1, false);
            $this->fail('Se aceptó un pago que no coincide con el total.');
        } catch (DomainException) {
        }

        $this->assertSame(0, Pedido::query()->count());
        $this->assertSame(0, Comanda::query()->count());
        $this->assertSame(0, Venta::query()->count());
        $this->assertSame(20.0, $this->stock(2));
        $this->assertSame(10.0, $this->stock(3));
        $this->assertDatabaseCount('restaurante.consumos_insumos', 0);
    }

    public function test_mesa_se_envia_ahora_y_se_cobra_despues_liberando_la_mesa(): void
    {
        ['pedido' => $cuenta, 'comandas' => $comandas] = $this->pos->enviar(null, [
            'clave' => 'mesa00000000000001', 'tipo' => Pedido::MESA, 'id_mesa' => 1, 'items' => [$this->lomo('lomomesaaaaaaaaaa1')],
        ], 10, 1);

        $this->assertSame(1, $comandas);
        $this->assertSame(Pedido::ABIERTO, $cuenta->estado);
        $this->assertSame('OCUPADA', $this->estadoMesa(1));

        // Segunda ronda al cobrar: la gaseosa se agrega y se cobra todo junto.
        $resultado = $this->pos->cobrar($cuenta->id_pedido, [
            'clave' => 'mesacobro000000001', 'items' => [$this->gaseosa('gasemesaaaaaaaaaa1')], ...$this->pago(35),
        ], 10, 1, false);

        $this->assertSame('35.00', number_format((float) $resultado['venta']->total_venta, 2));
        $this->assertSame(Pedido::CERRADO, $resultado['pedido']->estado);
        $this->assertSame('DISPONIBLE', $this->estadoMesa(1));
    }

    public function test_delivery_exige_direccion_y_mesa_de_otra_sucursal_no_se_usa(): void
    {
        foreach ([
            ['clave' => 'delivery0000000001', 'tipo' => Pedido::DELIVERY, 'items' => $this->lineas()],
            ['clave' => 'otrasede0000000001', 'tipo' => Pedido::MESA, 'id_mesa' => 3, 'items' => $this->lineas('b')],
        ] as $datos) {
            try {
                $this->pos->enviar(null, $datos, 10, 1);
                $this->fail('Se aceptó una cuenta inválida.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, Pedido::query()->count());
    }

    public function test_pantalla_y_cobro_por_http(): void
    {
        Bus::fake();
        $admin = User::query()->findOrFail(10);

        $this->actingAs($admin)->get('/pos')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Restaurante/Pos')
            ->where('inicio.tipo', 'PARA_LLEVAR')
            ->where('carta.productos', fn ($productos) => collect($productos)->pluck('nombre')->contains('Lomo saltado')));

        $this->actingAs($admin)->get('/pos?mesa=1')->assertInertia(fn (Assert $page) => $page->where('inicio.mesa.codigo', 'M1')->where('inicio.tipo', 'MESA'));

        $this->actingAs($admin)->post('/pos/cobrar', [
            'clave' => 'http00000000000001', 'tipo' => 'PARA_LLEVAR',
            'items' => $this->lineas('h'), ...$this->pago(40),
        ])->assertRedirect(route('restaurante.pos'))->assertSessionHas('pos.ultima_venta.total', 40.0);

        Bus::assertDispatched(EmitirComprobanteSunatJob::class);

        // Una cuenta guardada está disponible desde el salón y puede enviarse después.
        $datos = ['clave' => 'http00000000000002', 'tipo' => 'MESA', 'id_mesa' => 1, 'items' => [$this->gaseosa('gasehttpaaaaaaaaa1')]];
        $this->post('/pos/guardar', $datos)->assertSessionHasNoErrors();
        $cuenta = Pedido::query()->where('id_mesa', 1)->where('estado', 'ABIERTO')->sole();
        $ruta = route('restaurante.pos', ['cuenta' => $cuenta->getKey()]);
        $this->post('/pos/guardar', $datos)->assertRedirect($ruta)->assertSessionHasNoErrors();
        $this->get('/pos?mesa=1')->assertRedirect($ruta);
        $this->get($ruta)->assertInertia(fn (Assert $page) => $page->component('Restaurante/Pos')
            ->has('cuenta.detalles', 1)->where('cuenta.detalles.0.estado', 'PENDIENTE'));
        $this->assertDatabaseCount('restaurante.comandas', 0);

        $this->post('/pos/enviar', ['clave' => 'http00000000000003', 'id_pedido' => $cuenta->getKey()])
            ->assertRedirect($ruta)->assertSessionHasNoErrors();
        $this->assertSame('ENTREGADO', $cuenta->detalles()->sole()->estado);
    }

    public function test_el_pos_avisa_si_la_comanda_no_se_imprimio(): void
    {
        $this->actingAs(User::query()->findOrFail(10))->post('/pos/enviar', [
            'clave' => 'aviso0000000000001', 'tipo' => 'PARA_LLEVAR', 'items' => [$this->lomo('lomoavisoaaaaaaaaa')],
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Enviado a cocina (1 comanda).')
            ->assertSessionHas('warning', fn (string $aviso) => str_contains($aviso, 'Cocina general') && str_contains($aviso, 'Estaciones de cocina'));
    }

    public function test_comprobantes_se_filtran_por_tipo_en_todo_el_historial(): void
    {
        Bus::fake();
        $admin = User::query()->findOrFail(10);
        $this->actingAs($admin)->post('/pos/cobrar', ['clave' => 'tipo00000000000001', 'tipo' => 'PARA_LLEVAR', 'items' => $this->lineas('b'), ...$this->pago(40)])->assertSessionHasNoErrors();
        $this->post('/pos/cobrar', ['clave' => 'tipo00000000000002', 'tipo' => 'PARA_LLEVAR', 'items' => $this->lineas('n'), ...$this->pago(40, 'NOTA_VENTA')])->assertSessionHasNoErrors();

        $this->get('/ventas?tipo=NOTA_VENTA')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Ventas/Index')
            ->where('tipoComprobante', 'NOTA_VENTA')
            ->has('ventas.data', 1)
            ->where('ventas.data.0.tipo_comprobante', 'NOTA_VENTA')
            ->where('ventas.data.0.estado_sunat', Venta::SIN_ENVIO_SUNAT));

        $this->get('/ventas?tipo=BOLETA')->assertInertia(fn (Assert $page) => $page->has('ventas.data', 1)->where('ventas.data.0.tipo_comprobante', 'BOLETA'));
        // Un tipo desconocido no filtra.
        $this->get('/ventas?tipo=OTRO')->assertInertia(fn (Assert $page) => $page->has('ventas.data', 2)->where('tipoComprobante', 'TODOS'));
    }

    public function test_el_resumen_de_caja_separa_notas_de_venta_de_comprobantes_electronicos(): void
    {
        Bus::fake();
        $this->actingAs(User::query()->findOrFail(10));
        $this->post('/pos/cobrar', ['clave' => 'caja00000000000001', 'tipo' => 'PARA_LLEVAR', 'items' => $this->lineas('b'), ...$this->pago(40)])->assertSessionHasNoErrors();
        $this->post('/pos/cobrar', ['clave' => 'caja00000000000002', 'tipo' => 'PARA_LLEVAR', 'items' => $this->lineas('n'), ...$this->pago(40, 'NOTA_VENTA')])->assertSessionHasNoErrors();
        $this->post('/pos/cobrar', ['clave' => 'caja00000000000003', 'tipo' => 'PARA_LLEVAR', 'items' => $this->lineas('m'), ...$this->pago(40, 'NOTA_VENTA')])->assertSessionHasNoErrors();

        $turno = \App\Models\TurnoCaja::query()->where('estado', 'ABIERTO')->sole();
        $resumen = app(GestionarTurnoCajaService::class)->resumen($turno);

        $this->assertSame(['cantidad' => 1, 'total' => 40.0], $resumen['ventas_por_comprobante']['BOLETA']);
        $this->assertSame(['cantidad' => 2, 'total' => 80.0], $resumen['ventas_por_comprobante']['NOTA_VENTA']);
        $this->assertArrayNotHasKey('FACTURA', $resumen['ventas_por_comprobante']);
        // Las notas de venta son dinero real: entran a la gaveta igual que las boletas.
        $this->assertSame(120.0, $resumen['ventas_efectivo']);
    }

    public function test_un_cajero_puede_emitir_factura_sin_enviar_comandas(): void
    {
        Bus::fake();
        DB::table('seguridad.permisos')->insert(['id_permiso' => 50, 'codigo' => 'pedidos.registrar']);
        DB::table('seguridad.rol_permisos')->insert(['id_rol' => 2, 'id_permiso' => 50]);
        app(GestionarTurnoCajaService::class)->abrir(20, 1, 0);
        app(RecetasService::class)->guardar(1, 1, [['id_precio_insumo' => 3, 'cantidad' => 1]]);
        $datos = ['clave' => 'facturamanual00001', 'tipo' => Pedido::PARA_LLEVAR, 'items' => $this->lineas(),
            'id_cliente' => 2, ...$this->pago(40, 'FACTURA')];

        $this->actingAs(User::findOrFail(20))->post('/pos/cobrar', [...$datos, 'enviar_a_cocina' => true])->assertForbidden();
        $this->post('/pos/cobrar', $datos)->assertRedirect(route('restaurante.pos'))->assertSessionHasNoErrors();
        $this->post('/pos/cobrar', $datos)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('operaciones.ventas', ['id_tipo_comprobante' => 2, 'id_cliente' => 2, 'total_venta' => 40]);
        $this->assertDatabaseCount('operaciones.ventas', 1);
        $this->assertDatabaseCount('restaurante.comandas', 0);
        $this->assertDatabaseCount('operaciones.trabajos_impresion', 0);
        $this->assertDatabaseCount('restaurante.consumos_insumos', 1);
        $this->assertSame(9.0, $this->stock(3));
        $this->assertSame(18.0, $this->stock(2));
        Bus::assertDispatchedAfterResponse(EmitirComprobanteSunatJob::class);
    }

    public function test_el_mozo_no_puede_cobrar(): void
    {
        $mozo = User::query()->findOrFail(30);
        DB::table('seguridad.permisos')->insert([['id_permiso' => 50, 'codigo' => 'pedidos.registrar']]);
        DB::table('seguridad.rol_permisos')->insert([['id_rol' => 5, 'id_permiso' => 50]]);

        $this->actingAs($mozo)->post('/pos/cobrar', ['clave' => 'mozo00000000000001', 'tipo' => 'PARA_LLEVAR', 'items' => $this->lineas('m'), ...$this->pago(40)])
            ->assertForbidden();
        $this->assertSame(0, Venta::query()->count());
    }
}
