<?php

namespace Tests\Feature\Restaurante;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Flujo HTTP del restaurante: autenticación, permisos, pantallas Inertia y operaciones. */
class RutasRestauranteTest extends TestCase
{
    use PreparaBaseRestaurante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararBaseRestaurante();

        DB::statement("ATTACH DATABASE ':memory:' AS seguridad");
        DB::statement('CREATE TABLE seguridad.roles (id_rol INTEGER PRIMARY KEY, nombre TEXT, descripcion TEXT NULL, estado BOOLEAN DEFAULT 1)');
        DB::statement('CREATE TABLE seguridad.permisos (id_permiso INTEGER PRIMARY KEY, codigo TEXT UNIQUE, nombre TEXT, modulo TEXT, descripcion TEXT NULL, estado BOOLEAN DEFAULT 1)');
        DB::statement('CREATE TABLE seguridad.rol_permisos (id_rol INTEGER, id_permiso INTEGER, PRIMARY KEY (id_rol, id_permiso))');
        DB::statement('CREATE TABLE seguridad.usuarios (id_usuario INTEGER PRIMARY KEY, id_rol INTEGER, id_sucursal INTEGER, nombres TEXT, email TEXT, password TEXT, estado BOOLEAN DEFAULT 1)');
        DB::statement('CREATE TABLE seguridad.usuario_sucursales (id_usuario INTEGER, id_sucursal INTEGER)');
        DB::statement('CREATE TABLE catalogos.clientes (id_cliente INTEGER PRIMARY KEY, nombre TEXT NULL)');

