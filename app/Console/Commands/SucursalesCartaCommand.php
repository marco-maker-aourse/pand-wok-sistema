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
 *
 * Con --completar (requiere --horarios), las sucursales asignadas toman nombre, dirección
 * y horario de su sede en el archivo, y cada sede sin sucursal se asocia a la sucursal
 * del mismo nombre o, si no existe, se crea.
 */
class SucursalesCartaCommand extends Command
{
    protected $signature = 'restaurante:sucursales
        {--asignar=* : slug=id_sucursal (repetible)}
        {--horarios= : JSON de carta del que tomar el horario de cada sede}
        {--completar : Toma nombre y dirección de la carta y crea las sedes que falten}';

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
        $sedes = [];
        if ($archivo = $this->option('horarios')) {
            $ruta = is_file($archivo) ? $archivo : base_path($archivo);
            $datos = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
            if (! is_array($datos['sedes'] ?? null)) {
                $this->error("No se pudo leer las sedes de {$archivo}.");

                return self::FAILURE;
            }
            $sedes = array_filter($datos['sedes'], fn ($sede, $slug) => is_array($sede) && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', (string) $slug), ARRAY_FILTER_USE_BOTH);
            $horarios = array_map(fn (array $sede) => $sede['horario'] ?? null, $sedes);
        }

        if ($this->option('completar') && $sedes === []) {
            $this->error('--completar necesita --horarios con el archivo de la carta.');

            return self::FAILURE;
        }

        if ($asignaciones) {
            $error = $this->asignar($asignaciones, $horarios);
            if ($error) {
                $this->error($error);

                return self::FAILURE;
            }
            $this->info('Identificadores de carta asignados.');
        }

        if ($this->option('completar')) {
            $this->completar($sedes);
        }

        $this->table(
            ['ID', 'Sucursal', 'Identificador de carta', 'Horario', 'Activa'],
            Sucursal::query()->orderBy('id_sucursal')->get(['id_sucursal', 'nombre', 'slug', 'horario_atencion', 'estado'])
                ->map(fn (Sucursal $s) => [$s->id_sucursal, $s->nombre, $s->slug ?? '—', $s->horario_atencion ?? '—', $s->estado ? 'sí' : 'no'])
                ->all(),
        );

        return self::SUCCESS;
    }

    /**
     * Las sucursales con identificador toman nombre, dirección y horario de su sede; las
     * sedes sin sucursal se asocian por nombre o se crean.
     *
     * @param  array<string, array{nombre?:string, direccion?:string, horario?:string}>  $sedes
     */
    private function completar(array $sedes): void
    {
        DB::transaction(function () use ($sedes): void {
            foreach ($sedes as $slug => $sede) {
                $nombre = mb_strtoupper(trim((string) ($sede['nombre'] ?? $slug)));
                $datos = array_filter([
                    'direccion' => isset($sede['direccion']) ? trim((string) $sede['direccion']) : null,
                    'horario_atencion' => isset($sede['horario']) ? mb_substr(trim((string) $sede['horario']), 0, 80) : null,
                ], fn ($valor) => $valor !== null && $valor !== '');

                $sucursal = Sucursal::query()->where('slug', $slug)->first()
                    ?? Sucursal::query()->whereNull('slug')->whereRaw('UPPER(nombre) = ?', [$nombre])->first();

                if ($sucursal) {
                    // No se renombra si otra sucursal ya usa ese nombre.
                    $nombreLibre = ! Sucursal::query()->whereRaw('UPPER(nombre) = ?', [$nombre])->whereKeyNot($sucursal->id_sucursal)->exists();
                    $sucursal->update([...$datos, 'slug' => $slug, ...($nombreLibre ? ['nombre' => $nombre] : [])]);
                    $this->line("Sucursal {$sucursal->id_sucursal} actualizada como «{$slug}».");

                    continue;
                }

                $nueva = Sucursal::query()->create([
                    ...$datos,
                    'nombre' => $nombre,
                    'direccion' => $datos['direccion'] ?? $nombre,
                    'slug' => $slug,
                    'estado' => true,
                ]);
                $this->line("Sucursal {$nueva->id_sucursal} creada: {$nombre} («{$slug}»).");
            }
        });
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
