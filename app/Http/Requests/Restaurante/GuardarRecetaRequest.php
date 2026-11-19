<?php

namespace App\Http\Requests\Restaurante;

use Illuminate\Foundation\Http\FormRequest;

class GuardarRecetaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ingredientes' => ['present', 'array', 'max:100'],
            'ingredientes.*' => ['array:id_precio_insumo,cantidad'],
            'ingredientes.*.id_precio_insumo' => ['required', 'integer', 'min:1', 'distinct'],
            'ingredientes.*.cantidad' => ['required', 'numeric', 'gt:0', 'decimal:0,4', 'max:9999'],
        ];
    }
}
