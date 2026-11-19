<?php

namespace App\Http\Requests\Catalogos;

use App\Models\Proveedor;
use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Ruc;
use Illuminate\Validation\Rule;

class StoreProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $proveedor = $this->route('proveedor');
        $id = is_object($proveedor) ? $proveedor->id_proveedor : $proveedor;

        return [
            'ruc' => [
                'required',
                'string',
                new Ruc,
                // Evita el error 'Database connection [catalogos] not configured'
                Rule::unique(Proveedor::class, 'num_documento')->ignore($id, 'id_proveedor'),
                function ($attribute, $value, $fail) {
                    // Blindaje condicional de num_documento en edición (PUT/PATCH)
                    if (in_array($this->method(), ['PUT', 'PATCH'])) {
                        $proveedor = $this->route('proveedor');
                        if ($proveedor && $proveedor->num_documento !== $value) {
                            if (! $this->user()->hasPermission('proveedores.editar_documento')) {
                                $fail('No tiene permiso para modificar el documento.');
                            }
                            if ($proveedor->tieneIngresos()) {
                                $fail('El proveedor ya tiene ingresos asociados; el documento no se puede modificar.');
                            }
                            if (blank($this->input('motivo_cambio_documento'))) {
                                $fail('El motivo del cambio es obligatorio.');
                            }
                            $this->merge(['_permitir_cambio_documento' => true]);
                        }
                    }
                },
            ],
            'razon_social' => ['required', 'string', 'max:255'],
            'direccion'    => ['nullable', 'string', 'max:255'],
            'telefono'     => ['nullable', 'string', 'max:50'],
            'email'        => ['nullable', 'email', 'max:150'],
            'web'          => ['nullable', 'string', 'max:255'],
            'estado'                  => ['sometimes', 'boolean'],
            'motivo_cambio_documento' => ['nullable', 'string', 'min:10', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'ruc.required'         => 'El número de RUC es obligatorio.',
            'ruc.size'             => 'El RUC debe tener exactamente 11 dígitos.',
            'ruc.unique'           => 'Este RUC ya pertenece a otro proveedor registrado.',
            'razon_social.required'=> 'La razón social o nombre comercial es obligatoria.',
            'email.email'          => 'Ingresa un correo electrónico válido.',
        ];
    }
}
