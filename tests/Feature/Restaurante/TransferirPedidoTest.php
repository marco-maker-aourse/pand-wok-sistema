<?php

namespace Tests\Feature\Restaurante;

use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Services\Restaurante\GestionarPedidoService;
use App\Services\Restaurante\TransferirPedidoService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransferirPedidoTest extends TestCase
{
    use PreparaBaseRestaurante;

    private GestionarPedidoService $pedidos;
    private TransferirPedidoService $transferir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararBaseRestaurante();
        $this->pedidos = app(GestionarPedidoService::class);
        $this->transferir = app(TransferirPedidoService::class);
    }

    private function abrir(int $mesaId, string $clave): Pedido
    {
        return $this->pedidos->abrir(['tipo' => Pedido::MESA, 'id_mesa' => $mesaId, 'clave_idempotencia' => $clave], 10, 1);
    }

    public function test_cambiar_de_mesa_libera_la_origen_y_ocupa_la_destino(): void
    {
        $pedido = $this->abrir(1, 'a');

        $movido = $this->transferir->cambiarMesa($pedido->id_pedido, 2, 10, 1);

        $this->assertSame(2, (int) $movido->id_mesa);
        $this->assertSame('DISPONIBLE', $this->estadoMesa(1));
        $this->assertSame('OCUPADA', $this->estadoMesa(2));
        $evento = json_decode(DB::table('restaurante.pedido_eventos')->where('tipo', 'MESA_TRANSFERIDA')->value('datos'), true);
        $this->assertSame(['mesa_origen' => 'M1', 'mesa_destino' => 'M2'], $evento);
    }

    public function test_no_se_cambia_a_una_mesa_ocupada_ni_de_otra_sucursal(): void
    {
        $pedido = $this->abrir(1, 'a');
        $this->abrir(2, 'b');

        foreach ([2 => 'no está disponible', 3 => 'no existe en esta sucursal'] as $mesa => $mensaje) {
            try {
                $this->transferir->cambiarMesa($pedido->id_pedido, $mesa, 10, 1);
                $this->fail("Se transfirió a la mesa {$mesa}.");
            } catch (DomainException $e) {
                $this->assertStringContainsString($mensaje, $e->getMessage());
            }
        }
        $this->assertSame(1, (int) $pedido->refresh()->id_mesa);
    }

    public function test_unir_cuentas_mueve_los_items_vigentes_y_cierra_el_origen(): void
    {
        $origen = $this->abrir(1, 'a');
        $destino = $this->abrir(2, 'b');
        [, $anulada] = $this->pedidos->agregarItems($origen->id_pedido, [$this->lomo('l'), $this->gaseosa('g')], 10, 1);
        $this->pedidos->anularItem($anulada->id_detalle_pedido, null, false, 10, 1);
        $this->pedidos->agregarItems($destino->id_pedido, [$this->gaseosa('g2', 2)], 10, 1);

        $this->transferir->unir($origen->id_pedido, $destino->id_pedido, 10, 1);

        $origen->refresh();
        $this->assertSame(Pedido::UNIDO, $origen->estado);
        $this->assertSame($destino->id_pedido, (int) $origen->id_pedido_destino);
        $this->assertSame('DISPONIBLE', $this->estadoMesa(1));
        $this->assertSame('OCUPADA', $this->estadoMesa(2));
        // 30 (lomo) + 10 (2 gaseosas); la gaseosa anulada queda como historial del origen.
        $this->assertSame(40.0, $this->pedidos->total($destino));
        $this->assertSame(1, PedidoDetalle::query()->where('id_pedido', $origen->id_pedido)->count());
    }

    public function test_mover_items_separa_la_cuenta(): void
    {
        $origen = $this->abrir(1, 'a');
        $destino = $this->pedidos->abrir(['tipo' => Pedido::PARA_LLEVAR, 'clave_idempotencia' => 'llevar'], 10, 1);
        [$lomo, $gaseosa] = $this->pedidos->agregarItems($origen->id_pedido, [$this->lomo('l'), $this->gaseosa('g')], 10, 1);

        $this->transferir->moverItems($origen->id_pedido, $destino->id_pedido, [$gaseosa->id_detalle_pedido], 10, 1);

        $this->assertSame(30.0, $this->pedidos->total($origen));
        $this->assertSame(5.0, $this->pedidos->total($destino));
        $this->assertSame(Pedido::ABIERTO, $origen->refresh()->estado);
        $this->assertSame(['ITEMS_RECIBIDOS'], DB::table('restaurante.pedido_eventos')->where('id_pedido', $destino->id_pedido)->where('tipo', 'like', 'ITEMS%')->pluck('tipo')->all());
    }

    public function test_no_se_mueven_items_ajenos_anulados_ni_cobrados(): void
    {
        $origen = $this->abrir(1, 'a');
        $destino = $this->abrir(2, 'b');
        [$lomo, $gaseosa] = $this->pedidos->agregarItems($origen->id_pedido, [$this->lomo('l'), $this->gaseosa('g')], 10, 1);
        [$ajeno] = $this->pedidos->agregarItems($destino->id_pedido, [$this->gaseosa('x')], 10, 1);
        $this->pedidos->anularItem($gaseosa->id_detalle_pedido, null, false, 10, 1);

        foreach ([[$ajeno->id_detalle_pedido], [$gaseosa->id_detalle_pedido]] as $ids) {
            try {
                $this->transferir->moverItems($origen->id_pedido, $destino->id_pedido, $ids, 10, 1);
                $this->fail('Se movió un ítem que no correspondía.');
            } catch (DomainException) {
            }
        }

        $lomo->update(['cantidad_cobrada' => 1]);
        $this->expectExceptionMessage('cobro registrado');
        $this->transferir->moverItems($origen->id_pedido, $destino->id_pedido, [$lomo->id_detalle_pedido], 10, 1);
    }

    public function test_no_se_une_un_pedido_consigo_mismo(): void
    {
        $pedido = $this->abrir(1, 'a');

        $this->expectExceptionMessage('deben ser distintos');
        $this->transferir->unir($pedido->id_pedido, $pedido->id_pedido, 10, 1);
    }
}
