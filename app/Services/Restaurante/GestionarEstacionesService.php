<?php

namespace App\Services\Restaurante;

use App\Models\Impresora;
use App\Models\Restaurante\Estacion;
use App\Models\TerminalPos;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Estaciones de preparación por sucursal y a qué estación va cada categoría. */
class GestionarEstacionesService
{
    public function guardar(array $datos, int $sucursalId, ?int $estacionId = null): Estacion
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw new DomainException('La estación necesita un nombre.');
        }

        $impresora = isset($datos['id_impresora']) ? (int) $datos['id_impresora'] : null;
        $terminal = isset($datos['id_terminal']) ? (int) $datos['id_terminal'] : null;
        if ($terminal && ! $impresora) {
            throw new DomainException('Selecciona una impresora para la terminal.');
        }
        if ($impresora) {
            $dispositivo = Impresora::query()->where('id_sucursal', $sucursalId)->find($impresora);
            if (! $dispositivo) {
                throw new DomainException('La impresora no pertenece a esta sucursal.');
            }
            if (! $terminal && ! $dispositivo->cloudprnt_clave_hash) {
                throw new DomainException('Elige una terminal o vincula la impresora con CloudPRNT.');
            }
            if (! $terminal && (int) ($datos['copias'] ?? 1) !== 1) {
                throw new DomainException('CloudPRNT admite una copia automática por comanda.');
            }
        }
        if ($terminal && ! TerminalPos::query()->where('id_sucursal', $sucursalId)->whereKey($terminal)->exists()) {
            throw new DomainException('La terminal no pertenece a esta sucursal.');
        }

        $valores = [
            'nombre' => $nombre,
            'id_impresora' => $impresora,
            'id_terminal' => $terminal,
            'copias' => max(1, min(5, (int) ($datos['copias'] ?? 1))),
            'por_defecto' => (bool) ($datos['por_defecto'] ?? false) && (bool) ($datos['activo'] ?? true),
            'orden' => (int) ($datos['orden'] ?? 0),
            'activo' => (bool) ($datos['activo'] ?? true),
        ];

        return DB::transaction(function () use ($valores, $sucursalId, $estacionId): Estacion {
            DB::table('inventario.sucursales')->where('id_sucursal', $sucursalId)->lockForUpdate()->first(['id_sucursal']);
            $duplicada = Estacion::query()->where('id_sucursal', $sucursalId)
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($valores['nombre'])])
                ->when($estacionId, fn ($query) => $query->whereKeyNot($estacionId))
                ->exists();
            if ($duplicada) {
                throw new DomainException("Ya existe la estación «{$valores['nombre']}» en esta sucursal.");
            }

            // Solo una estación por defecto por sucursal.
            if ($valores['por_defecto']) {
                Estacion::query()->where('id_sucursal', $sucursalId)
                    ->when($estacionId, fn ($query) => $query->whereKeyNot($estacionId))
                    ->update(['por_defecto' => false]);
            }

            if ($estacionId === null) {
                return Estacion::query()->create([...$valores, 'id_sucursal' => $sucursalId]);
            }

            $estacion = Estacion::query()->where('id_sucursal', $sucursalId)->lockForUpdate()->find($estacionId);
            if (! $estacion) {
                throw new DomainException('La estación no existe en esta sucursal.');
            }
            $estacion->update($valores);

            return $estacion;
        });
    }

    /**
     * Fija las categorías que prepara una estación. Una categoría solo puede estar en una
     * estación por sucursal: asignarla aquí la quita de la anterior.
     *
     * @param  array<int>  $categoriaIds
     */
    public function asignarCategorias(int $estacionId, array $categoriaIds, int $sucursalId): void
    {
        $categoriaIds = array_values(array_unique(array_map('intval', $categoriaIds)));
        $estacion = Estacion::query()->where('id_sucursal', $sucursalId)->find($estacionId);
        if (! $estacion) {
            throw new DomainException('La estación no existe en esta sucursal.');
        }

        DB::transaction(function () use ($estacion, $categoriaIds, $sucursalId): void {
            DB::table('inventario.sucursales')->where('id_sucursal', $sucursalId)->lockForUpdate()->first(['id_sucursal']);
            DB::table('restaurante.estacion_categorias')
                ->where('id_sucursal', $sucursalId)
                ->where(fn ($query) => $query->where('id_estacion', $estacion->id_estacion)->orWhereIn('id_categoria', $categoriaIds))
                ->delete();

            DB::table('restaurante.estacion_categorias')->insert(array_map(fn (int $categoria): array => [
                'id_sucursal' => $sucursalId,
                'id_categoria' => $categoria,
                'id_estacion' => $estacion->id_estacion,
            ], $categoriaIds));
        });
    }

    /** Estaciones con sus categorías y datos de impresión, para la pantalla de configuración. */
    public function listado(int $sucursalId): array
    {
        $categorias = DB::table('restaurante.estacion_categorias')->where('id_sucursal', $sucursalId)->get()->groupBy('id_estacion');

        return Estacion::query()->where('id_sucursal', $sucursalId)
            ->with(['impresora:id_impresora,nombre,activo,estado_conexion', 'terminal:id_terminal,nombre,activo,vinculado_en,ultimo_contacto_en'])
            ->orderBy('orden')->orderBy('nombre')
            ->get()
            ->map(fn (Estacion $estacion): array => [
                ...$estacion->only(['id_estacion', 'nombre', 'id_impresora', 'id_terminal', 'copias', 'por_defecto', 'orden', 'activo']),
                'impresora' => $estacion->impresora?->only(['id_impresora', 'nombre', 'activo', 'estado_conexion']),
                'terminal' => $estacion->terminal ? [
                    'nombre' => $estacion->terminal->nombre,
                    'vinculada' => (bool) $estacion->terminal->vinculado_en,
                    'ultimo_contacto_en' => $estacion->terminal->ultimo_contacto_en?->toIso8601String(),
                ] : null,
                'categorias' => ($categorias[$estacion->id_estacion] ?? collect())->pluck('id_categoria')->map(fn ($id) => (int) $id)->values()->all(),
            ])->all();
    }
}
