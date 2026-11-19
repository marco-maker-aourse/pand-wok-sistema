<?php

namespace App\Http\Requests\Catalogos;

use App\Models\UnidadMedida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnidadMedidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unidad = $this->route('unidad');
        $id = is_object($unidad) ? $unidad->id_unidad : $unidad;

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                // Evita el error 'Database connection [catalogos] not configured'
                Rule::unique(UnidadMedida::class, 'nombre')->ignore($id, 'id_unidad'),
            ],
            'codigo_sunat' => [
                'nullable',
                'string',
                'max:10',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'  => 'El nombre de la unidad de medida es obligatorio.',
            'nombre.unique'    => 'Esta unidad de medida ya se encuentra registrada.',
            'codigo_sunat.max' => 'El código oficial de SUNAT no debe superar los 10 caracteres.',
        ];
    }
}