        DB::table('seguridad.roles')->insert([
            ['id_rol' => 1, 'nombre' => 'Administrador General'],
            ['id_rol' => 5, 'nombre' => 'Mozo'],
        ]);
        foreach (['mesas.ver', 'pedidos.registrar', 'comandas.enviar'] as $i => $codigo) {
            DB::table('seguridad.permisos')->insert(['id_permiso' => $i + 1, 'codigo' => $codigo, 'nombre' => $codigo, 'modulo' => 'restaurante']);
            DB::table('seguridad.rol_permisos')->insert(['id_rol' => 5, 'id_permiso' => $i + 1]);
        }
        DB::table('seguridad.usuarios')->insert([
            ['id_usuario' => 10, 'id_rol' => 1, 'id_sucursal' => 1, 'nombres' => 'Ana Admin', 'email' => 'a@x.test'],
            ['id_usuario' => 20, 'id_rol' => 5, 'id_sucursal' => 1, 'nombres' => 'Mario Mozo', 'email' => 'm@x.test'],
        ]);
    }

    private function usuario(int $id): User
    {
        return User::query()->with('rol')->findOrFail($id);
    }

    public function test_las_pantallas_del_restaurante_exigen_sesion(): void
    {
        foreach (['/salon', '/salon/configuracion', '/carta', '/pedidos/1', '/cocina', '/cocina/estaciones'] as $ruta) {
            $this->get($ruta)->assertRedirect(route('login'));
        }
    }

    public function test_flujo_de_mesa_abrir_tomar_pedido_y_ver_la_cuenta(): void
    {
        $admin = $this->usuario(10);
        DB::table('restaurante.precios_sucursal')->insert(['id_sucursal' => 1, 'id_precio' => 2, 'precio_venta' => 6, 'disponible' => true]);

        $this->actingAs($admin)->get('/salon')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Restaurante/Salon')
            ->where('resumen.libres', 2)
            ->has('areas', 1)
            ->where('areas.0.mesas.0.codigo', 'M1')
            ->where('puede.abrir', true));

        $respuesta = $this->actingAs($admin)->post('/pedidos', ['tipo' => 'MESA', 'id_mesa' => 1, 'comensales' => 3, 'clave_idempotencia' => str_repeat('a', 32)]);
        $idPedido = (int) DB::table('restaurante.pedidos')->value('id_pedido');
        $respuesta->assertRedirect(route('restaurante.pedidos.show', $idPedido));

        $this->actingAs($admin)->post("/pedidos/{$idPedido}/items", ['items' => [
            ['clave' => str_repeat('b', 32), 'id_precio' => 1, 'cantidad' => 1, 'opciones' => [2, 3]],
            ['clave' => str_repeat('c', 32), 'id_precio' => 2, 'cantidad' => 2],
        ]])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get("/pedidos/{$idPedido}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Restaurante/Pedido')
            ->where('pedido.mesa.codigo', 'M1')
            ->where('pedido.total', 44.5)   // 30 + 2.50 (huevo) + 2 × 6 (precio de la sucursal)
            ->has('pedido.detalles', 2)
            ->where('carta.productos', fn ($productos) => collect($productos)->pluck('nombre')->sort()->values()->all() === ['Gaseosa', 'Lomo saltado'])
            ->where('carta.productos', fn ($productos) => collect($productos)->firstWhere('nombre', 'Gaseosa')['presentaciones'][0]['precio_venta'] == 6));

        $this->actingAs($admin)->get('/salon')->assertInertia(fn (Assert $page) => $page
            ->where('areas.0.mesas.0.estado', 'OCUPADA')
            ->where('areas.0.mesas.0.cuenta.total', 44.5));
    }

    public function test_errores_de_negocio_vuelven_como_errores_de_validacion(): void
    {
        $admin = $this->usuario(10);
        $this->actingAs($admin)->post('/pedidos', ['tipo' => 'MESA', 'id_mesa' => 1, 'clave_idempotencia' => str_repeat('a', 32)]);

        $this->actingAs($admin)->from('/salon')
            ->post('/pedidos', ['tipo' => 'MESA', 'id_mesa' => 1, 'clave_idempotencia' => str_repeat('z', 32)])
            ->assertRedirect('/salon')
            ->assertSessionHasErrors(['general']);
    }

    public function test_un_mozo_toma_pedidos_pero_no_transfiere_ni_configura(): void
    {
        $mozo = $this->usuario(20);

        $this->actingAs($mozo)->get('/salon')->assertOk();
        $this->actingAs($mozo)->post('/pedidos', ['tipo' => 'MESA', 'id_mesa' => 1, 'clave_idempotencia' => str_repeat('a', 32)])->assertRedirect();
        $idPedido = (int) DB::table('restaurante.pedidos')->value('id_pedido');

        $this->actingAs($mozo)->post("/pedidos/{$idPedido}/transferir", ['operacion' => 'mesa', 'id_mesa' => 2])->assertForbidden();
        $this->actingAs($mozo)->get('/salon/configuracion')->assertForbidden();
        $this->actingAs($mozo)->get('/carta')->assertForbidden();
        $this->assertSame('OCUPADA', $this->estadoMesa(1));
    }

    public function test_mozo_guarda_y_envia_pero_solo_cocina_actualiza_el_estado(): void
    {
        $this->actingAs($this->usuario(20))->post('/pedidos', ['tipo' => 'MESA', 'id_mesa' => 1, 'clave_idempotencia' => str_repeat('k', 32)]);
        $pedido = (int) DB::table('restaurante.pedidos')->value('id_pedido');
        $datos = ['items' => [$this->lomo(str_repeat('l', 32))], 'clave_envio' => str_repeat('e', 32)];
        $this->post("/pedidos/{$pedido}/guardar-y-enviar", $datos)->assertSessionHasNoErrors();
        $this->post("/pedidos/{$pedido}/guardar-y-enviar", $datos)->assertSessionHasNoErrors();
        $comanda = (int) DB::table('restaurante.comandas')->value('id_comanda');
        $this->assertDatabaseCount('restaurante.comandas', 1);
        $this->assertDatabaseCount('restaurante.pedido_detalles', 1);
        $this->get('/cocina')->assertForbidden();
        $this->put("/cocina/comandas/{$comanda}/estado", ['estado' => 'LISTA'])->assertForbidden();

        $this->actingAs($this->usuario(10))->get('/cocina')->assertInertia(fn (Assert $page) => $page
            ->component('Restaurante/Cocina')->where('comandas.0.pedido.mesa', 'M1'));
        $this->put("/cocina/comandas/{$comanda}/estado", ['estado' => 'LISTA'])->assertSessionHasNoErrors();
        $this->get("/pedidos/{$pedido}")->assertInertia(fn (Assert $page) => $page->where('pedido.comandas.0.estado', 'LISTA'));
        $this->actingAs($this->usuario(10))->withSession(['sucursal_activa' => 2])->put("/cocina/comandas/{$comanda}/estado", ['estado' => 'ENTREGADA'])->assertNotFound();
    }

    public function test_administrador_configura_estaciones_y_categorias_de_la_sucursal(): void
    {
        DB::table('catalogos.categorias')->insert(['id_categoria' => 1, 'nombre' => 'Bebidas']);
        $datos = ['nombre' => 'Bar', 'activo' => true, 'por_defecto' => false, 'orden' => 0, 'copias' => 1, 'categorias' => [1]];
        $this->actingAs($this->usuario(20))->post('/cocina/estaciones', $datos)->assertForbidden();
        $this->actingAs($this->usuario(10))->post('/cocina/estaciones', $datos)->assertSessionHasNoErrors();
        $this->get('/cocina/estaciones')->assertInertia(fn (Assert $page) => $page
            ->component('Restaurante/Estaciones')->where('estaciones.0.nombre', 'Bar')->where('estaciones.0.categorias', [1]));
    }

    public function test_el_rol_de_cocina_entra_directamente_al_tablero_al_iniciar_sesion(): void
    {
        DB::table('seguridad.roles')->insert(['id_rol' => 6, 'nombre' => 'Cocina']);
        DB::table('seguridad.permisos')->insert(['id_permiso' => 6, 'codigo' => 'cocina.ver', 'nombre' => 'Ver cocina', 'modulo' => 'restaurante']);
        DB::table('seguridad.rol_permisos')->insert(['id_rol' => 6, 'id_permiso' => 6]);
        DB::table('seguridad.usuarios')->insert([
            'id_usuario' => 30, 'id_rol' => 6, 'id_sucursal' => 1, 'nombres' => 'Cocina', 'email' => 'c@x.test',
            'password' => Hash::make('clave-temporal-de-prueba'),
        ]);

        $this->post('/login', ['email' => 'c@x.test', 'password' => 'clave-temporal-de-prueba'])
            ->assertRedirect(route('restaurante.cocina'));
        $this->get('/cocina')->assertInertia(fn (Assert $page) => $page->component('Restaurante/Cocina'));
    }

    public function test_un_pedido_de_otra_sucursal_da_404(): void
    {
        $admin = $this->usuario(10);
        DB::table('restaurante.pedidos')->insert([
            'id_pedido' => 99, 'id_sucursal' => 2, 'numero' => 1, 'tipo' => 'PARA_LLEVAR', 'id_usuario' => 10,
            'estado' => 'ABIERTO', 'clave_idempotencia' => 'otra-sucursal', 'abierto_en' => now(),
        ]);

        $this->actingAs($admin)->get('/pedidos/99')->assertNotFound();
    }
}
