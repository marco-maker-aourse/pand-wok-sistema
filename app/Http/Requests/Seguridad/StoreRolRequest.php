<?php

namespace App\Http\Requests\Seguridad;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRolRequest extends FormRequest
{
    public function authorize(): bool 
    { 
        return true; 
    }

    public function rules(): array
    {
        $id = $this->route('rol')?->id_rol;

        return [
            'nombre'      => ['required', 'string', 'max:50', Rule::unique(Rol::class, 'nombre')->ignore($id, 'id_rol')],
            'descripcion' => ['nullable', 'string', 'max:200'],
            'estado'      => ['sometimes', 'boolean'],
            'permisos'    => ['sometimes', 'array'],
            'permisos.*'  => ['integer', Rule::exists(Permiso::class, 'id_permiso')],
        ];
    }
}
