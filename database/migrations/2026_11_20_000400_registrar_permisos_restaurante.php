<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restaurante — permisos de salón, pedidos y carta.
 *
 * "Administrador General" ya tiene todos los permisos por código (User::hasPermission).
 * Aquí solo se asignan al rol "Administrador" si existe; el resto de roles se
 * configura desde Roles y permisos.
 */
return new class extends Migration
{
    private const PERMISOS = [
        ['codigo' => 'mesas.ver', 'nombre' => 'Ver salón y mesas', 'modulo' => 'restaurante'],
        ['codigo' => 'mesas.gestionar', 'nombre' => 'Configurar áreas y mesas', 'modulo' => 'restaurante'],
        ['codigo' => 'pedidos.registrar', 'nombre' => 'Abrir pedidos y tomar órdenes', 'modulo' => 'restaurante'],
        ['codigo' => 'pedidos.transferir', 'nombre' => 'Transferir, unir y separar cuentas', 'modulo' => 'restaurante'],
        ['codigo' => 'pedidos.anular', 'nombre' => 'Anular pedidos e ítems enviados a cocina', 'modulo' => 'restaurante'],
        ['codigo' => 'carta.gestionar', 'nombre' => 'Gestionar carta, modificadores y agotados', 'modulo' => 'restaurante'],
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $permiso) {
            DB::table('seguridad.permisos')->insertOrIgnore([...$permiso, 'estado' => true]);
        }

        $idAdministrador = DB::table('seguridad.roles')->where('nombre', 'Administrador')->value('id_rol');
        if (! $idAdministrador) {
            return;
        }

        $ids = DB::table('seguridad.permisos')
            ->whereIn('codigo', array_column(self::PERMISOS, 'codigo'))
            ->pluck('id_permiso');

        DB::table('seguridad.rol_permisos')->insertOrIgnore(
            $ids->map(fn ($id): array => ['id_rol' => $idAdministrador, 'id_permiso' => $id])->all()
        );
    }

    public function down(): void
    {
        // rol_permisos se elimina en cascada.
        DB::table('seguridad.permisos')->whereIn('codigo', array_column(self::PERMISOS, 'codigo'))->delete();
    }
};
