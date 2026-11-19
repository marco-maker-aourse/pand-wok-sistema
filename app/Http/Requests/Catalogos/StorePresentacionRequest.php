<?php

namespace App\Http\Requests\Catalogos;

use App\Models\Presentacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePresentacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $presentacion = $this->route('presentacion');
        $id = is_object($presentacion) ? $presentacion->id_presentacion : $presentacion;

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                // Uso directo del modelo para no disparar el error de conexión
                Rule::unique(Presentacion::class, 'nombre')->ignore($id, 'id_presentacion'),
            ],
            'estado' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del empaque es obligatorio.',
            'nombre.unique'   => 'Este empaque ya se encuentra registrado.',
        ];
    }
}
