<?php

namespace App\Console\Commands;

use App\Services\Restaurante\ConfigurarAdicionalesCombinacionService;
use App\Services\Restaurante\OpcionesCarta;
use Illuminate\Console\Command;

/**
 * Crea el grupo "Adicional de combinación" (sopa o 3 wantanes fritos, + S/ 1.00) y lo agrega
 * a los platos de la categoría indicada. El precio se ajusta luego por sucursal en la carta.
 *
 *   php artisan restaurante:adicionales-combinacion --combinaciones=Combinaciones
 */
class AdicionalesCombinacionCommand extends Command
{
    protected $signature = 'restaurante:adicionales-combinacion {--combinaciones=Combinaciones : Categoría cuyos platos aceptan el adicional}';

    protected $description = 'Configura el adicional de las combinaciones (sopa o 3 wantanes fritos)';

    public function handle(ConfigurarAdicionalesCombinacionService $servicio, OpcionesCarta $opciones): int
    {
        $nombre = (string) $this->option('combinaciones');
        $platos = $opciones->platosDeCategoria($nombre);
        if ($platos === null) {
            $this->error("No existe la categoría «{$nombre}».");

            return self::FAILURE;
        }

        $resumen = $servicio->ejecutar($platos->all());
        $this->info('Grupo «'.ConfigurarAdicionalesCombinacionService::GRUPO."» listo (id {$resumen['grupo']}).");
        $this->line('Opciones: '.implode(', ', $resumen['opciones']));
        $this->line("Combinaciones con adicional: {$resumen['combinaciones']}");

        return self::SUCCESS;
    }
}
