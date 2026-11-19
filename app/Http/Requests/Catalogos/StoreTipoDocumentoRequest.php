<?php

namespace App\Http\Requests\Catalogos;

use Illuminate\Foundation\Http\FormRequest;

class StoreTipoDocumentoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'codigo_sunat' => ['required', 'string', 'max:5'],
            'nombre' => ['required', 'string', 'max:100'],
            'longitud_exacta' => ['nullable', 'integer', 'min:1', 'max:99'],
            'tipo_persona' => ['nullable', 'string', 'max:20'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }
}
