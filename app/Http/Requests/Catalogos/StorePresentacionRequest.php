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

    /** "taza " y "TAZA" son el mismo empaque: se guarda en mayúsculas y sin espacios sobrantes. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $this->input('nombre'))))]);
        }
    }

    /** Alta rápida desde el formulario de producto (JSON): si el nombre ya existe, se reutiliza. */
    public function esAltaRapida(): bool
    {
        return $this->expectsJson() && ! $this->route('presentacion');
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
                ...($this->esAltaRapida() ? [] : [Rule::unique(Presentacion::class, 'nombre')->ignore($id, 'id_presentacion')]),
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
