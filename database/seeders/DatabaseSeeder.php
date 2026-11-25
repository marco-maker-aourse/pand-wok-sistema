<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Instalación inicial (`php artisan migrate:fresh --seed`). Todo o nada: si falta la
 * configuración del administrador, no queda una base a medias.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(fn () => $this->call([
            DatosBaseSeeder::class,
            EmpresaSeeder::class,
            SeguridadSeeder::class,
        ]));
    }
}
