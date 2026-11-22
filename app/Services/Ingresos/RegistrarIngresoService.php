<?php

namespace App\Services\Ingresos;

use App\Models\Ingreso;
use App\Models\ProductoPresentacion;
use App\Services\Inventario\GestionarExistenciaService;
use App\Services\Inventario\OrigenMovimiento;
use DomainException;
use Illuminate\Support\Facades\DB;

class RegistrarIngresoService
{
    public function __construct(private readonly GestionarExistenciaService $inventory) {}

    public function execute(array $data): Ingreso
    {
        return DB::transaction(function () use ($data): Ingreso {
            $total = 0.0;
            foreach ($data['detalles'] as $detail) {
                $total += (float) $detail['cantidad'] * (float) $detail['precio_costo'];
            }

            // La cabecera y el Kardex deben confirmarse como una única operación ACID.
            $ingreso = Ingreso::create([
                ...collect($data)->except('detalles')->all(),
                'impuesto_total' => 0,
                'total_ingreso' => round($total, 2),
                'estado' => 'REGISTRADO',
            ]);

            foreach ($data['detalles'] as $detail) {
                $presentation = ProductoPresentacion::query()->with('producto')->findOrFail($detail['id_prod_pres']);
                $subtotal = round((float) $detail['cantidad'] * (float) $detail['precio_costo'], 2);

                $ingreso->detalles()->create([
                    'id_precio'         => $presentation->id_precio,
                    'cantidad'          => (float) $detail['cantidad'],
                    'factor_conversion' => $presentation->factor_conversion,
                    'precio_costo'      => (float) $detail['precio_costo'],
                    'subtotal'          => $subtotal,
                ]);

                // El precio de venta se administra por presentación; el costo queda trazado en ingreso/Kardex.
                if (filled($detail['precio_venta'] ?? null) && (float) $detail['precio_venta'] > 0) {
                    $costoUnitario = (float) $detail['precio_costo'] / max((float) $presentation->factor_conversion, 1);
                    $precioVenta   = (float) $detail['precio_venta'];

                    if ($precioVenta <= $costoUnitario) {
                        throw new DomainException('El precio de venta no puede ser menor al costo unitario');
                    }

                    $presentation->update(['precio_venta' => $precioVenta]);
                }

                // Actualizar el stock en el inventario base.
                $baseCost = (float) $detail['precio_costo'] / max((float) $presentation->factor_conversion, 1);
                $this->inventory->increase(
                    $presentation,
                    $data['id_sucursal'],
                    (float) $detail['cantidad'],
                    OrigenMovimiento::ingreso($ingreso),
                    $baseCost
                );
            }

            return $ingreso->load('detalles');
        }, attempts: 3);
    }
}
