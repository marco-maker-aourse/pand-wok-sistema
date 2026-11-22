<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleRoutesTest extends TestCase
{
    public function test_private_modules_require_authentication(): void
    {
        foreach (['/', '/clientes', '/caja', '/ventas', '/inventario', '/kardex', '/usuarios', '/reportes'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_login_page_is_public(): void { $this->get('/login')->assertOk(); }

    public function test_print_agent_routes_reject_requests_without_a_device_token(): void
    {
        $this->getJson('/api/agente-impresion/v1/trabajos/siguiente')->assertUnauthorized();
        $this->putJson('/api/agente-impresion/v1/terminal/impresora-sistema', ['nombre' => 'Cola local'])->assertUnauthorized();
    }

    public function test_module_routes_are_registered_without_requiring_sqlite_schemas(): void
    {
        // SQLite cannot emulate PostgreSQL schemas such as catalogos.productos.
        // Query-backed pages are covered against the Supabase integration environment.
        foreach (['productos.index', 'clientes.index', 'caja.index', 'caja.apertura', 'caja.movimientos.store', 'caja.resumen', 'caja.cierre', 'proveedores.index', 'usuarios.index', 'documentos.lookup', 'roles.permissions.update', 'impresoras.index', 'impresoras.store', 'impresoras.diagnosticar', 'impresoras.prueba', 'terminales-pos.store', 'terminales-pos.vinculacion.crear', 'terminales-pos.vinculacion.revocar', 'ventas.ticket.imprimir'] as $name) {
            $this->assertTrue(Route::has($name));
        }
    }
}
