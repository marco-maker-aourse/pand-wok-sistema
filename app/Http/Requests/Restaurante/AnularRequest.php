<?php

namespace App\Http\Requests\Restaurante;

use Illuminate\Foundation\Http\FormRequest;

/** El servicio decide cuándo el motivo es obligatorio (ítems ya enviados, pedido completo). */
class AnularRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:255'],
            'recuperar_insumos' => ['sometimes', 'boolean'],
        ];
    }
}
