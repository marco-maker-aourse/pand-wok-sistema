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
        ];
    }
}
