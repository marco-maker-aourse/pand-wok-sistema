<?php

namespace App\Http\Requests\Restaurante;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarItemPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cantidad' => ['sometimes', 'numeric', 'gt:0', 'max:999'],
            'observacion' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Cambiar la sopa o los acompañamientos antes de enviar la comanda.
            'selecciones' => ['sometimes', 'array', 'max:40'],
            'selecciones.*.id_opcion' => ['required', 'integer', 'min:1'],
            'selecciones.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999'],
        ];
    }
}
