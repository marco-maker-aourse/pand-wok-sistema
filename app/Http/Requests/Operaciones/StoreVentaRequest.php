<?php

namespace App\Http\Requests\Operaciones;

use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\ProductoPresentacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Contrato de entrada del POS (POST /ventas).
 *
 * El servidor ignora precio y subtotal enviados por el navegador; solo valida
 * identificadores, cantidades y medios de pago. La autorización la aplica el
 * middleware `permission:ventas.registrar` de la ruta.
 */
class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_cliente' => ['required', 'integer', Rule::exists(Cliente::class, 'id_cliente')->where('estado', true)],
            // Compatibilidad temporal: los clientes antiguos envían un único id_metodo_pago.
            'id_metodo_pago' => ['nullable', 'integer', 'required_without:pagos', Rule::exists(MetodoPago::class, 'id_metodo')->where('estado', true)],
            'nro_referencia' => ['nullable', 'string', 'max:100'],
            'monto_recibido' => ['nullable', 'numeric', 'min:0', 'max:999999999.99', 'decimal:0,2'],
            'pagos' => ['nullable', 'array', 'min:1'],
            'pagos.*.id_metodo' => ['required_with:pagos', 'integer', Rule::exists(MetodoPago::class, 'id_metodo')->where('estado', true)],
            'pagos.*.monto' => ['required_with:pagos', 'numeric', 'gt:0', 'max:999999999.99', 'decimal:0,2'],
            'pagos.*.nro_referencia' => ['nullable', 'string', 'max:100'],
            'pagos.*.monto_recibido' => ['nullable', 'numeric', 'min:0', 'max:999999999.99', 'decimal:0,2'],
            'tipo_comprobante' => ['required', Rule::in(['BOLETA', 'FACTURA'])],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id_prod_pres' => ['required', 'integer', Rule::exists(ProductoPresentacion::class, 'id_precio')->where('estado', true)],
            // La unidad base puede ser fraccionable (kg, litro, metro). Se conserva
            // la misma precisión de inventario definida por la base de datos.
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
        ];
    }
}
