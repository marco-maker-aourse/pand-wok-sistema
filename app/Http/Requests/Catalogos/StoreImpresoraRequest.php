<?php

namespace App\Http\Requests\Catalogos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImpresoraRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'marca' => ['nullable', 'string', 'max:60'],
            'modelo' => ['nullable', 'string', 'max:80'],
            // BLUETOOTH representa una impresora Classic instalada mediante la cola de Windows.
            // BLE no se declara compatible hasta implementar un adaptador específico por modelo.
            'tipo_conexion' => ['required', Rule::in(['USB', 'RED', 'SISTEMA', 'BLUETOOTH'])],
            'identificador' => ['required', 'string', 'max:255'],
            'puerto' => ['nullable', 'integer', 'between:1,65535'],
            'ancho_papel_mm' => ['required', 'integer', Rule::in([58, 80])],
            'soporta_escpos' => ['sometimes', 'boolean'],
            'corte_automatico' => ['sometimes', 'boolean'],
            'abre_cajon' => ['sometimes', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
