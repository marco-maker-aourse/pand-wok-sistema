<?php

namespace Tests\Feature\Restaurante;

use App\Models\Restaurante\Pedido;
use App\Services\Restaurante\ConfigurarAdicionalesCombinacionService;
use App\Services\Restaurante\ConfigurarSopasMenuService;
use App\Services\Restaurante\GestionarPedidoService;
use App\Services\Restaurante\OpcionesSucursalService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reglas de la carta de Pand Wok:
 * - Menú: con o sin sopa, al mismo precio.
 * - Combinación: sopa o 3 wantanes fritos como adicional (+ S/ 1.00), ajustable por sucursal.
 */
class OpcionesMenuYCombinacionTest extends TestCase
{
    use PreparaCartaEstructurada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCartaEstructurada();
        DB::table('catalogos.unidades_medida')->insert(['id_unidad' => 1, 'codigo_sunat' => 'NIU', 'nombre' => 'Unidad']);
        DB::table('catalogos.presentaciones')->insert(['id_presentacion' => 1, 'nombre' => 'Unidad']);
    }

    private function agregar(array $item, int $sucursal = 1): \App\Models\Restaurante\PedidoDetalle
    {
        static $n = 0;
        $pedido = app(GestionarPedidoService::class)->abrir(['tipo' => Pedido::PARA_LLEVAR, 'nombre_contacto' => 'Ana', 'clave_idempotencia' => 'pedido'.++$n], 10, $sucursal);

        return app(GestionarPedidoService::class)->agregarItems($pedido->id_pedido, [$item], 10, $sucursal)[0];
    }

    private function opcion(int $grupo, string $nombre): int
    {
        return (int) DB::table('restaurante.opciones_modificador as o')
            ->leftJoin('catalogos.producto_presentaciones as p', 'p.id_precio', '=', 'o.id_precio')
            ->leftJoin('catalogos.productos as c', 'c.id_producto', '=', 'p.id_producto')
            ->where('o.id_grupo', $grupo)->whereRaw('COALESCE(o.nombre, c.nombre) = ?', [$nombre])->value('o.id_opcion');
    }

    public function test_el_menu_va_con_o_sin_sopa_al_mismo_precio(): void
    {
        // La base de prueba tenía la sopa obligatoria: configurar la vuelve opcional.
        $grupo = app(ConfigurarSopasMenuService::class)->ejecutar([10])['grupo'];
        $this->assertSame(0, (int) DB::table('restaurante.grupos_modificadores')->where('id_grupo', $grupo)->value('seleccion_minima'));

        $sinSopa = $this->agregar($this->menu('m1', 1, []));
        $conSopa = $this->agregar($this->menu('m2', 1, [$this->opcion($grupo, 'Sopa wantán') => 1]));

        $this->assertSame('15.0000', $sinSopa->precio_unitario);
        $this->assertSame('15.0000', $conSopa->precio_unitario, 'La sopa no sube ni baja el precio del menú.');
    }

    public function test_la_combinacion_acepta_sopa_o_wantanes_con_adicional_por_sucursal(): void
    {
        $servicio = app(ConfigurarAdicionalesCombinacionService::class);
        $resumen = $servicio->ejecutar([20]);
        $servicio->ejecutar([20]);
        $grupo = $resumen['grupo'];

        $this->assertSame(['3 wantanes fritos', 'Sopa wantán', 'Sopa de pollo', 'Sopa de kion'], $resumen['opciones']);
        $this->assertSame(4, DB::table('restaurante.opciones_modificador')->where('id_grupo', $grupo)->count(), 'Repetir no duplica opciones.');

        $wantanes = $this->opcion($grupo, '3 wantanes fritos');
        $sinAdicional = $this->agregar($this->combinacion('c1'));
        $conWantanes = $this->agregar($this->combinacion('c2', 1, [$wantanes => 1]));
        $this->assertSame('18.0000', $sinAdicional->precio_unitario, 'El adicional es opcional.');
        $this->assertSame('19.0000', $conWantanes->precio_unitario);

        // El precio del adicional se fija por sucursal.
        app(OpcionesSucursalService::class)->fijar($wantanes, 2, 1.5, true);
        $this->assertSame('19.5000', $this->agregar($this->combinacion('c3', 1, [$wantanes => 1]), 2)->precio_unitario);
        $this->assertSame('19.0000', $this->agregar($this->combinacion('c4', 1, [$wantanes => 1]), 1)->precio_unitario);

        // Solo un adicional por combinación.
        $this->expectException(DomainException::class);
        $this->agregar($this->combinacion('c5', 1, [$wantanes => 1, $this->opcion($grupo, 'Sopa wantán') => 1]));
    }
}
