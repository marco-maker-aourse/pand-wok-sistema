<?php

namespace App\Console\Commands;

use App\Models\SerieSucursal;
use App\Models\Sucursal;
use Illuminate\Console\Command;

/**
 * Deja lista una base recién instalada (`migrate:fresh --seed`) con la carta del restaurante:
 *
 *   php artisan restaurante:preparar
 *
 * 1. Asocia la sucursal inicial del seeder a la primera sede de la carta y crea las demás.
 * 2. Importa categorías, platos y precios por sucursal.
 * 3. Configura la sopa opcional de los menús y el adicional de las combinaciones.
 * 4. Asigna series por defecto (B00n / F00n) a las sucursales que aún no tienen.
 *
 * Es repetible: no duplica platos ni sucursales y nunca cambia series ya configuradas.
 */
class PrepararRestauranteCommand extends Command
{
    protected $signature = 'restaurante:preparar
        {--carta=database/data/carta/pand-wok.json : JSON con sedes y carta}
        {--sede-inicial=chilca : Sede de la carta que corresponde a la sucursal inicial}
        {--menus=Menu : Categoría cuyos platos llevan sopa a elección}
        {--combinaciones=Combinaciones : Categoría cuyos platos aceptan sopa o wantanes como adicional}';

    protected $description = 'Carga sucursales, carta, opciones de menú y combinaciones, y series por defecto en una base nueva';

    public function handle(): int
    {
        $carta = (string) $this->option('carta');
        $inicial = Sucursal::query()->orderBy('id_sucursal')->value('id_sucursal');

        $pasos = [
            ['restaurante:sucursales', array_filter([
                '--asignar' => $inicial && ! Sucursal::query()->where('slug', $this->option('sede-inicial'))->exists()
                    ? [$this->option('sede-inicial').'='.$inicial] : [],
                '--horarios' => $carta,
                '--completar' => true,
            ])],
            ['restaurante:importar-carta', ['archivo' => $carta]],
            ['restaurante:sopas-menu', ['--menus' => $this->option('menus')]],
            ['restaurante:adicionales-combinacion', ['--combinaciones' => $this->option('combinaciones')]],
        ];

        foreach ($pasos as [$comando, $argumentos]) {
            $this->components->info($comando);
            if ($this->call($comando, $argumentos) !== self::SUCCESS) {
                $this->components->error("Falló {$comando}; no se continuó.");

                return self::FAILURE;
            }
        }

        $this->asignarSeriesFaltantes();

        return self::SUCCESS;
    }

    /** Series por defecto según el ID de la sucursal; se editan después en Datos generales. */
    private function asignarSeriesFaltantes(): void
    {
        $filas = [];
        foreach (Sucursal::query()->where('estado', true)->orderBy('id_sucursal')->get() as $sucursal) {
            $serie = SerieSucursal::query()->find($sucursal->id_sucursal);
            if ($serie?->serie_boleta || $serie?->serie_factura) {
                $filas[] = [$sucursal->nombre, $serie->serie_boleta ?? '—', $serie->serie_factura ?? '—', 'ya tenía'];

                continue;
            }

            [$boleta, $factura] = $this->seriesLibres($sucursal->id_sucursal);
            SerieSucursal::query()->updateOrCreate(['id_sucursal' => $sucursal->id_sucursal], [
                'serie_boleta' => $boleta, 'serie_factura' => $factura,
            ]);
            $filas[] = [$sucursal->nombre, $boleta, $factura, 'asignada'];
        }

        $this->table(['Sucursal', 'Boleta', 'Factura', 'Series'], $filas);
        $this->warn('Revisa que estas series sean las registradas ante SUNAT (Datos generales).');
    }

    /** @return array{0:string,1:string} */
    private function seriesLibres(int $desde): array
    {
        $usadas = SerieSucursal::query()->get(['serie_boleta', 'serie_factura', 'serie_guia'])
            ->flatMap(fn (SerieSucursal $s) => [$s->serie_boleta, $s->serie_factura, $s->serie_guia])
            ->filter()->all();

        for ($n = $desde; $n <= 999; $n++) {
            $sufijo = str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            if (! in_array("B{$sufijo}", $usadas, true) && ! in_array("F{$sufijo}", $usadas, true)) {
                return ["B{$sufijo}", "F{$sufijo}"];
            }
        }

        throw new \RuntimeException('No quedan series libres.');
    }
}
