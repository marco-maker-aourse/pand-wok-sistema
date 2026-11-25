<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Catálogos sin los que el sistema no opera: documentos, comprobantes, unidades,
 * métodos de pago, presentaciones y el cliente genérico.
 *
 * Se puede ejecutar sobre una base ya en uso (`php artisan db:seed --class=DatosBaseSeeder`):
 * solo agrega lo que falta.
 */
class DatosBaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CatalogosSunatSeeder::class, CatalogosRestauranteSeeder::class]);
    }
}
