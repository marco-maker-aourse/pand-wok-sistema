<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\Producto;
use App\Services\Restaurante\ConfigurarSopasMenuService;
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

    public function handle(ConfigurarSopasMenuService $servicio): int
    {
        $menus = [];
        if ($nombre = $this->option('menus')) {
            $categoria = Categoria::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();
            if (! $categoria) {
                $this->error("No existe la categoría «{$nombre}».");

                return self::FAILURE;
            }
            $menus = Producto::query()->where('id_categoria', $categoria->id_categoria)
                ->where('tipo_producto', Producto::PREPARADO)->where('estado', true)->pluck('id_producto')->all();
        }

        $resumen = $servicio->ejecutar($menus);
        $this->info('Grupo «'.ConfigurarSopasMenuService::GRUPO."» listo (id {$resumen['grupo']}).");
        $this->line('Sopas reutilizadas: '.(implode(', ', $resumen['reutilizadas']) ?: 'ninguna'));
        $this->line('Sopas creadas (sin precio, no se venden solas): '.(implode(', ', $resumen['creadas']) ?: 'ninguna'));
        $this->line("Platos con sopa a elección: {$resumen['menus']}");

        return self::SUCCESS;
    }
}
