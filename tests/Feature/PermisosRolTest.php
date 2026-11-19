<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Reglas de hasPermission(): rol activo, permiso activo y bypass del Administrador General. */
class PermisosRolTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement("ATTACH DATABASE ':memory:' AS seguridad");
        DB::statement('CREATE TABLE seguridad.roles (id_rol INTEGER PRIMARY KEY, nombre TEXT, estado INTEGER)');
        DB::statement('CREATE TABLE seguridad.permisos (id_permiso INTEGER PRIMARY KEY, codigo TEXT, estado INTEGER)');
        DB::statement('CREATE TABLE seguridad.rol_permisos (id_rol INTEGER, id_permiso INTEGER)');

        DB::table('seguridad.roles')->insert([
            ['id_rol' => 1, 'nombre' => 'Administrador General', 'estado' => 1],
            ['id_rol' => 2, 'nombre' => 'Cajero', 'estado' => 1],
            ['id_rol' => 3, 'nombre' => 'Cajero inactivo', 'estado' => 0],
        ]);
        DB::table('seguridad.permisos')->insert([
            ['id_permiso' => 1, 'codigo' => 'ventas.registrar', 'estado' => 1],
            ['id_permiso' => 2, 'codigo' => 'kardex.ver', 'estado' => 0],
        ]);
        DB::table('seguridad.rol_permisos')->insert([
            ['id_rol' => 2, 'id_permiso' => 1], ['id_rol' => 2, 'id_permiso' => 2], ['id_rol' => 3, 'id_permiso' => 1],
        ]);
    }

    public function test_administrador_general_tiene_todos_los_permisos(): void
    {
        $this->assertTrue($this->usuario(1)->hasPermission('usuarios.gestionar'));
    }

    public function test_cajero_solo_tiene_permisos_asignados_y_activos(): void
    {
        $cajero = $this->usuario(2);

        $this->assertTrue($cajero->hasPermission('ventas.registrar'));
        $this->assertFalse($cajero->hasPermission('kardex.ver'));
        $this->assertFalse($cajero->hasPermission('usuarios.gestionar'));
    }

    public function test_rol_inactivo_o_inexistente_no_tiene_permisos(): void
    {
        $this->assertFalse($this->usuario(3)->hasPermission('ventas.registrar'));
        $this->assertFalse($this->usuario(99)->hasPermission('ventas.registrar'));
    }

    private function usuario(int $rolId): User
    {
        return (new User)->forceFill(['id_usuario' => 1, 'id_rol' => $rolId]);
    }
}
