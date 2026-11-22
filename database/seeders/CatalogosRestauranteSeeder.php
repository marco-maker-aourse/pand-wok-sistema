<?php

namespace Database\Seeders;

use App\Models\MetodoPago;
use App\Models\Presentacion;
use App\Services\Ventas\CatalogoPosService;
use Illuminate\Database\Seeder;

/**
 * Catálogos operativos con los que el restaurante puede cobrar desde el primer día.
 * Es repetible: busca por nombre y solo crea lo que falta.
 */
class CatalogosRestauranteSeeder extends Seeder
{
    /**
     * Las comisiones de tarjeta dependen del contrato con el banco o la pasarela:
     * quedan en cero y se configuran en Métodos de pago.
     */
    private const METODOS_PAGO = [
        ['nombre' => 'Efectivo', 'tipo' => MetodoPago::EFECTIVO, 'requiere_referencia' => false],
        ['nombre' => 'Yape', 'tipo' => MetodoPago::BILLETERA_DIRECTA, 'requiere_referencia' => true],
        ['nombre' => 'Plin', 'tipo' => MetodoPago::BILLETERA_DIRECTA, 'requiere_referencia' => true],
        ['nombre' => 'Transferencia', 'tipo' => MetodoPago::BILLETERA_DIRECTA, 'requiere_referencia' => true],
        ['nombre' => 'Tarjeta de débito', 'tipo' => MetodoPago::PASARELA, 'requiere_referencia' => true],
        ['nombre' => 'Tarjeta de crédito', 'tipo' => MetodoPago::PASARELA, 'requiere_referencia' => true],
    ];

    /** Tamaños y empaques habituales de platos y bebidas. */
    private const PRESENTACIONES = [
        'Unidad', 'Porción', 'Media porción', 'Personal', 'Mediano', 'Familiar',
        'Vaso', 'Jarra', 'Botella 500 ml', 'Botella 1 L', 'Botella 1.5 L', 'Botella 3 L', 'Lata',
        'Kilogramo', 'Paquete', 'Caja',
    ];

    public function run(): void
    {
        foreach (self::METODOS_PAGO as $metodo) {
            if (! MetodoPago::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($metodo['nombre'])])->exists()) {
                MetodoPago::query()->create([...$metodo, 'comision_porcentaje' => 0, 'comision_fija' => 0, 'estado' => true]);
            }
        }

        foreach (self::PRESENTACIONES as $nombre) {
            if (! Presentacion::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->exists()) {
                Presentacion::query()->create(['nombre' => $nombre, 'estado' => true]);
            }
        }

        // Cliente de las boletas sin datos del comprador; misma regla que usa el POS.
        app(CatalogoPosService::class)->clienteGenerico();
    }
}
