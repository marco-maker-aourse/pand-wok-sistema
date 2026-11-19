<?php

use App\Providers\AppServiceProvider;
use Inertia\ServiceProvider as InertiaServiceProvider;

return [
    AppServiceProvider::class,
    // Registro explícito: evita depender de una caché de descubrimiento local.
    InertiaServiceProvider::class,
];
