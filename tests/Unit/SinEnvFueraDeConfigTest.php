<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * El host ejecuta `php artisan config:cache` en cada despliegue. Con la caché activa,
 * env() devuelve null fuera de config/: una credencial leída así desaparece en producción
 * aunque esté en el .env. Toda lectura del entorno debe pasar por config().
 */
class SinEnvFueraDeConfigTest extends TestCase
{
    public function test_el_codigo_de_app_no_lee_env_directamente(): void
    {
        $raiz = dirname(__DIR__, 2).'/app';
        $usos = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz)) as $archivo) {
            if ($archivo->getExtension() !== 'php') {
                continue;
            }
            foreach (file($archivo->getPathname()) as $numero => $linea) {
                if (preg_match('/(?<![\w>:$])env\(/', $linea)) {
                    $usos[] = str_replace($raiz, 'app', $archivo->getPathname()).':'.($numero + 1);
                }
            }
        }

        $this->assertSame([], $usos, 'Usa config() en lugar de env() fuera de config/.');
    }
}
