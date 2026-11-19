<?php

namespace App\Http\Requests\Restaurante;

use Illuminate\Foundation\Http\FormRequest;

/** Forma del área; duplicados por sucursal se validan en GestionarSalonService. */
class GuardarAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:60'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
