<?php

namespace App\Http\Requests\Configuracion;

use App\Models\Sucursal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta y edición de sucursales: reservado a roles administradores. */
class StoreSucursalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdministrator();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => is_string($this->nombre) ? mb_strtoupper(trim($this->nombre)) : $this->nombre,
            'direccion' => is_string($this->direccion) ? trim($this->direccion) : $this->direccion,
            'codigo_establecimiento' => filled($this->codigo_establecimiento) ? trim((string) $this->codigo_establecimiento) : null,
            'slug' => filled($this->slug) ? mb_strtolower(trim((string) $this->slug)) : null,
            'horario_atencion' => filled($this->horario_atencion) ? trim((string) $this->horario_atencion) : null,
        ]);
    }

    public function rules(): array
    {
        $sucursal = $this->route('sucursal');

        return [
            'nombre' => ['required', 'string', 'max:100', Rule::unique(Sucursal::class, 'nombre')->ignore($sucursal?->id_sucursal, 'id_sucursal')],
            'direccion' => ['required', 'string', 'max:255'],
            'telefono' => ['nullable', 'regex:/^[0-9+\s-]{6,20}$/'],
            'responsable' => ['nullable', 'string', 'max:150'],
            // Código de establecimiento anexo asignado por SUNAT (0000 = domicilio fiscal).
            'codigo_establecimiento' => ['nullable', 'digits:4', Rule::unique(Sucursal::class, 'codigo_establecimiento')->ignore($sucursal?->id_sucursal, 'id_sucursal')],
            // Identificador público de la carta digital (/api/carta-publica/{slug}).
            'slug' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique(Sucursal::class, 'slug')->ignore($sucursal?->id_sucursal, 'id_sucursal')],
            'horario_atencion' => ['nullable', 'string', 'max:80'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre de la sucursal.',
            'nombre.unique' => 'Ya existe una sucursal con ese nombre.',
            'direccion.required' => 'Ingresa la dirección de la sucursal.',
            'telefono.regex' => 'El teléfono solo admite números, espacios, + y guiones (6 a 20 caracteres).',
            'codigo_establecimiento.digits' => 'El código de establecimiento SUNAT tiene 4 dígitos.',
            'codigo_establecimiento.unique' => 'Ese código de establecimiento ya pertenece a otra sucursal.',
            'slug.regex' => 'El identificador de carta solo admite minúsculas, números y guiones (p. ej. el-tambo).',
            'slug.unique' => 'Ese identificador de carta ya pertenece a otra sucursal.',
        ];
    }
}
