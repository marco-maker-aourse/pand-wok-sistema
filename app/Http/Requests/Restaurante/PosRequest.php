<?php

namespace App\Http\Requests\Restaurante;

use App\Models\Restaurante\Pedido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Operación del punto de venta: enviar a cocina o cobrar. La cuenta es nueva (sin
 * id_pedido) o una abierta. Los precios nunca vienen del navegador.
 */
class PosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cobrar = $this->routeIs('restaurante.pos.cobrar');
        $nueva = ! $this->filled('id_pedido');

        return [
            // Cabe en las claves derivadas (pos:{clave}:envio ≤ 80).
            'clave' => ['required', 'alpha_num:ascii', 'min:16', 'max:64'],
            'id_pedido' => ['nullable', 'integer', 'min:1'],
            'tipo' => [$nueva ? 'required' : 'nullable', Rule::in(Pedido::TIPOS)],
            'id_mesa' => ['nullable', 'required_if:tipo,'.Pedido::MESA, 'integer', 'min:1'],
            'comensales' => ['nullable', 'integer', 'min:1', 'max:100'],
            'nombre_contacto' => ['nullable', 'string', 'max:120'],
            'telefono_contacto' => ['nullable', 'string', 'max:20'],
            'direccion_entrega' => ['nullable', 'required_if:tipo,'.Pedido::DELIVERY, 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:255'],

            'items' => ['nullable', 'array', 'max:60'],
            'items.*.clave' => ['required', 'alpha_num:ascii', 'min:16', 'max:80', 'distinct'],
            'items.*.id_precio' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999'],
            'items.*.observacion' => ['nullable', 'string', 'max:255'],
            'items.*.opciones' => ['nullable', 'array', 'max:20'],
            'items.*.opciones.*' => ['integer'],

            'tipo_comprobante' => [$cobrar ? 'required' : 'prohibited', Rule::in(['BOLETA', 'FACTURA'])],
            'id_cliente' => ['nullable', 'integer', 'min:1'],
            'descuento_global' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999.99'],
            'pagos' => [$cobrar ? 'required' : 'prohibited', 'array', 'min:1', 'max:20'],
            'pagos.*' => ['array:id_metodo,monto,nro_referencia,monto_recibido'],
            'pagos.*.id_metodo' => ['required', 'integer', 'min:1', 'distinct'],
            'pagos.*.monto' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999999.99'],
            'pagos.*.nro_referencia' => ['nullable', 'string', 'max:100'],
            'pagos.*.monto_recibido' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999999.99'],
        ];
    }

    public function attributes(): array
    {
        return ['id_mesa' => 'mesa', 'direccion_entrega' => 'dirección de entrega', 'items' => 'ítems', 'pagos' => 'pagos'];
    }

    /** Datos normalizados para VentaPosService (solo campos conocidos). */
    public function datos(): array
    {
        $datos = $this->validated();
        $datos['items'] = array_map(fn (array $item): array => [
            'clave' => $item['clave'],
            'id_precio' => (int) $item['id_precio'],
            'cantidad' => $item['cantidad'],
            'observacion' => $item['observacion'] ?? null,
            'opciones' => array_map('intval', $item['opciones'] ?? []),
        ], $datos['items'] ?? []);

        return $datos;
    }
}
