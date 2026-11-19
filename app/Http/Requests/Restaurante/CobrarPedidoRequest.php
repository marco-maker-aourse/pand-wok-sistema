<?php

namespace App\Http\Requests\Restaurante;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CobrarPedidoRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'clave_cobro' => ['required', 'alpha_num:ascii', 'min:16', 'max:80'],
            'tipo_comprobante' => ['required', Rule::in(['BOLETA', 'FACTURA'])],
            'id_cliente' => ['nullable', 'integer', 'min:1'],
            'descuento_global' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999.99'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*' => ['array:id_detalle_pedido,cantidad,cobrada'],
            'items.*.id_detalle_pedido' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'max:999'],
            'items.*.cobrada' => ['required', 'numeric', 'min:0', 'decimal:0,3', 'max:999'],
            'pagos' => ['required', 'array', 'min:1', 'max:20'],
            'pagos.*' => ['array:id_metodo,monto,nro_referencia,monto_recibido'],
            'pagos.*.id_metodo' => ['required', 'integer', 'min:1', 'distinct'],
            'pagos.*.monto' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999.99'],
            'pagos.*.nro_referencia' => ['nullable', 'string', 'max:100'],
            'pagos.*.monto_recibido' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999.99'],
        ];
    }
}
