<?php

namespace App\Services\Restaurante;

use Illuminate\Support\Facades\DB;

/**
 * Grupo "Adicional de combinación": a una combinación se le puede agregar una sopa o
 * 3 wantanes fritos por un precio adicional (S/ 1.00 por defecto). Es opcional y de una
 * sola elección. El precio se ajusta por sucursal en la carta (opciones por sucursal).
 *
 * Repetible: no duplica opciones ni cambia precios ya ajustados.
 */
class ConfigurarAdicionalesCombinacionService
{
    public const GRUPO = 'Adicional de combinación';

    public const PRECIO = 1.00;

    public const WANTANES = '3 wantanes fritos';

    /** Sopas que se ofrecen como adicional: nombre si se crea => prefijo para buscarla. */
    public const SOPAS = [
        'Sopa wantán' => 'sopa wantan',
        'Sopa de pollo' => 'sopa de pollo',
        'Sopa de kion' => 'sopa de kion',
    ];

    public function __construct(private readonly OpcionesCarta $opciones = new OpcionesCarta) {}

    /** @return array{grupo:int, opciones:array<string>, combinaciones:int} */
    public function ejecutar(array $combinaciones = []): array
    {
        return DB::transaction(function () use ($combinaciones): array {
            $grupo = $this->opciones->grupo(self::GRUPO, 0, 1);

            $orden = 0;
            $nombres = [$this->opciones->opcionTexto($grupo, self::WANTANES, self::PRECIO, ++$orden)->nombre];
            foreach (self::SOPAS as $nombre => $prefijo) {
                [$precio] = $this->opciones->sopa($nombre, $prefijo);
                $this->opciones->opcionProducto($grupo, $precio, self::PRECIO, ++$orden);
                $nombres[] = $precio->producto->nombre;
            }

            $this->opciones->asignar($grupo, $combinaciones);

            return ['grupo' => $grupo->id_grupo, 'opciones' => $nombres, 'combinaciones' => count($combinaciones)];
        });
    }
}
