<?php

namespace Tests\Feature\Restaurante;

use App\Jobs\EmitirComprobanteSunatJob;
use App\Models\Kardex;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\Pedido;
use App\Models\User;
use App\Models\Venta;
use App\Services\Caja\GestionarTurnoCajaService;
use App\Services\Restaurante\VentaPosService;
use DomainException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
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

    public function test_venta_de_mostrador_en_un_paso_envia_a_cocina_descuenta_bebidas_y_cierra(): void
    {
        $resultado = $this->pos->cobrar(null, [
            'clave' => 'mostrador0000000001', 'tipo' => Pedido::PARA_LLEVAR, 'nombre_contacto' => 'Ana',
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
        $datos = ['clave' => 'reintento000000001', 'tipo' => Pedido::PARA_LLEVAR, 'items' => $this->lineas(), ...$this->pago(40)];

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
        try {
            $this->pos->cobrar(null, ['clave' => 'nocuadra00000000001', 'tipo' => Pedido::PARA_LLEVAR, 'items' => $this->lineas(), ...$this->pago(39)], 10, 1, false);
            $this->fail('Se aceptó un pago que no coincide con el total.');
        } catch (DomainException) {
        }

        $this->assertSame(0, Pedido::query()->count());
        $this->assertSame(0, Comanda::query()->count());
        $this->assertSame(0, Venta::query()->count());
        $this->assertSame(20.0, $this->stock(2));
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

        // Una mesa ocupada abre su cuenta desde el salón.
        $this->actingAs($admin)->post('/pos/enviar', ['clave' => 'http00000000000002', 'tipo' => 'MESA', 'id_mesa' => 1, 'items' => [$this->lomo('lomohttpaaaaaaaaa1')]]);
        $cuenta = Pedido::query()->where('id_mesa', 1)->where('estado', 'ABIERTO')->value('id_pedido');
        $this->actingAs($admin)->get('/pos?mesa=1')->assertRedirect(route('restaurante.pos', ['cuenta' => $cuenta]));
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
