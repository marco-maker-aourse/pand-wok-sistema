<?php

namespace App\Console\Commands;

use App\Models\Impresora;
use Illuminate\Console\Command;

class ConfigurarCloudPrnt extends Command
{
    protected $signature = 'restaurante:cloudprnt:clave {impresora : ID en el catálogo de impresoras}';

    protected $description = 'Genera o rota la contraseña de una impresora Star CloudPRNT del restaurante';

    public function handle(): int
    {
        $id = $this->argument('impresora');
        if (! ctype_digit($id) || strlen($id) > 18) {
            $this->error('El ID de la impresora debe ser numérico.');

            return self::FAILURE;
        }

        $impresora = Impresora::query()->findOrFail((int) $id);
        if ($impresora->cloudprnt_clave_hash && ! $this->confirm('¿Rotar la contraseña y deshabilitar la anterior?')) {
            return self::FAILURE;
        }

        $clave = bin2hex(random_bytes(20));
        $impresora->forceFill(['cloudprnt_clave_hash' => hash('sha256', $clave)])->save();

        $this->info('Servidor: '.route('api.restaurante.cloudprnt', $impresora->getKey()));
        $this->info('Usuario: '.$impresora->getKey());
        $this->info('Contraseña (se muestra una vez): '.$clave);

        return self::SUCCESS;
    }
}
