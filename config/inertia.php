<?php

/*
|--------------------------------------------------------------------------
| Inertia (solo lo que difiere del paquete)
|--------------------------------------------------------------------------
|
| inertia-laravel combina este archivo con su configuración por defecto clave a clave
| (mergeConfigFrom), así que aquí solo se redefinen `pages` y `testing`. El resto
| (SSR, historial, DevTools) sigue los valores del paquete.
|
| Por defecto el paquete busca las páginas en resources/js/pages (minúsculas). Este
| proyecto las tiene en resources/js/Pages, que es la carpeta que resuelve app.jsx
| (import.meta.glob sobre ./Pages, archivos .jsx). En Windows la diferencia pasa desapercibida;
| en Linux (GitHub Actions, hosting) no se encuentran.
|
*/

return [

    'pages' => [
        // Validación en tiempo de ejecución: igual que el paquete (desactivada en producción).
        'ensure_pages_exist' => false,

        'paths' => [
            resource_path('js/Pages'),
        ],

        // Todas las páginas del proyecto son .jsx (ver resources/js/app.jsx).
        'extensions' => [
            'jsx',
        ],
    ],

    'testing' => [
        // assertInertia comprueba que el componente exista en disco. Se mantiene activo.
        'ensure_pages_exist' => true,
    ],

];
