<?php

namespace App\Http\Requests\Seguridad;

use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El PIN revalida movimientos de dinero. Un gestor de usuarios puede
        // editar cuentas de su sede, pero solo un administrador puede crear o
        // reemplazar esta segunda credencial. Del mismo modo, solo un
        // administrador concede acceso a sucursales adicionales.
        $requiereAdministrador = $this->filled('pin_cajero') || $this->has('sucursales_adicionales');

        return ! $requiereAdministrador || (bool) $this->user()?->isAdministrator();
    }

    /** Sucursales adicionales enviadas (sin la sede principal); null si no se gestionan. */
    public function sucursalesAdicionales(): ?array
    {
        if (! $this->has('sucursales_adicionales')) {
            return null;
        }

        return collect($this->validated('sucursales_adicionales', []))
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === (int) $this->validated('id_sucursal'))
            ->unique()
            ->values()
            ->all();
    }

    public function rules(): array
    {
        $userId = $this->route('usuario')?->id_usuario;
        $password = $this->isMethod('post') ? ['required'] : ['nullable'];
        // Es opcional porque no todas las cuentas operan caja. Cuando se envía,
        // la confirmación es obligatoria y User lo almacena usando el cast hashed.
        $pin = ['nullable'];

        return [
            'nombres' => ['required', 'string', 'min:3', 'max:150'],
            'email' => [
                'required', 
                'email', 
                'max:100', 
                Rule::unique(User::class, 'email')->ignore($userId, 'id_usuario')
            ],
            'id_rol' => ['required', 'integer', Rule::exists(Rol::class, 'id_rol')->where('estado', true)],
            'id_sucursal' => ['required', 'integer', Rule::exists(Sucursal::class, 'id_sucursal')->where('estado', true)],
            'id_tipo_documento' => ['required', 'integer', Rule::exists(TipoDocumento::class, 'id_tipo_documento')->where('estado', true)],
            'num_documento' => [
                'required',
                'string', 
                'max:15', 
                Rule::unique(User::class, 'num_documento')->ignore($userId, 'id_usuario'),
            ],
            'password' => [...$password, 'string', 'min:8', 'confirmed'],
            // Se usa para revalidar operaciones de caja; no sustituye la contraseña de inicio de sesión.
            'pin_cajero' => [...$pin, 'digits_between:4,6', 'confirmed'],
            'estado' => ['sometimes', 'boolean'],
            // Acceso a otras sucursales además de la sede (para cambiar de sucursal de trabajo).
            'sucursales_adicionales' => ['sometimes', 'array', 'max:100'],
            'sucursales_adicionales.*' => ['integer', 'distinct', Rule::exists(Sucursal::class, 'id_sucursal')->where('estado', true)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombres' => mb_strtoupper(trim((string) $this->input('nombres'))),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'num_documento' => mb_strtoupper(trim((string) $this->input('num_documento'))),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->has('id_tipo_documento') || $validator->errors()->has('num_documento')) {
                return;
            }

            $tipoDocumento = TipoDocumento::query()->find($this->integer('id_tipo_documento'));
            if (! $tipoDocumento) {
                return;
            }

            $numero = (string) $this->input('num_documento');
            if ($tipoDocumento->longitud_exacta !== null && strlen($numero) !== $tipoDocumento->longitud_exacta) {
                $validator->errors()->add('num_documento', "{$tipoDocumento->nombre} debe tener exactamente {$tipoDocumento->longitud_exacta} caracteres.");
            }

            if (in_array((string) $tipoDocumento->codigo_sunat, ['1', '6'], true) && ! ctype_digit($numero)) {
                $validator->errors()->add('num_documento', "{$tipoDocumento->nombre} solo admite dígitos.");
            }
        });
    }

    public function messages(): array
    {
        return [
            'nombres.required' => 'El nombre completo es obligatorio.',
            'email.required'   => 'El correo electrónico es obligatorio.',
            'email.email'      => 'El correo electrónico no tiene un formato válido.',
            'email.unique'     => 'Este correo electrónico ya se encuentra registrado.',
            'id_rol.required'  => 'Debe seleccionar un rol para el usuario.',
            'id_rol.exists'    => 'El rol seleccionado no es válido.',
            'id_sucursal.required' => 'Debe asignar una sucursal al usuario.',
            'id_sucursal.exists' => 'La sucursal seleccionada no está disponible.',
            'id_tipo_documento.required' => 'Debe seleccionar un tipo de documento.',
            'id_tipo_documento.exists' => 'El tipo de documento seleccionado no está disponible.',
            'num_documento.required' => 'El número de documento es obligatorio.',
            'num_documento.unique' => 'El número de documento ya pertenece a otro usuario.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'pin_cajero.digits_between' => 'El PIN de caja debe tener entre 4 y 6 dígitos.',
            'pin_cajero.confirmed' => 'La confirmación del PIN de caja no coincide.',
            'sucursales_adicionales.*.exists' => 'Una de las sucursales adicionales no está disponible.',
            'sucursales_adicionales.*.distinct' => 'Hay sucursales adicionales repetidas.',
        ];
    }
}
