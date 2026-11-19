<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISOS = [
        'pos.descuentos' => 'Aplicar descuentos al cobrar pedidos',
        'recetas.gestionar' => 'Configurar recetas y consultar sus costos estimados',
        'mermas.registrar' => 'Registrar mermas de insumos',
        'reportes.restaurante' => 'Consultar reportes del restaurante',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $codigo => $nombre) {
            DB::table('seguridad.permisos')->insertOrIgnore(['codigo' => $codigo, 'nombre' => $nombre, 'modulo' => 'restaurante', 'estado' => true]);
        }

        $porRol = [
            'Administrador' => array_keys(self::PERMISOS),
            'Cajero' => ['mesas.ver'],
            'Almacenero' => ['mermas.registrar'],
        ];
        $roles = DB::table('seguridad.roles')->whereIn('nombre', array_keys($porRol))->pluck('id_rol', 'nombre');
        $permisos = DB::table('seguridad.permisos')->whereIn('codigo', [...array_keys(self::PERMISOS), 'mesas.ver'])->pluck('id_permiso', 'codigo');
        foreach ($roles as $rol => $id) {
            foreach ($porRol[$rol] as $codigo) {
                DB::table('seguridad.rol_permisos')->insertOrIgnore(['id_rol' => $id, 'id_permiso' => $permisos[$codigo]]);
            }
        }

        DB::statement('CREATE INDEX ventas_restaurante_fecha_idx ON operaciones.ventas (id_sucursal, fecha_emision) WHERE id_pedido IS NOT NULL');
        DB::statement('CREATE INDEX comandas_restaurante_fecha_idx ON restaurante.comandas (id_sucursal, enviada_en)');
        DB::statement("CREATE INDEX pedidos_restaurante_anulados_idx ON restaurante.pedidos (id_sucursal, cerrado_en) WHERE estado = 'ANULADO'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX restaurante.pedidos_restaurante_anulados_idx');
        DB::statement('DROP INDEX restaurante.comandas_restaurante_fecha_idx');
        DB::statement('DROP INDEX operaciones.ventas_restaurante_fecha_idx');
        DB::table('seguridad.permisos')->whereIn('codigo', array_keys(self::PERMISOS))->delete();
    }
};
