<?php

namespace App\Http\Requests\Restaurante;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Una sola entrada para las tres operaciones de transferencia:
 * - mesa:  lleva la cuenta a otra mesa libre (id_mesa).
 * - unir:  pasa toda la cuenta a otra cuenta abierta (id_pedido_destino).
 * - mover: pasa ítems concretos a otra cuenta abierta (id_pedido_destino + detalles).
 */
class TransferirPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operacion' => ['required', Rule::in(['mesa', 'unir', 'mover'])],
            'id_mesa' => ['required_if:operacion,mesa', 'nullable', 'integer'],
            'id_pedido_destino' => ['required_if:operacion,unir,mover', 'nullable', 'integer'],
            'detalles' => ['required_if:operacion,mover', 'nullable', 'array', 'max:200'],
            'detalles.*' => ['integer'],
        ];
    }

    public function attributes(): array
    {
        return ['id_mesa' => 'mesa de destino', 'id_pedido_destino' => 'cuenta de destino', 'detalles' => 'ítems'];
    }
}
