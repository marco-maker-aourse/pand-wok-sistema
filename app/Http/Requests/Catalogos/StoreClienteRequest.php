<?php

namespace App\Http\Requests\Catalogos;

use App\Models\Cliente;
use App\Models\TipoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use App\Rules\Ruc;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    /**
     * Prepara los datos antes de la validación relacionando el código SUNAT.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('id_tipo_documento') && $this->filled('tipo_documento')) {
            $documentTypeId = TipoDocumento::query()
                ->where('codigo_sunat', (string) $this->input('tipo_documento'))
                ->where('estado', true)
                ->value('id_tipo_documento');

            $this->merge(['id_tipo_documento' => $documentTypeId]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cliente = $this->route('cliente');
        $id = is_object($cliente) ? $cliente->id_cliente : $cliente;

        return [
            'id_tipo_documento'   => [
                'required',
                'integer',
                Rule::exists(TipoDocumento::class, 'id_tipo_documento')->where('estado', true),
            ],
            'tipo_persona'        => [
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $idTipoDocumento = $this->input('id_tipo_documento');
                    if (! $idTipoDocumento) {
                        return;
                    }

                    $tipoDocumento = TipoDocumento::query()
                        ->where('id_tipo_documento', $idTipoDocumento)
                        ->where('estado', true)
                        ->first(['codigo_sunat']);

                    if (! $tipoDocumento) {
                        return;
                    }

                    $numDocumento = $this->input('num_documento', '');

                    if ($tipoDocumento->codigo_sunat === '6') { // RUC
                        if (str_starts_with($numDocumento, '20')) {
                            if ($value !== 'JURIDICA') {
                                $fail('Para RUC iniciado en 20, el tipo persona debe ser JURIDICA.');
                            }
                        } elseif (str_starts_with($numDocumento, '10')) {
                            if ($value !== 'NATURAL') {
                                $fail('Para RUC iniciado en 10, el tipo persona debe ser NATURAL.');
                            }
                        }
                    } else { // DNI u otro documento
                        if ($value !== 'NATURAL') {
                            $fail('Para este tipo de documento, el tipo persona debe ser NATURAL.');
                        }
                    }
                },
            ],
            'tipo_documento'      => ['nullable', 'string', 'max:50'],
            'num_documento'       => [
                'required',
                'string',
                'max:20',
                Rule::unique(Cliente::class, 'num_documento')->ignore($id, 'id_cliente'),
                function ($attribute, $value, $fail) {
                    $idTipoDocumento = $this->input('id_tipo_documento');
                    if (! $idTipoDocumento) {
                        return;
                    }

                    $tipoDocumento = TipoDocumento::query()
                        ->where('id_tipo_documento', $idTipoDocumento)
                        ->where('estado', true)
                        ->first(['codigo_sunat']);

                    if (! $tipoDocumento) {
                        return;
                    }

                    if ($tipoDocumento->codigo_sunat === '1') { // DNI
                        if (! preg_match('/^\d{8}$/', $value)) {
                            $fail('El DNI debe tener exactamente 8 dígitos numéricos.');
                        }
                    } elseif ($tipoDocumento->codigo_sunat === '6') { // RUC
                        if (! preg_match(Ruc::PATRON, $value)) {
                            $fail(Ruc::MENSAJE);
                        }
                    } else { // Otro documento (Carnet, Pasaporte)
                        if (! preg_match('/^\d+$/', $value)) {
                            $fail('El número de documento debe ser numérico.');
                        }
                    }

                    // Blindaje condicional de num_documento en edición (PUT/PATCH)
                    if (in_array($this->method(), ['PUT', 'PATCH'])) {
                        $cliente = $this->route('cliente');
                        if ($cliente && $cliente->num_documento !== $value) {
                            if (! $this->user()->hasPermission('clientes.editar_documento')) {
                                $fail('No tiene permiso para modificar el documento.');
                            }
                            if ($cliente->tieneVentas()) {
                                $fail('El cliente ya tiene comprobantes emitidos; el documento no se puede modificar.');
                            }
                            if (blank($this->input('motivo_cambio_documento'))) {
                                $fail('El motivo del cambio es obligatorio.');
                            }
                            $this->merge(['_permitir_cambio_documento' => true]);
                        }
                    }
                },
            ],
            'nombre_razon_social' => ['required', 'string', 'max:255'],
            'direccion_fiscal'    => ['nullable', 'string', 'max:255'],
            'telefono'            => ['nullable', 'string', 'max:50'],
            'email'               => ['nullable', 'email', 'max:150'],
            'estado'                  => ['sometimes', 'boolean'],
            'motivo_cambio_documento' => ['nullable', 'string', 'min:10', 'max:255'],
        ];
    }

    /**
     * Asigna nombres limpios en español a los campos para mensajes genéricos de Laravel.
     */
    public function attributes(): array
    {
        return [
            'id_tipo_documento'   => 'tipo de documento',
            'num_documento'       => 'número de documento',
            'nombre_razon_social' => 'nombres o razón social',
            'direccion_fiscal'    => 'dirección fiscal',
            'email'               => 'correo electrónico',
        ];
    }

    /**
     * Mensajes específicos en español por cada regla de validación.
     */
    public function messages(): array
    {
        return [
            'id_tipo_documento.required' => 'Debe seleccionar un tipo de documento válido (DNI o RUC).',
            'id_tipo_documento.exists'   => 'El tipo de documento seleccionado no está disponible.',
            'num_documento.required'     => 'El número de documento (DNI/RUC) es obligatorio.',
            'num_documento.unique'       => 'Ya existe un cliente registrado con este número de documento.',
            'nombre_razon_social.required' => 'El nombre completo o razón social es obligatorio.',
            'email.email'                => 'Ingresa un correo electrónico válido.',
        ];
    }
}