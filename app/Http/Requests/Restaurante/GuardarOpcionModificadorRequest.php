<?php

namespace App\Http\Requests\Restaurante;

use App\Models\Restaurante\OpcionModificador;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarOpcionModificadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $opcion = $this->route('opcion');
        $grupoId = $opcion?->id_grupo ?? $this->route('grupo')?->id_grupo;

        return [
            'id_precio' => ['nullable', 'integer', 'min:1'],
            'nombre' => [
                'nullable', 'required_without:id_precio', 'string', 'max:80',
                Rule::unique(OpcionModificador::class, 'nombre')
                    ->where('id_grupo', $grupoId)
                    ->ignore($opcion?->id_opcion, 'id_opcion'),
            ],
            'precio_adicional' => ['required', 'numeric', 'min:0', 'max:99999'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['nombre.unique' => 'Esa opción ya existe en el grupo.'];
    }
}
