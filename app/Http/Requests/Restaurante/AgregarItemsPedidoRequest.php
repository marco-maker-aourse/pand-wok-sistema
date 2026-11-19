<?php

namespace App\Http\Requests\Restaurante;

use Illuminate\Foundation\Http\FormRequest;

/** Ítems nuevos; el precio no se acepta del navegador, lo calcula ArmarItemPedidoService. */
class AgregarItemsPedidoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.clave' => ['required', 'string', 'min:16', 'max:80', 'distinct'],
            'items.*.id_precio' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999'],
            'items.*.observacion' => ['nullable', 'string', 'max:255'],
            'items.*.opciones' => ['nullable', 'array', 'max:20'],
            'items.*.opciones.*' => ['integer'],
        ];
    }

    /** Solo los campos conocidos llegan al servicio. */
    public function items(): array
    {
        return array_map(fn (array $item): array => [
            'clave' => $item['clave'],
            'id_precio' => (int) $item['id_precio'],
            'cantidad' => $item['cantidad'],
            'observacion' => $item['observacion'] ?? null,
            'opciones' => array_map('intval', $item['opciones'] ?? []),
        ], $this->validated('items'));
    }
}
