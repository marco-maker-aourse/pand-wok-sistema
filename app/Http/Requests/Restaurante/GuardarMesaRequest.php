<?php

namespace App\Http\Requests\Restaurante;

use Illuminate\Foundation\Http\FormRequest;

/** Forma de la mesa; pertenencia del área y código único se validan en el servicio. */
class GuardarMesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_area' => ['required', 'integer'],
            'codigo' => ['required', 'string', 'max:20'],
            'capacidad' => ['required', 'integer', 'min:1', 'max:50'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['id_area' => 'área', 'codigo' => 'código'];
    }
}
