<?php

namespace App\Http\Requests\Restaurante;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarMermaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'clave' => ['required', 'alpha_num:ascii', 'min:16', 'max:80'],
            'id_precio' => ['required', 'integer', 'min:1'],
            'cantidad' => ['required', 'numeric', 'gt:0', 'decimal:0,4', 'max:999999'],
            'motivo' => ['required', 'string', 'max:255'],
        ];
    }
}
