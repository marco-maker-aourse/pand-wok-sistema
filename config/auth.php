<?php

use App\Models\User;

return [

    // Guard y broker por defecto
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    // Guardias de autenticación
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    // Proveedor configurado con el modelo User (tabla seguridad.usuarios)
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],

    // Restablecimiento de contraseñas
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    // Expiración de confirmación de clave
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];