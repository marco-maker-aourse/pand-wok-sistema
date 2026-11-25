<?php

namespace Tests\Feature\Restaurante;

use App\Http\Controllers\Api\AgenteImpresionController;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\Pedido;
use App\Models\Restaurante\PedidoDetalle;
use App\Models\TerminalPos;
use App\Models\TrabajoImpresion;
use App\Services\Restaurante\CocinaService;
use App\Services\Restaurante\EnviarComandaService;
use App\Services\Restaurante\GestionarEstacionesService;
use App\Services\Restaurante\GestionarPedidoService;
use App\Services\Restaurante\TransferirPedidoService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CocinaTest extends TestCase
{
    use PreparaBaseRestaurante;

    private GestionarPedidoService $pedidos;
    private EnviarComandaService $envio;
    private CocinaService $cocina;
    private GestionarEstacionesService $estaciones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararBaseRestaurante();
        DB::statement("ATTACH DATABASE ':memory:' AS seguridad");
        DB::statement('CREATE TABLE seguridad.usuarios (id_usuario INTEGER PRIMARY KEY, nombres TEXT)');
        DB::table('seguridad.usuarios')->insert(['id_usuario' => 10, 'nombres' => 'Ana']);
        DB::table('catalogos.categorias')->insert(['id_categoria' => 1, 'nombre' => 'Bebidas']);
        DB::table('catalogos.productos')->where('id_producto', 2)->update(['id_categoria' => 1]);

        $this->pedidos = app(GestionarPedidoService::class);
        $this->envio = app(EnviarComandaService::class);
        $this->cocina = app(CocinaService::class);
        $this->estaciones = app(GestionarEstacionesService::class);
    }

    private function abrir(int $sucursal = 1): Pedido
    {
        return $this->pedidos->abrir(['tipo' => Pedido::MESA, 'id_mesa' => $sucursal === 1 ? 1 : 3, 'clave_idempotencia' => "abrir-{$sucursal}"], 10, $sucursal);
    }

    private function prepararImpresora(): void
    {
        DB::table('catalogos.impresoras')->insert([
            ['id_impresora' => 1, 'id_sucursal' => 1, 'nombre' => 'Caja'],
            ['id_impresora' => 2, 'id_sucursal' => 1, 'nombre' => 'Cocina'],
            ['id_impresora' => 3, 'id_sucursal' => 2, 'nombre' => 'Otra sucursal'],
        ]);
        DB::table('catalogos.terminales_pos')->insert(['id_terminal' => 1, 'id_sucursal' => 1, 'id_impresora' => 1, 'nombre' => 'Caja']);
        $this->estaciones->guardar(['nombre' => 'Caliente', 'por_defecto' => true, 'id_impresora' => 2, 'id_terminal' => 1], 1);
    }

    public function test_envia_por_categoria_y_por_estacion_predeterminada(): void
    {
        $caliente = $this->estaciones->guardar(['nombre' => 'Caliente', 'por_defecto' => true], 1);
        $bar = $this->estaciones->guardar(['nombre' => 'Bar'], 1);
        $this->estaciones->asignarCategorias($bar->id_estacion, [1], 1);
        $pedido = $this->abrir();
        [$lomo, $gaseosa] = $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo('lomo'), $this->gaseosa('gaseosa')], 10, 1);
        $resultado = $this->envio->enviar($pedido->id_pedido, 'envio', 10, 1);

        $this->assertSame(0, $resultado['directos']);
        $this->assertSame([1, 2], $resultado['comandas']->pluck('numero')->all());
        $this->assertEquals($caliente->id_estacion, $resultado['comandas']->first()->id_estacion);
        $this->assertEquals($bar->id_estacion, $resultado['comandas']->last()->id_estacion);
        $this->assertSame(PedidoDetalle::ENVIADO, $lomo->refresh()->estado);
        $this->assertSame(PedidoDetalle::ENVIADO, $gaseosa->refresh()->estado);
        $this->assertSame('Gaseosa', $this->cocina->tablero(1, $bar->id_estacion)[0]['items'][0]['nombre']);
    }

    public function test_el_envio_informa_las_comandas_que_no_se_pudieron_imprimir(): void
    {
        // Sin estaciones: la comanda va a "Cocina general", que no tiene impresora.
        $pedido = $this->abrir();
        $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo('lomo')], 10, 1);
        $this->assertSame(['Cocina general'], $this->envio->enviar($pedido->id_pedido, 'sin-estacion', 10, 1)['sin_imprimir']);

        // Estación predeterminada sin impresora.
        $this->estaciones->guardar(['nombre' => 'Caliente', 'por_defecto' => true], 1);
        $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo('lomo2')], 10, 1);
        $this->assertSame(['Caliente'], $this->envio->enviar($pedido->id_pedido, 'sin-impresora', 10, 1)['sin_imprimir']);
        $this->assertDatabaseCount('operaciones.trabajos_impresion', 0);
    }

    public function test_con_impresora_la_comanda_se_encola_sola_al_enviar(): void
    {
        $this->prepararImpresora();
        $pedido = $this->abrir();
        $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo('lomo')], 10, 1);
        $resultado = $this->envio->enviar($pedido->id_pedido, 'envio', 10, 1);

        $this->assertSame([], $resultado['sin_imprimir']);
        $this->assertSame('PENDIENTE', TrabajoImpresion::query()->sole()->estado);
        // Repetir el envío no cambia el resultado ni duplica la impresión.
        $this->assertSame([], $this->envio->enviar($pedido->id_pedido, 'envio', 10, 1)['sin_imprimir']);
        $this->assertDatabaseCount('operaciones.trabajos_impresion', 1);
    }

    public function test_repetir_un_envio_directo_no_arrastra_los_items_nuevos(): void
    {
        $pedido = $this->abrir();
        [$gaseosa] = $this->pedidos->agregarItems($pedido->id_pedido, [$this->gaseosa('gaseosa')], 10, 1);
        $primero = $this->envio->enviar($pedido->id_pedido, 'directos', 10, 1);
        [$lomo] = $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo('lomo')], 10, 1);
        $reintento = $this->envio->enviar($pedido->id_pedido, 'directos', 10, 1);

        $this->assertSame(1, $primero['directos']);
        $this->assertSame(1, $reintento['directos']);
        $this->assertSame(PedidoDetalle::ENTREGADO, $gaseosa->refresh()->estado);
        $this->assertSame(PedidoDetalle::PENDIENTE, $lomo->refresh()->estado);
        $this->assertDatabaseCount('restaurante.comandas', 0);
    }

    public function test_el_envio_y_la_reimpresion_son_idempotentes_y_el_ticket_no_tiene_precios(): void
    {
        $this->prepararImpresora();
        $pedido = $this->abrir();
        $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo('lomo')], 10, 1);
        $primero = $this->envio->enviar($pedido->id_pedido, 'envio', 10, 1)['comandas']->sole();
        $reintento = $this->envio->enviar($pedido->id_pedido, 'envio', 10, 1)['comandas']->sole();
        $this->assertSame($primero->id_comanda, $reintento->id_comanda);
        $trabajo = TrabajoImpresion::query()->sole();
        $this->assertSame('COMANDA', $trabajo->tipo);
        $this->assertSame(2, $trabajo->contenido['impresora']['id']);
        $this->assertArrayNotHasKey('precio', $trabajo->contenido['comanda']['items'][0]);
        $this->assertFalse($trabajo->contenido['impresora']['abre_cajon']);

        $trabajo->update(['estado' => 'CONFIRMADO']);
        $reimpresion = $this->cocina->reimprimir($primero->id_comanda, 'solicitud', 1);
        $this->assertSame($reimpresion->getKey(), $this->cocina->reimprimir($primero->id_comanda, 'solicitud', 1)->getKey());
        $this->assertDatabaseCount('operaciones.trabajos_impresion', 2);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Ya hay una impresión');
        $this->cocina->reimprimir($primero->id_comanda, 'otra-solicitud', 1);
    }

    public function test_transiciones_guardan_horas_y_respetan_las_anulaciones(): void
    {
        $this->prepararImpresora();
        $pedido = $this->abrir();
        [$lomo, $otro] = $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo('lomo'), $this->lomo('otro')], 10, 1);
        $comanda = $this->envio->enviar($pedido->id_pedido, 'envio', 10, 1)['comandas']->sole();
        $id = $comanda->id_comanda;

        $this->travel(1)->minutes();
        $iniciada = $this->cocina->cambiarEstado($id, Comanda::EN_PREPARACION, 1);
        $this->assertNotNull($iniciada->iniciada_en);
        $this->travel(1)->minutes();
        $this->assertEquals($iniciada->iniciada_en, $this->cocina->cambiarEstado($id, Comanda::EN_PREPARACION, 1)->iniciada_en);
        $this->pedidos->anularItem($lomo->id_detalle_pedido, 'Lo pidió el cliente', true, 10, 1);
        $lista = $this->cocina->cambiarEstado($id, Comanda::LISTA, 1);
        $this->assertNotNull($lista->lista_en);
        $this->assertSame(PedidoDetalle::ANULADO, $lomo->refresh()->estado);
        $this->assertSame(PedidoDetalle::LISTO, $otro->refresh()->estado);
        $this->assertNotNull($this->cocina->cambiarEstado($id, Comanda::ENTREGADA, 1)->entregada_en);
        $this->assertSame(Comanda::ENTREGADA, $this->cocina->tablero(1)[0]['estado']);

        $this->expectException(DomainException::class);
        $this->cocina->cambiarEstado($id, Comanda::PENDIENTE, 1);
    }

    public function test_anular_la_comanda_completa_encola_un_aviso_y_la_quita_del_tablero(): void
    {
        $this->prepararImpresora();
        $pedido = $this->abrir();
        [$lomo] = $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo('lomo')], 10, 1);
        $comanda = $this->envio->enviar($pedido->id_pedido, 'envio', 10, 1)['comandas']->sole();

        $this->pedidos->anularItem($lomo->id_detalle_pedido, 'Lo pidió el cliente', true, 10, 1);
        $this->assertSame(Comanda::ANULADA, $comanda->refresh()->estado);
        $this->assertSame([], $this->cocina->tablero(1));
        $this->assertSame('ANULACION', TrabajoImpresion::query()->latest('id_trabajo_impresion')->first()->contenido['comanda']['tipo']);
    }

    public function test_los_correlativos_y_las_claves_de_envio_se_aislan_por_sucursal(): void
    {
        foreach ([1, 2] as $sucursal) {
            $pedido = $this->abrir($sucursal);
            $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo("lomo-{$sucursal}")], 10, $sucursal);
            $comanda = $this->envio->enviar($pedido->id_pedido, 'clave-compartida', 10, $sucursal)['comandas']->sole();
            $this->assertSame(1, $comanda->numero);
            $this->assertSame($comanda->id_comanda, $this->cocina->tablero($sucursal, 0)[0]['id_comanda']);
        }
        $this->expectException(DomainException::class);
        $this->cocina->reimprimir($comanda->id_comanda, 'solicitud', 1);
    }

    public function test_mover_parte_de_la_comanda_muestra_el_destino_de_cada_item(): void
    {
        $origen = $this->abrir();
        $destino = $this->pedidos->abrir(['tipo' => Pedido::MESA, 'id_mesa' => 2, 'clave_idempotencia' => 'mesa-2'], 10, 1);
        [$seQueda, $seMueve] = $this->pedidos->agregarItems($origen->id_pedido, [$this->lomo('primero'), $this->lomo('segundo')], 10, 1);
        $comanda = $this->envio->enviar($origen->id_pedido, 'envio', 10, 1)['comandas']->sole();

        app(TransferirPedidoService::class)->moverItems($origen->id_pedido, $destino->id_pedido, [$seMueve->id_detalle_pedido], 10, 1);

        [$fila] = $this->cocina->tablero(1);
        $this->assertSame($comanda->id_comanda, $fila['id_comanda']);
        $this->assertSame('M1', $fila['pedido']['mesa']);
        $this->assertSame($seQueda->id_detalle_pedido, $fila['items'][0]['id_detalle_pedido']);
        $this->assertNull($fila['items'][0]['cuenta']);
        $this->assertSame('M2', $fila['items'][1]['cuenta']['mesa']);
    }

    public function test_el_resultado_del_agente_actualiza_la_impresora_de_la_comanda(): void
    {
        $this->prepararImpresora();
        $pedido = $this->abrir();
        $this->pedidos->agregarItems($pedido->id_pedido, [$this->lomo('lomo')], 10, 1);
        $this->envio->enviar($pedido->id_pedido, 'envio', 10, 1);
        $job = TrabajoImpresion::query()->sole();
        $job->update(['estado' => 'ENTREGADO_AGENTE']);
        $request = Request::create('/', 'POST', ['estado' => 'CONFIRMADO']);
        $request->attributes->set('terminal_pos', TerminalPos::query()->findOrFail(1));

        $respuesta = app(AgenteImpresionController::class)->resultado($request, $job->getKey());

        $this->assertSame(200, $respuesta->getStatusCode());
        $this->assertDatabaseHas('catalogos.impresoras', ['id_impresora' => 2, 'ultimo_resultado_impresion' => 'correcta']);
        $this->assertDatabaseHas('catalogos.impresoras', ['id_impresora' => 1, 'ultimo_resultado_impresion' => null]);
    }
}
