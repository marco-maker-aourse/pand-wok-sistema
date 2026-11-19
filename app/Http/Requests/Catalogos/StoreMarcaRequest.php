<?php

namespace App\Http\Requests\Catalogos;

use App\Models\Marca;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMarcaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $marca = $this->route('marca');
        $id = is_object($marca) ? $marca->id_marca : $marca;

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                // Evita el error 'Database connection [catalogos] not configured'
                Rule::unique(Marca::class, 'nombre')->ignore($id, 'id_marca'),
            ],
            'estado' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la marca es obligatorio.',
            'nombre.unique'   => 'Ya existe una marca registrada con este mismo nombre.',
            'nombre.max'      => 'El nombre no debe superar los 100 caracteres.',
        ];
    }
}
