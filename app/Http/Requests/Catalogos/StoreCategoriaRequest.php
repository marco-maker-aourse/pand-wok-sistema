<?php

namespace App\Http\Requests\Catalogos;

use App\Models\Categoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoria = $this->route('categoria');
        $id = is_object($categoria) ? $categoria->id_categoria : $categoria;

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                // Evita que Laravel busque una conexión externa 'catalogos'
                Rule::unique(Categoria::class, 'nombre')->ignore($id, 'id_categoria'),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'estado'      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'nombre.unique'   => 'Ya existe una categoría registrada con este nombre.',
            'descripcion.max' => 'La descripción no debe exceder los 255 caracteres.',
        ];
    }
}
