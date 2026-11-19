<?php

namespace App\Console\Commands;

use App\Models\Sucursal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Lista las sucursales y, opcionalmente, les asigna el identificador de la carta digital.
 *
 *   php artisan restaurante:sucursales
 *   php artisan restaurante:sucursales --asignar=chilca=1 --asignar=tambo=2 --horarios=database/data/carta/pand-wok.json
 *
 * Con --horarios, el horario de atención se toma de las sedes del archivo solo si la
 * sucursal aún no tiene uno. Es lo mismo que editar la sucursal en Configuración.
 */
class SucursalesCartaCommand extends Command
{
    protected $signature = 'restaurante:sucursales
        {--asignar=* : slug=id_sucursal (repetible)}
        {--horarios= : JSON de carta del que tomar el horario de cada sede}';

    protected $description = 'Lista sucursales y asigna su identificador de carta digital';

    public function handle(): int
    {
        $asignaciones = [];
        foreach ($this->option('asignar') as $par) {
            if (! preg_match('/^([a-z0-9]+(?:-[a-z0-9]+)*)=(\d+)$/', $par, $partes)) {
                $this->error("Formato inválido: «{$par}». Usa slug=id_sucursal (p. ej. el-tambo=2).");

                return self::FAILURE;
            }
            $asignaciones[$partes[1]] = (int) $partes[2];
        }

        if (count($asignaciones) !== count(array_unique($asignaciones))) {
            $this->error('Una misma sucursal no puede recibir dos identificadores.');

            return self::FAILURE;
        }

        $horarios = [];
        if ($archivo = $this->option('horarios')) {
            $ruta = is_file($archivo) ? $archivo : base_path($archivo);
            $datos = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
            if (! is_array($datos['sedes'] ?? null)) {
                $this->error("No se pudo leer las sedes de {$archivo}.");

                return self::FAILURE;
            }
            $horarios = array_map(fn ($sede) => is_array($sede) ? ($sede['horario'] ?? null) : null, $datos['sedes']);
        }

        if ($asignaciones) {
            $error = $this->asignar($asignaciones, $horarios);
            if ($error) {
                $this->error($error);

                return self::FAILURE;
            }
            $this->info('Identificadores de carta asignados.');
        }

        $this->table(
            ['ID', 'Sucursal', 'Identificador de carta', 'Horario', 'Activa'],
            Sucursal::query()->orderBy('id_sucursal')->get(['id_sucursal', 'nombre', 'slug', 'horario_atencion', 'estado'])
                ->map(fn (Sucursal $s) => [$s->id_sucursal, $s->nombre, $s->slug ?? '—', $s->horario_atencion ?? '—', $s->estado ? 'sí' : 'no'])
                ->all(),
        );

        return self::SUCCESS;
    }

    /** Valida todo antes de escribir; devuelve el error o null. */
    private function asignar(array $asignaciones, array $horarios): ?string
    {
        foreach ($asignaciones as $slug => $id) {
            $sucursal = Sucursal::query()->find($id);
            if (! $sucursal) {
                return "La sucursal {$id} no existe.";
            }
            $ocupado = Sucursal::query()->where('slug', $slug)->whereKeyNot($id)->first();
            // Solo se permite si esa otra sucursal también recibe un identificador nuevo aquí (intercambio).
            if ($ocupado && ! in_array((int) $ocupado->id_sucursal, $asignaciones, true)) {
                return "El identificador «{$slug}» ya pertenece a la sucursal {$ocupado->id_sucursal} ({$ocupado->nombre}).";
            }
        }

        DB::transaction(function () use ($asignaciones, $horarios): void {
            // Primero se liberan los identificadores que cambian de dueño (índice único).
            Sucursal::query()->whereIn('slug', array_keys($asignaciones))->update(['slug' => null]);
            foreach ($asignaciones as $slug => $id) {
                $sucursal = Sucursal::query()->find($id);
                $cambios = ['slug' => $slug];
                if (! $sucursal->horario_atencion && ! empty($horarios[$slug])) {
                    $cambios['horario_atencion'] = mb_substr((string) $horarios[$slug], 0, 80);
                }
                $sucursal->update($cambios);
            }
        });

        return null;
    }
}
