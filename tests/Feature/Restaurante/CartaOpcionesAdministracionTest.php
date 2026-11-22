<?php

namespace Tests\Feature\Restaurante;

use App\Models\User;
use App\Services\Restaurante\ConfigurarSopasMenuService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Administración de opciones: sopas iniciales, vínculos, ajustes por sucursal y variantes. */
class CartaOpcionesAdministracionTest extends TestCase
{
    use PreparaCartaEstructurada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararCartaEstructurada();
        DB::table('catalogos.unidades_medida')->insert(['id_unidad' => 1, 'codigo_sunat' => 'NIU', 'nombre' => 'Unidad']);
        DB::table('catalogos.presentaciones')->insert(['id_presentacion' => 1, 'nombre' => 'Unidad']);
    }

    public function test_las_sopas_iniciales_reutilizan_las_existentes_sin_duplicar_y_es_repetible(): void
    {
        // "Sopa de kion" y "Sopa de patita" no existen con ese prefijo en esta base: se renombran para probar.
        DB::table('catalogos.productos')->where('id_producto', 12)->update(['nombre' => 'Caldo de gallina']);
        DB::table('catalogos.productos')->where('id_producto', 14)->update(['nombre' => 'Sopa de Patitas (Sáb. Dom.)']);
        DB::table('restaurante.grupos_modificadores')->where('id_grupo', 10)->update(['nombre' => 'Sopa antigua']);

        $servicio = app(ConfigurarSopasMenuService::class);
        $primero = $servicio->ejecutar([10]);
        $segundo = $servicio->ejecutar([10]);

        $this->assertSame(['Sopa de pollo', 'Sopa wantán', 'Sopa de Patitas (Sáb. Dom.)'], $primero['reutilizadas']);
        $this->assertSame(['Sopa de kion'], $primero['creadas']);
        $this->assertSame([], $segundo['creadas'], 'La segunda vez reutiliza la sopa creada.');
        $this->assertSame(1, DB::table('catalogos.productos')->where('nombre', 'Sopa de kion')->count());
        $this->assertSame(4, DB::table('restaurante.opciones_modificador')->where('id_grupo', $primero['grupo'])->count());
        $this->assertSame(0, DB::table('restaurante.opciones_modificador')->where('id_grupo', $primero['grupo'])->whereNotNull('nombre')->count(),
            'Las opciones toman el nombre del producto.');
        $kion = DB::table('catalogos.producto_presentaciones as p')->join('catalogos.productos as c', 'c.id_producto', '=', 'p.id_producto')
            ->where('c.nombre', 'Sopa de kion')->value('p.id_precio');
        $this->assertSame(2, DB::table('restaurante.precios_sucursal')->where('id_precio', $kion)->where('disponible', false)->count(),
            'Sin precio real no se vende sola en ninguna sucursal.');
        $this->assertTrue(DB::table('restaurante.producto_grupo_modificador')->where(['id_producto' => 10, 'id_grupo' => $primero['grupo']])->exists());
    }

    public function test_el_administrador_vincula_opciones_ajusta_por_sucursal_y_define_variantes(): void
    {
        $admin = User::query()->findOrFail(10);
        DB::table('seguridad.permisos')->insert(['id_permiso' => 9, 'codigo' => 'carta.gestionar']);

        // Opción vinculada a un producto: sin nombre propio.
        $this->actingAs($admin)->post('/carta/modificadores/11/opciones', ['id_precio' => 11, 'precio_adicional' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('restaurante.opciones_modificador', ['id_grupo' => 11, 'id_precio' => 11, 'nombre' => null]);
        // Repetirla o vincular un insumo se rechaza.
        $this->actingAs($admin)->post('/carta/modificadores/11/opciones', ['id_precio' => 11, 'precio_adicional' => 2])->assertSessionHasErrors('id_precio');
        $this->actingAs($admin)->post('/carta/modificadores/11/opciones', ['id_precio' => 30, 'precio_adicional' => 0])->assertSessionHasErrors('id_precio');

        // Recargo propio en la sucursal activa (1) y obligatoriedad propia del plato.
        $this->actingAs($admin)->put('/carta/opciones/21/sucursal', ['precio_adicional' => 4, 'disponible' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('restaurante.opciones_sucursal', ['id_sucursal' => 1, 'id_opcion' => 21, 'precio_adicional' => 4]);
        $this->actingAs($admin)->put('/carta/productos/10/modificadores', [
            'grupos' => [10], 'reglas' => [['id_grupo' => 10, 'seleccion_minima' => 0, 'seleccion_maxima' => 1]],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('restaurante.producto_grupo_modificador', ['id_producto' => 10, 'id_grupo' => 10, 'seleccion_minima' => 0]);

        // Variante física: la gaseosa (DIRECTO) descuenta de un insumo; un plato no puede.
        $this->actingAs($admin)->put('/carta/precios/2/variante', ['id_producto_stock' => 3])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('catalogos.producto_presentaciones', ['id_precio' => 2, 'id_producto_stock' => 3]);
        $this->actingAs($admin)->put('/carta/precios/1/variante', ['id_producto_stock' => 3])->assertSessionHasErrors('id_producto_stock');

        $this->actingAs($admin)->get('/carta')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Restaurante/Carta')
            ->where('grupos', fn ($grupos) => collect(collect($grupos)->firstWhere('id_grupo', 11)['opciones'])
                ->firstWhere('id_opcion', 21)['precio_sucursal'] == 4));
    }
}
