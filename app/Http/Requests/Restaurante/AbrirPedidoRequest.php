<?php

namespace App\Http\Requests\Restaurante;

use App\Models\Restaurante\Pedido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbrirPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(Pedido::TIPOS)],
            'id_mesa' => ['nullable', 'required_if:tipo,'.Pedido::MESA, 'integer'],
            'comensales' => ['nullable', 'integer', 'min:1', 'max:100'],
            'nombre_contacto' => ['nullable', 'string', 'max:120'],
            'telefono_contacto' => ['nullable', 'string', 'max:20'],
            'direccion_entrega' => ['nullable', 'required_if:tipo,'.Pedido::DELIVERY, 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:255'],
            // Generada por el navegador al abrir el diálogo; protege del doble envío.
            'clave_idempotencia' => ['required', 'string', 'min:16', 'max:80'],
        ];
    }

    public function attributes(): array
    {
        return ['id_mesa' => 'mesa', 'direccion_entrega' => 'dirección de entrega'];
    }
}
