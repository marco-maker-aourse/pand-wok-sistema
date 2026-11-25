<?php

namespace App\Services\Restaurante;

use Illuminate\Support\Facades\DB;

/**
 * Grupo "Sopa del menú": el menú puede ir con o sin sopa y el precio no cambia
 * (opcional, una sola sopa, sin costo adicional).
 *
 * Repetible: reutiliza las sopas existentes (sin distinguir mayúsculas ni tildes) y, si un
 * grupo anterior la exigía, la vuelve opcional.
 */
class ConfigurarSopasMenuService
{
    public const GRUPO = 'Sopa del menú';

    /** Nombre con el que se crea la sopa si no existe => prefijo normalizado para buscarla. */
    public const SOPAS = [
        'Sopa de pollo' => 'sopa de pollo',
        'Sopa de kion' => 'sopa de kion',
        'Sopa wantán' => 'sopa wantan',
        'Sopa de patita' => 'sopa de patita',
    ];

    public function __construct(private readonly OpcionesCarta $opciones = new OpcionesCarta) {}

    /** @return array{grupo:int, reutilizadas:array<string>, creadas:array<string>, menus:int} */
    public function ejecutar(array $menus = []): array
    {
        return DB::transaction(function () use ($menus): array {
            $grupo = $this->opciones->grupo(self::GRUPO, 0, 1);
            if ($grupo->seleccion_minima > 0) {
                $grupo->update(['seleccion_minima' => 0]);
            }

            $reutilizadas = [];
            $creadas = [];
            $orden = 0;
            foreach (self::SOPAS as $nombre => $prefijo) {
                [$precio, $creada] = $this->opciones->sopa($nombre, $prefijo);
                $creada ? $creadas[] = $nombre : $reutilizadas[] = $precio->producto->nombre;
                $this->opciones->opcionProducto($grupo, $precio, 0, ++$orden);
            }

            $this->opciones->asignar($grupo, $menus);

            return ['grupo' => $grupo->id_grupo, 'reutilizadas' => $reutilizadas, 'creadas' => $creadas, 'menus' => count($menus)];
        });
    }

    public static function normalizar(string $texto): string
    {
        return OpcionesCarta::normalizar($texto);
    }
}
