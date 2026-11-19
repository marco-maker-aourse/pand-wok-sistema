<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * PostgreSQL/Supabase: un cajero solo puede mantener un turno abierto
     * por sucursal. El servicio aplica el mismo control para mensajes claros.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS turnos_caja_unico_abierto_usuario_sucursal
            ON operaciones.turnos_caja (id_usuario, id_sucursal)
            WHERE estado = 'ABIERTO'
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS operaciones.turnos_caja_unico_abierto_usuario_sucursal');
        }
    }
};
