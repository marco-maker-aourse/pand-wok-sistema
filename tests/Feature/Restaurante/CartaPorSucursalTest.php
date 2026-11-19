<?php

namespace Tests\Feature\Restaurante;

use App\Models\Restaurante\Pedido;
use App\Services\Restaurante\CartaPublicaService;
use App\Services\Restaurante\GestionarPedidoService;
use App\Services\Restaurante\ImportarCartaService;
use App\Services\Restaurante\PrecioSucursalService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Precios por sucursal, importación de carta y carta pública. */
class CartaPorSucursalTest extends TestCase
{
    use PreparaBaseRestaurante;

    private const ARCHIVO = 'database/data/carta/pand-wok.json';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararBaseRestaurante();
    }

    private function cartaDeEjemplo(): array
    {
        return json_decode(file_get_contents(base_path(self::ARCHIVO)), true);
    }

    private function abrir(int $sucursal, int $mesa, string $clave): Pedido
    {
        return app(GestionarPedidoService::class)->abrir(['tipo' => Pedido::MESA, 'id_mesa' => $mesa, 'clave_idempotencia' => $clave], 10, $sucursal);
    }

    public function test_el_pedido_usa_el_precio_de_su_sucursal(): void
    {
        $precios = app(PrecioSucursalService::class);
        $precios->fijar(2, 1, 6.5, true);   // Gaseosa: S/ 6.50 en la sucursal 1.
        $pedidos = app(GestionarPedidoService::class);

        [$centro] = $pedidos->agregarItems($this->abrir(1, 1, 'c')->id_pedido, [$this->gaseosa('g1')], 10, 1);
        [$norte] = $pedidos->agregarItems($this->abrir(2, 3, 'n')->id_pedido, [$this->gaseosa('g2')], 10, 2);

        $this->assertSame('6.5000', $centro->precio_unitario);
        $this->assertSame('5.0000', $norte->precio_unitario, 'Sin precio propio rige el general.');
    }

    public function test_un_plato_que_no_se_vende_en_la_sucursal_no_se_puede_pedir(): void
    {
        app(PrecioSucursalService::class)->fijar(2, 1, null, false);

        $this->expectExceptionMessage('no se vende en esta sucursal');
        app(GestionarPedidoService::class)->agregarItems($this->abrir(1, 1, 'c')->id_pedido, [$this->gaseosa('g')], 10, 1);
    }

    public function test_volver_al_precio_general_borra_el_ajuste(): void
    {
        $precios = app(PrecioSucursalService::class);
        $precios->fijar(2, 1, 7, true);
        $precios->fijar(2, 1, null, true);

        $this->assertSame(0, DB::table('restaurante.precios_sucursal')->count());
    }

    public function test_importa_la_carta_con_precios_por_sucursal_y_es_repetible(): void
    {
        DB::table('inventario.sucursales')->where('id_sucursal', 1)->update(['slug' => 'chilca']);
        DB::table('inventario.sucursales')->insert(['id_sucursal' => 3, 'nombre' => 'Tingo', 'slug' => 'tingomaria']);
        $importar = app(ImportarCartaService::class);

        // "tambo" se asocia explícitamente a la sucursal 2 (sin slug).
        $resumen = $importar->importar($this->cartaDeEjemplo(), ['tambo' => 2]);

        $this->assertSame(14, $resumen['categorias']);
        $this->assertSame(176, $resumen['creados']);
        $this->assertSame(['chilca' => 'Centro', 'tambo' => 'Norte', 'tingomaria' => 'Tingo'], $resumen['sedes']);
        // 176 platos × 3 sedes = 528; 2 platos solo se venden en Tingo María (2 × 2 no disponibles).
        $this->assertSame(524, $resumen['precios_sucursal']);
        $this->assertSame(4, $resumen['no_disponibles']);

        $chaufa = DB::table('catalogos.productos')->where('codigo_externo', 'c01')->first();
        $this->assertSame('Chaufa con Tallarín', $chaufa->nombre);
        $this->assertSame('01', $chaufa->codigo_carta);
        $this->assertSame('PREPARADO', $chaufa->tipo_producto);
        $idPrecio = DB::table('inventario.precio_producto')->where('id_producto', $chaufa->id_producto)->value('id_precio');
        $this->assertSame(
            [1 => 14.0, 2 => 13.0, 3 => 14.0],
            DB::table('restaurante.precios_sucursal')->where('id_precio', $idPrecio)->orderBy('id_sucursal')->pluck('precio_venta', 'id_sucursal')->map(fn ($p) => (float) $p)->all(),
        );

        $repetida = $importar->importar($this->cartaDeEjemplo(), ['tambo' => 2]);
        $this->assertSame(0, $repetida['creados']);
        $this->assertSame(176, $repetida['actualizados']);
        $this->assertSame(176 + 3, DB::table('catalogos.productos')->count(), 'No se duplican productos.');
    }

    public function test_la_simulacion_no_guarda_nada(): void
    {
        $resumen = app(ImportarCartaService::class)->importar($this->cartaDeEjemplo(), [], true);

        $this->assertTrue($resumen['simulado']);
        $this->assertSame(176, $resumen['creados']);
        $this->assertSame(3, DB::table('catalogos.productos')->count());
        $this->assertSame(0, DB::table('catalogos.categorias')->count());
    }

    public function test_rechaza_archivos_mal_formados(): void
    {
        $casos = [
            'sin sedes' => ['carta' => []],
            'precio para sede inexistente' => ['sedes' => ['a' => []], 'carta' => [['categoria' => 'X', 'platos' => [['id' => 'x1', 'nombre' => 'X', 'precios' => ['b' => 1]]]]]],
            'id repetido' => ['sedes' => ['a' => []], 'carta' => [['categoria' => 'X', 'platos' => [
                ['id' => 'x1', 'nombre' => 'X', 'precios' => ['a' => 1]],
                ['id' => 'x1', 'nombre' => 'Y', 'precios' => ['a' => 2]],
            ]]]],
        ];

        foreach ($casos as $caso => $datos) {
            try {
                app(ImportarCartaService::class)->importar($datos);
                $this->fail("Se aceptó: {$caso}");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_la_carta_publica_devuelve_los_mismos_precios_que_se_importaron(): void
    {
        DB::table('inventario.sucursales')->where('id_sucursal', 1)->update(['slug' => 'chilca', 'horario_atencion' => '10:30 AM - 11:00 PM']);
        DB::table('inventario.sucursales')->where('id_sucursal', 2)->update(['slug' => 'tambo']);
        DB::table('inventario.sucursales')->insert(['id_sucursal' => 3, 'nombre' => 'Tingo', 'slug' => 'tingomaria']);
        $origen = $this->cartaDeEjemplo();
        app(ImportarCartaService::class)->importar($origen);
        // Productos de prueba ajenos a la carta importada: no deben mezclarse.
        DB::table('catalogos.productos')->whereNull('codigo_externo')->update(['estado' => false]);

        $publica = app(CartaPublicaService::class)->carta();

        $this->assertSame(['chilca', 'tambo', 'tingomaria'], array_keys($publica['sedes']));
        $this->assertSame('10:30 AM - 11:00 PM', $publica['sedes']['chilca']['horario']);
        $this->assertSame(array_column($origen['carta'], 'categoria'), array_column($publica['carta'], 'categoria'), 'Mismo orden de categorías.');
        $this->assertSame('Oferta: 3 wantanes fritos ó sopa 1.00', $publica['carta'][0]['oferta']);

        $precios = fn (array $carta) => collect($carta)->flatMap(fn ($c) => $c['platos'])
            ->mapWithKeys(fn ($p) => [$p['id'] => array_map('floatval', $p['precios'])])->sortKeys()->all();
        $this->assertEquals($precios($origen['carta']), $precios($publica['carta']));

        // Un agotado se informa sin ocultar el plato.
        $producto = DB::table('catalogos.productos')->where('codigo_externo', 'c01')->value('id_producto');
        DB::table('restaurante.productos_agotados')->insert(['id_sucursal' => 2, 'id_producto' => $producto, 'id_usuario' => 10, 'marcado_en' => now()]);
        $plato = collect(app(CartaPublicaService::class)->carta('tambo')['carta'][0]['platos'])->firstWhere('id', 'c01');
        $this->assertSame(['tambo'], $plato['agotadoEn']);
        $this->assertSame(['tambo' => 13.0], $plato['precios']);
    }

    public function test_api_publica_responde_sin_sesion_y_404_si_la_sucursal_no_existe(): void
    {
        DB::table('inventario.sucursales')->where('id_sucursal', 1)->update(['slug' => 'chilca']);
        DB::table('catalogos.categorias')->insert(['id_categoria' => 1, 'nombre' => 'Bebidas']);
        DB::table('catalogos.productos')->where('id_producto', 2)->update(['id_categoria' => 1]);

        $this->getJson('/api/carta-publica/chilca')
            ->assertOk()
            ->assertJsonPath('sedes.chilca.nombre', 'Centro')
            ->assertJsonPath('carta.0.platos.0.precios.chilca', 5);

        $this->getJson('/api/carta-publica/no-existe')->assertNotFound();
    }
}
