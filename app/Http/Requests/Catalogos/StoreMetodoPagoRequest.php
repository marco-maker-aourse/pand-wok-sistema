<?php

namespace App\Http\Requests\Catalogos;

use App\Models\MetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMetodoPagoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('metodoPago')?->id_metodo;

        return [
            'nombre' => ['required', 'string', 'max:50', Rule::unique(MetodoPago::class, 'nombre')->ignore($id, 'id_metodo')],
            'tipo' => ['required', Rule::in(['EFECTIVO', 'BILLETERA_DIRECTA', 'PASARELA'])],
            'requiere_referencia' => ['sometimes', 'boolean'],
            'comision_porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'comision_fija' => ['required', 'numeric', 'min:0'],
        ];
    }
}
