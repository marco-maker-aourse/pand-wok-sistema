<?php

namespace App\Console\Commands;

use App\Services\Restaurante\ImportarCartaService;
use DomainException;
use Illuminate\Console\Command;

/**
 * php artisan restaurante:importar-carta database/data/carta/pand-wok.json --simular
 * php artisan restaurante:importar-carta database/data/carta/pand-wok.json --sede=chilca=1 --sede=tambo=2
 *
 * Sin --sede, cada sede del archivo se asocia a la sucursal con el mismo slug.
 */
class ImportarCartaCommand extends Command
{
    protected $signature = 'restaurante:importar-carta
        {archivo : Ruta del JSON con sedes y carta}
        {--sede=* : Asociación explícita slug=id_sucursal (repetible)}
        {--simular : Muestra el resultado sin guardar cambios}';

    protected $description = 'Importa o actualiza la carta (categorías, platos y precios por sucursal) desde un JSON';

    public function handle(ImportarCartaService $importador): int
    {
        $ruta = $this->argument('archivo');
        $ruta = is_file($ruta) ? $ruta : base_path($ruta);
        if (! is_file($ruta)) {
            $this->error("No se encontró el archivo {$this->argument('archivo')}.");

            return self::FAILURE;
        }

        $datos = json_decode((string) file_get_contents($ruta), true);
        if (! is_array($datos)) {
            $this->error('El archivo no es un JSON válido.');

            return self::FAILURE;
        }

        $mapa = [];
        foreach ($this->option('sede') as $par) {
            if (! preg_match('/^([a-z0-9-]+)=(\d+)$/', $par, $partes)) {
                $this->error("Formato de --sede inválido: «{$par}». Usa slug=id_sucursal.");

                return self::FAILURE;
            }
            $mapa[$partes[1]] = (int) $partes[2];
        }

        try {
            $resumen = $importador->importar($datos, $mapa, (bool) $this->option('simular'));
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Sede del archivo', 'Sucursal del sistema'], collect($resumen['sedes'])
            ->map(fn (?string $sucursal, string $slug) => [$slug, $sucursal ?? '— sin asociar (sus precios no se importan)'])
            ->values()->all());

        $this->table(['Resultado', 'Cantidad'], [
            ['Categorías', $resumen['categorias']],
            ['Platos nuevos', $resumen['creados']],
            ['Platos actualizados', $resumen['actualizados']],
            ['Precios por sucursal', $resumen['precios_sucursal']],
            ['Platos no vendidos en una sucursal', $resumen['no_disponibles']],
        ]);

        $resumen['simulado']
            ? $this->warn('Simulación: no se guardó ningún cambio.')
            : $this->info('Carta importada.');

        return self::SUCCESS;
    }
}
