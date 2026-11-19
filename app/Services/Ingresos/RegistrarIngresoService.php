<?php

namespace App\Services\Ingresos;

use App\Models\Ingreso;
use App\Models\Lote;
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

                // Solo se persisten columnas definidas en operaciones.detalle_ingresos.
                $fechaVencimiento = filled($detail['fecha_vencimiento'] ?? null) ? $detail['fecha_vencimiento'] : null;

                // Validación: fecha de vencimiento no puede ser anterior a la fecha actual.
                if (filled($fechaVencimiento) && $fechaVencimiento < now()->toDateString()) {
                    throw new DomainException('La fecha de vencimiento no puede ser anterior a la fecha actual');
                }

                // Validación: si el producto usa vencimiento, la fecha es obligatoria.
                if ($presentation->producto->usa_vencimiento === true && !filled($fechaVencimiento)) {
                    throw new DomainException('Este producto requiere una fecha de vencimiento');
                }

                // 2. Insertar únicamente las columnas reales de operaciones.detalle_ingresos
                $detailRecord = $ingreso->detalles()->create([
                    'id_precio'         => $presentation->id_precio,
                    'id_lote'           => null,
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

                    $porcentajeUtilidad = (($precioVenta - $costoUnitario) / $costoUnitario) * 100;

                    $presentation->update([
                        'precio_venta'        => $precioVenta,
                        'porcentaje_utilidad' => round($porcentajeUtilidad, 2),
                    ]);
                }

                // 4. Actualizar stock físico en inventario base
                $baseCost = (float) $detail['precio_costo'] / max((float) $presentation->factor_conversion, 1);
                $this->inventory->increase(
                    $presentation,
                    $data['id_sucursal'],
                    (float) $detail['cantidad'],
                    OrigenMovimiento::ingreso($ingreso),
                    $baseCost
                );

                // 5. Asignar lote para rotación FIFO (Primeras Entradas, Primeras Salidas)
                if (filled($detail['lote'] ?? null)) {
                    $baseQuantity = $this->inventory->toBaseQuantity($presentation, (float) $detail['cantidad']);
                    $lot = Lote::query()
                        ->where('id_producto', $presentation->id_producto)
                        ->where('id_sucursal', $data['id_sucursal'])
                        ->where('codigo_lote', $detail['lote'])
                        ->lockForUpdate()
                        ->first();

                    if ($lot) {
                        $lot->increment('stock', $baseQuantity);
                    } else {
                        $lot = Lote::create([
                            'id_producto'       => $presentation->id_producto,
                            'id_sucursal'       => $data['id_sucursal'],
                            'codigo_lote'       => $detail['lote'],
                            'fecha_vencimiento' => $fechaVencimiento,
                            'stock'             => $baseQuantity,
                            'estado'            => true,
                        ]);
                    }

                    $detailRecord->update(['id_lote' => $lot->id_lote]);
                }
            }

            return $ingreso->load('detalles');
        }, attempts: 3);
    }
}
