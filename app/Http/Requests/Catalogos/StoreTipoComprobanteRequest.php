<?php

namespace App\Http\Requests\Catalogos;

use App\Models\TipoComprobante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTipoComprobanteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $voucherId = $this->route('tipoComprobante')?->id_tipo_comprobante;

        return [
            // Código de catálogo SUNAT; no es serie ni correlativo de la operación.
            'codigo_sunat' => ['required', 'string', 'max:5', Rule::unique(TipoComprobante::class, 'codigo_sunat')->ignore($voucherId, 'id_tipo_comprobante')],
            'descripcion' => ['required', 'string', 'max:150'],
            'exige_ruc' => ['sometimes', 'boolean'],
            'permite_anulacion' => ['sometimes', 'boolean'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }
}
