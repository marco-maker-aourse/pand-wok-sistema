<?php

namespace App\Http\Requests\Catalogos;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta de producto desde el asistente de tres pasos (datos, almacén y precios). */
class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta aplica `permission:productos.gestionar`.
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'                                          => ['required', 'string', 'max:200'],
            'id_categoria'                                    => ['required', 'integer', Rule::exists(Categoria::class, 'id_categoria')],
            'id_marca'                                        => ['nullable', 'integer', Rule::exists(Marca::class, 'id_marca')],
            'id_unidad_base'                                  => ['required_unless:es_servicio,true', 'nullable', 'integer', Rule::exists(UnidadMedida::class, 'id_unidad')],
            'codigo_barras'                                   => ['nullable', 'string', 'max:50', Rule::unique(Producto::class, 'codigo_barras')],
            'codigo_sunat'                                    => ['nullable', 'string', 'max:20'],
            'afecta_igv'                                      => ['boolean'],
            'es_servicio'                                     => ['boolean'],
            'usa_vencimiento'                                 => ['boolean'],

            // Precios del Paso 3
            'precio_venta_unitario'                           => ['nullable', 'numeric', 'min:0.01'],
            'id_presentacion_unitaria'                        => ['nullable', 'integer'],
            'presentaciones_adicionales'                      => ['nullable', 'array'],
            'presentaciones_adicionales.*.id_presentacion'   => ['nullable', 'integer', Rule::exists(Presentacion::class, 'id_presentacion')],
            'presentaciones_adicionales.*.factor_conversion' => ['nullable', 'numeric', 'min:1'],
            'presentaciones_adicionales.*.precio_venta'      => ['nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'                => 'El nombre del producto es obligatorio.',
            'id_categoria.required'          => 'Debe seleccionar una categoría.',
            'id_unidad_base.required_unless' => 'Debe seleccionar la unidad de medida principal.',
            'codigo_barras.unique'           => 'El código de barras ya pertenece a otro artículo.',
            'precio_venta_unitario.min'      => 'El precio de venta suelto debe ser mayor a 0.',
        ];
    }

    /** Datos validados con los valores por defecto del catálogo. */
    public function datosProducto(): array
    {
        return [
            ...$this->validated(),
            'nombre' => strtoupper(trim($this->validated('nombre'))),
            'afecta_igv' => $this->boolean('afecta_igv', true),
            'es_servicio' => $this->boolean('es_servicio', false),
            'usa_vencimiento' => $this->boolean('usa_vencimiento', false),
        ];
    }
}
