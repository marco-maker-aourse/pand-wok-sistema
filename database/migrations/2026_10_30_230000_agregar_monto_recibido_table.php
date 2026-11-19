<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('operaciones.pagos_ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('operaciones.pagos_ventas', 'monto_recibido')) {
                $table->decimal('monto_recibido', 14, 2)->nullable();
            }
            if (! Schema::hasColumn('operaciones.pagos_ventas', 'vuelto')) {
                $table->decimal('vuelto', 14, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('operaciones.pagos_ventas', function (Blueprint $table) {
            $columns = collect(['monto_recibido', 'vuelto'])
                ->filter(fn (string $column): bool => Schema::hasColumn('operaciones.pagos_ventas', $column))
                ->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
