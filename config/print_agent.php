<?php

return [
    /* URL HTTPS administrada por despliegue. El binario no se versiona dentro de Laravel. */
    'installer_url' => env('PRINT_AGENT_INSTALLER_URL'),
    'installer_version' => env('PRINT_AGENT_INSTALLER_VERSION'),
    // El administrador puede comprobar el binario descargado antes de instalarlo.
    'installer_sha256' => env('PRINT_AGENT_INSTALLER_SHA256'),
];
