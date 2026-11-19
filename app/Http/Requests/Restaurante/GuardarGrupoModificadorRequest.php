<?php

namespace App\Http\Requests\Restaurante;

use App\Models\Restaurante\GrupoModificador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarGrupoModificadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $grupo = $this->route('grupo');

        return [
            'nombre' => [
                'required', 'string', 'max:80',
                Rule::unique(GrupoModificador::class, 'nombre')->ignore($grupo?->id_grupo, 'id_grupo'),
            ],
            'seleccion_minima' => ['required', 'integer', 'min:0', 'max:20'],
            'seleccion_maxima' => ['required', 'integer', 'min:1', 'max:20', 'gte:seleccion_minima'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe un grupo de modificadores con ese nombre.',
            'seleccion_maxima.gte' => 'El máximo no puede ser menor que el mínimo.',
        ];
    }
}
