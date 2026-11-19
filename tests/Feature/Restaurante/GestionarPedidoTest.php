<?php

namespace Tests\Feature\Restaurante;

use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Services\Restaurante\GestionarPedidoService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GestionarPedidoTest extends TestCase
{
    use PreparaBaseRestaurante;

    private GestionarPedidoService $pedidos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararBaseRestaurante();
        $this->pedidos = app(GestionarPedidoService::class);
    }

    private function abrirMesa(int $mesaId = 1, string $clave = 'abrir-1', int $sucursalId = 1): Pedido
    {
        return $this->pedidos->abrir(['tipo' => Pedido::MESA, 'id_mesa' => $mesaId, 'comensales' => 2, 'clave_idempotencia' => $clave], 10, $sucursalId);
    }

    public function test_abrir_mesa_la_ocupa_numera_el_pedido_y_registra_el_evento(): void
    {
        $pedido = $this->abrirMesa();

        $this->assertSame(Pedido::ABIERTO, $pedido->estado);
        $this->assertSame(1, $pedido->numero);
        $this->assertSame('OCUPADA', $this->estadoMesa(1));
        $this->assertSame(['PEDIDO_ABIERTO'], DB::table('restaurante.pedido_eventos')->pluck('tipo')->all());

        $segundo = $this->pedidos->abrir(['tipo' => Pedido::PARA_LLEVAR, 'nombre_contacto' => 'Ana', 'clave_idempotencia' => 'llevar-1'], 10, 1);
        $this->assertSame(2, $segundo->numero);
        $this->assertNull($segundo->id_mesa);
    }

    public function test_reintentar_la_apertura_con_la_misma_clave_devuelve_el_mismo_pedido(): void
    {
        $primero = $this->abrirMesa();
        $reintento = $this->abrirMesa();

        $this->assertSame($primero->id_pedido, $reintento->id_pedido);
        $this->assertSame(1, Pedido::query()->count());
    }

    public function test_no_se_abre_una_segunda_cuenta_en_una_mesa_ocupada(): void
    {
        $this->abrirMesa();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no está disponible');
        $this->abrirMesa(1, 'otra-clave');
    }

    public function test_el_indice_parcial_impide_dos_cuentas_abiertas_aunque_la_mesa_figure_libre(): void
    {
        $this->abrirMesa();
        // Simula una carrera: otro proceso dejó la mesa como disponible sin cerrar la cuenta.
        DB::table('restaurante.mesas')->where('id_mesa', 1)->update(['estado' => 'DISPONIBLE']);

        $this->expectException(DomainException::class);
        $this->abrirMesa(1, 'otra-clave');
    }

    public function test_no_se_puede_abrir_una_mesa_de_otra_sucursal_ni_una_fuera_de_servicio(): void
    {
        try {
            $this->abrirMesa(3, 'cruce', 1);
            $this->fail('Se abrió una mesa de otra sucursal.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('no existe en esta sucursal', $e->getMessage());
        }

        DB::table('restaurante.mesas')->where('id_mesa', 2)->update(['estado' => 'FUERA_SERVICIO']);
        $this->expectException(DomainException::class);
        $this->abrirMesa(2, 'fuera');
    }

    public function test_delivery_exige_direccion(): void
    {
        $this->expectExceptionMessage('dirección de entrega');
        $this->pedidos->abrir(['tipo' => Pedido::DELIVERY, 'clave_idempotencia' => 'd-1'], 10, 1);
    }

    public function test_agregar_items_calcula_precios_en_el_servidor_con_modificadores(): void
    {
        $pedido = $this->abrirMesa();

        [$lomo, $gaseosa] = $this->pedidos->agregarItems($pedido->id_pedido, [
            ['clave' => 'l-1', 'id_precio' => 1, 'cantidad' => 2, 'opciones' => [1, 3, 4], 'observacion' => '  sin cebolla ', 'precio_unitario' => 0.01],
            $this->gaseosa('g-1', 3),
        ], 10, 1);

        // 30 + 2.50 + 3.00 = 35.50 × 2; el precio enviado por el navegador se ignora.
        $this->assertSame('35.5000', $lomo->precio_unitario);
        $this->assertSame('5.5000', $lomo->precio_modificadores);
        $this->assertSame('71.00', $lomo->subtotal);
        $this->assertSame('sin cebolla', $lomo->observacion);
        $this->assertSame(PedidoDetalle::PENDIENTE, $lomo->estado);
        $this->assertSame(['Jugoso', 'Huevo frito', 'Plátano'], $lomo->modificadores()->pluck('nombre_opcion')->all());
        $this->assertSame('15.00', $gaseosa->subtotal);
        $this->assertSame(86.0, $this->pedidos->total($pedido));
    }

    public function test_reenviar_los_mismos_items_no_los_duplica(): void
    {
        $pedido = $this->abrirMesa();
        $items = [$this->lomo('l-1'), $this->gaseosa('g-1')];

        $this->pedidos->agregarItems($pedido->id_pedido, $items, 10, 1);
        $this->pedidos->agregarItems($pedido->id_pedido, $items, 10, 1);

        $this->assertSame(2, PedidoDetalle::query()->count());
        $this->assertSame(2, DB::table('restaurante.pedido_eventos')->where('tipo', 'ITEM_AGREGADO')->count());
    }

    public function test_reglas_de_modificadores(): void
    {
        $pedido = $this->abrirMesa();
        $casos = [
            'grupo obligatorio sin elegir' => [$this->lomo('a', 1, []), 'Elige al menos 1'],
            'dos opciones donde solo cabe una' => [$this->lomo('b', 1, [1, 2]), 'como máximo 1'],
            'opción de otro producto' => [$this->lomo('c', 1, [1, 5]), 'no corresponde'],
            'opción repetida' => [$this->lomo('d', 1, [1, 1]), 'más de una vez'],
        ];

        foreach ($casos as $caso => [$item, $mensaje]) {
            try {
                $this->pedidos->agregarItems($pedido->id_pedido, [$item], 10, 1);
                $this->fail("Se aceptó: {$caso}");
            } catch (DomainException $e) {
                $this->assertStringContainsString($mensaje, $e->getMessage(), $caso);
            }
        }
        $this->assertSame(0, PedidoDetalle::query()->count());
    }

    public function test_no_se_venden_insumos_ni_productos_agotados_en_la_sucursal(): void
    {
        $pedido = $this->abrirMesa();

        try {
            $this->pedidos->agregarItems($pedido->id_pedido, [['clave' => 'p', 'id_precio' => 3, 'cantidad' => 1]], 10, 1);
            $this->fail('Se vendió un insumo.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('insumo', $e->getMessage());
        }

        DB::table('restaurante.productos_agotados')->insert(['id_sucursal' => 1, 'id_producto' => 2, 'id_usuario' => 10, 'marcado_en' => now()]);
        $this->expectExceptionMessage('agotado');
        $this->pedidos->agregarItems($pedido->id_pedido, [$this->gaseosa('g')], 10, 1);
    }

    public function test_un_agotado_en_otra_sucursal_no_afecta(): void
    {
        DB::table('restaurante.productos_agotados')->insert(['id_sucursal' => 2, 'id_producto' => 2, 'id_usuario' => 10, 'marcado_en' => now()]);
        $pedido = $this->abrirMesa();

        $this->pedidos->agregarItems($pedido->id_pedido, [$this->gaseosa('g')], 10, 1);

        $this->assertSame(1, PedidoDetalle::query()->count());
    }

    public function test_editar_item_pendiente_recalcula_y_deja_historial(): void
    {
        $pedido = $this->abrirMesa();
        [$gaseosa] = $this->pedidos->agregarItems($pedido->id_pedido, [$this->gaseosa('g')], 10, 1);

        $editado = $this->pedidos->actualizarItem($gaseosa->id_detalle_pedido, ['cantidad' => 4, 'observacion' => 'helada'], 11, 1);

        $this->assertSame('20.00', $editado->subtotal);
        $evento = DB::table('restaurante.pedido_eventos')->where('tipo', 'ITEM_MODIFICADO')->first();
        $this->assertSame(11, (int) $evento->id_usuario);
        $this->assertSame(1.0, (float) json_decode($evento->datos, true)['antes']['cantidad']);
    }

    public function test_un_item_enviado_a_cocina_no_se_edita_y_su_anulacion_exige_permiso_y_motivo(): void
    {
        $pedido = $this->abrirMesa();
        [$lomo] = $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo('l')], 10, 1);
        $lomo->update(['estado' => PedidoDetalle::ENVIADO]);

        $intentos = [
            fn () => $this->pedidos->actualizarItem($lomo->id_detalle_pedido, ['cantidad' => 2], 10, 1),
            fn () => $this->pedidos->anularItem($lomo->id_detalle_pedido, 'cliente cambió', false, 10, 1),
            fn () => $this->pedidos->anularItem($lomo->id_detalle_pedido, '  ', true, 10, 1),
        ];
        foreach ($intentos as $intento) {
            try {
                $intento();
                $this->fail('Se permitió modificar un ítem enviado.');
            } catch (DomainException) {
            }
        }

        $anulado = $this->pedidos->anularItem($lomo->id_detalle_pedido, 'Cliente cambió de plato', true, 20, 1);
        $this->assertSame(PedidoDetalle::ANULADO, $anulado->estado);
        $this->assertSame(20, (int) $anulado->id_usuario_anulacion);
        $this->assertSame(0.0, $this->pedidos->total($pedido));
    }

    public function test_un_item_pendiente_se_anula_sin_permiso_y_repetir_la_anulacion_no_falla(): void
    {
        $pedido = $this->abrirMesa();
        [$gaseosa] = $this->pedidos->agregarItems($pedido->id_pedido, [$this->gaseosa('g')], 10, 1);

        $this->pedidos->anularItem($gaseosa->id_detalle_pedido, null, false, 10, 1);
        $otraVez = $this->pedidos->anularItem($gaseosa->id_detalle_pedido, null, false, 10, 1);

        $this->assertSame(PedidoDetalle::ANULADO, $otraVez->estado);
        $this->assertSame(1, DB::table('restaurante.pedido_eventos')->where('tipo', 'ITEM_ANULADO')->count());
    }

    public function test_un_item_con_cobro_no_se_anula_ni_con_permiso(): void
    {
        $pedido = $this->abrirMesa();
        [$gaseosa] = $this->pedidos->agregarItems($pedido->id_pedido, [$this->gaseosa('g')], 10, 1);
        $gaseosa->update(['cantidad_cobrada' => 1]);

        $this->expectExceptionMessage('cobro registrado');
        $this->pedidos->anularItem($gaseosa->id_detalle_pedido, 'error', true, 10, 1);
    }

    public function test_anular_pedido_anula_sus_items_y_libera_la_mesa(): void
    {
        $pedido = $this->abrirMesa();
        $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo('l'), $this->gaseosa('g')], 10, 1);

        $anulado = $this->pedidos->anularPedido($pedido->id_pedido, 'Cliente se retiró', false, 10, 1);

        $this->assertSame(Pedido::ANULADO, $anulado->estado);
        $this->assertSame('DISPONIBLE', $this->estadoMesa(1));
        $this->assertSame(2, PedidoDetalle::query()->where('estado', 'ANULADO')->count());

        // La mesa vuelve a admitir una cuenta nueva.
        $this->assertSame(Pedido::ABIERTO, $this->abrirMesa(1, 'nueva')->estado);
    }

    public function test_anular_pedido_exige_motivo_y_permiso_si_hay_items_en_cocina(): void
    {
        $pedido = $this->abrirMesa();
        [$lomo] = $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo('l')], 10, 1);

        try {
            $this->pedidos->anularPedido($pedido->id_pedido, '', true, 10, 1);
            $this->fail('Se anuló sin motivo.');
        } catch (DomainException) {
        }

        $lomo->update(['estado' => PedidoDetalle::EN_PREPARACION]);
        try {
            $this->pedidos->anularPedido($pedido->id_pedido, 'Se fue', false, 10, 1);
            $this->fail('Se anuló un pedido con ítems en cocina sin permiso.');
        } catch (DomainException) {
        }

        $this->assertSame(Pedido::ABIERTO, $pedido->refresh()->estado);
        $this->assertSame('OCUPADA', $this->estadoMesa(1));
    }

    public function test_un_pedido_de_otra_sucursal_no_se_puede_operar(): void
    {
        $pedido = $this->abrirMesa(3, 'norte', 2);

        $this->expectExceptionMessage('no existe en esta sucursal');
        $this->pedidos->agregarItems($pedido->id_pedido, [$this->gaseosa('g')], 10, 1);
    }

    public function test_un_pedido_cerrado_no_admite_items(): void
    {
        $pedido = $this->abrirMesa();
        $this->pedidos->anularPedido($pedido->id_pedido, 'Prueba', false, 10, 1);

        $this->expectExceptionMessage('ya no está abierto');
        $this->pedidos->agregarItems($pedido->id_pedido, [$this->gaseosa('g')], 10, 1);
    }
}
