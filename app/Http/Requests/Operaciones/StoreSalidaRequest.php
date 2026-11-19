<?php

namespace App\Http\Requests\Operaciones;

use App\Models\ProductoPresentacion;
use App\Models\TipoComprobante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo'                  => ['required', 'string', 'max:50'],
            'observacion'             => ['required', 'string'],
            'fecha_salida'            => ['nullable', 'date'],
            'id_tipo_comprobante'     => ['nullable', 'integer', Rule::exists(TipoComprobante::class, 'id_tipo_comprobante')],
            'serie_comprobante'       => ['nullable', 'string', 'max:10'],
            'correlativo_comprobante' => ['nullable'],
            
            // Validación de los productos dados de baja
            'detalles'                => ['required', 'array', 'min:1'],
            // Corregido: uso de clase de Modelo para evitar 'Database connection [catalogos] not configured'
            'detalles.*.id_prod_pres' => ['required', 'integer', Rule::exists(ProductoPresentacion::class, 'id_prod_pres')],
            'detalles.*.cantidad'     => ['required', 'numeric', 'gt:0'],
            'detalles.*.lote'         => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required'                  => 'El motivo de la salida es obligatorio.',
            'observacion.required'             => 'Debe ingresar una justificación u observación para auditoría de Kardex.',
            'detalles.required'                => 'Debe seleccionar al menos un producto para dar de baja.',
            'detalles.min'                     => 'Debe seleccionar al menos un producto para dar de baja.',
            'detalles.*.id_prod_pres.required' => 'La presentación del producto es requerida.',
            'detalles.*.cantidad.gt'           => 'La cantidad a descontar debe ser mayor a 0.',
        ];
    }
}
