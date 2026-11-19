<?php

namespace App\Http\Requests\Operaciones;

use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_proveedor'        => ['required', Rule::exists(Proveedor::class, 'id_proveedor')],
            'id_tipo_comprobante' => ['nullable'],
            'tipo_comprobante'    => ['required', 'string', 'max:50'],
            'serie_comprobante'   => ['required', 'string', 'max:10'],
            'num_comprobante'     => ['required', 'string', 'max:20'],
            'fecha_emision'       => ['required', 'date'],

            'detalles'                      => ['required', 'array', 'min:1'],
            'detalles.*.id_producto'        => ['required', Rule::exists(Producto::class, 'id_producto')],
            'detalles.*.id_prod_pres'       => ['required'], // Obligatorio para asegurar la trazabilidad del empaque
            'detalles.*.cantidad'           => ['required', 'numeric', 'min:1'],
            'detalles.*.precio_costo'       => ['required', 'numeric', 'min:0'],
            'detalles.*.precio_venta'       => ['nullable', 'numeric', 'min:0'],
            'detalles.*.lote'               => ['nullable', 'string', 'max:50'],
            'detalles.*.fecha_vencimiento'  => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_proveedor.required'  => 'Debe seleccionar un proveedor válido.',
            'id_proveedor.exists'    => 'El proveedor seleccionado no existe en los registros.',
            'num_comprobante.required' => 'Ingrese el número del comprobante de compra.',
            'detalles.required'      => 'Agregue al menos un producto a la orden de compra.',
        ];
    }
}
