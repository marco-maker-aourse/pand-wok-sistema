<?php

namespace App\Console\Commands;

use App\Services\Restaurante\ConfigurarSopasMenuService;
use App\Services\Restaurante\OpcionesCarta;
use Illuminate\Console\Command;

/**
 * Crea (o completa) el grupo "Sopa del menú" con las sopas iniciales: pollo, kion, wantán y patita.
 *
 *   php artisan restaurante:sopas-menu
 *   php artisan restaurante:sopas-menu --menus=Menu     (agrega el grupo a los platos de esa categoría)
 *
 * Es repetible: reutiliza las sopas que ya existen en la carta y no duplica opciones.
 * No es un seeder de demostración ni fija precios; ejecútalo solo con autorización en producción.
 */
class SopasMenuCommand extends Command
{
    protected $signature = 'restaurante:sopas-menu {--menus= : Categoría cuyos platos llevan sopa a elección}';

    protected $description = 'Configura el grupo "Sopa del menú" con las sopas iniciales';

    public function handle(ConfigurarSopasMenuService $servicio, OpcionesCarta $opciones): int
    {
        $menus = [];
        if ($nombre = $this->option('menus')) {
            $platos = $opciones->platosDeCategoria($nombre);
            if ($platos === null) {
                $this->error("No existe la categoría «{$nombre}».");

                return self::FAILURE;
            }
            $menus = $platos->all();
        }

        $resumen = $servicio->ejecutar($menus);
        $this->info('Grupo «'.ConfigurarSopasMenuService::GRUPO."» listo (id {$resumen['grupo']}).");
        $this->line('Sopas reutilizadas: '.(implode(', ', $resumen['reutilizadas']) ?: 'ninguna'));
        $this->line('Sopas creadas (sin precio, no se venden solas): '.(implode(', ', $resumen['creadas']) ?: 'ninguna'));
        $this->line("Platos con sopa a elección: {$resumen['menus']}");

        return self::SUCCESS;
    }
}
