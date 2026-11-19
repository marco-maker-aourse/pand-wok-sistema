<?php

namespace App\Http\Requests\Restaurante;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarEstacionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:60'],
            'activo' => ['required', 'boolean'],
            'por_defecto' => ['required', 'boolean'],
            'orden' => ['required', 'integer', 'between:0,32767'],
            'copias' => ['required', 'integer', 'between:1,5'],
            'id_impresora' => ['nullable', 'integer', 'min:1'],
            'id_terminal' => ['nullable', 'integer', 'min:1'],
            'categorias' => ['present', 'array'],
            'categorias.*' => ['integer', 'distinct', Rule::exists(config('database.default').'.catalogos.categorias', 'id_categoria')],
        ];
    }
}
