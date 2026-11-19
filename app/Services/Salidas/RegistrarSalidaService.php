<?php

namespace App\Services\Salidas;

use App\Models\ProductoPresentacion;
use App\Models\Salida;
use App\Services\Inventario\GestionarExistenciaService;
use App\Services\Inventario\OrigenMovimiento;
use Illuminate\Support\Facades\DB;

class RegistrarSalidaService
{
    public function __construct(private readonly GestionarExistenciaService $inventory) {}

    public function execute(array $data): Salida
    {
        return DB::transaction(function () use ($data): Salida {
            // Cabecera, detalle y Kardex se confirman o revierten juntos.
            $salida = Salida::create([
                ...collect($data)->except('detalles')->all(),
                'fecha_salida' => $data['fecha_salida'] ?? now(),
                'estado'       => $data['estado'] ?? 'REGISTRADO',
            ]);

            // Solo se persisten columnas reales de operaciones.detalle_salidas.
            foreach ($data['detalles'] as $detail) {
                $presentation = ProductoPresentacion::query()->with('producto')->findOrFail($detail['id_prod_pres']);

                $salida->detalles()->create([
                    'id_precio' => $presentation->id_precio,
                    'cantidad'     => (float) $detail['cantidad'],
                    'id_lote'       => filled($detail['lote'] ?? null)
                        ? \App\Models\Lote::query()
                            ->where('id_producto', $presentation->id_producto)
                            ->where('id_sucursal', $data['id_sucursal'])
                            ->where('codigo_lote', $detail['lote'])
                            ->value('id_lote')
                        : null,
                ]);

                // 3. Descontar del Kardex e Inventario físico
                if (! $presentation->producto->es_servicio) {
                    $this->inventory->decrease(
                        $presentation,
                        $data['id_sucursal'],
                        (float) $detail['cantidad'],
                        OrigenMovimiento::salida($salida),
                        $detail['lote'] ?? null
                    );
                }
            }

            return $salida->load(['detalles.presentacion.producto', 'detalles.presentacion.presentacion', 'usuario']);
        }, attempts: 3);
    }
}
