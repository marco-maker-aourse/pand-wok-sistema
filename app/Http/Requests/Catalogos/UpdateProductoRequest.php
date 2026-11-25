<?php

namespace App\Http\Requests\Catalogos;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Edición de los datos maestros de un producto existente. */
class UpdateProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La ruta aplica `permission:productos.gestionar`.
        return true;
    }

    public function rules(): array
    {
        $productoId = $this->route('producto')?->id_producto;

        return [
            'nombre'          => ['required', 'string', 'max:200'],
            'id_categoria'    => ['required', 'integer', Rule::exists(Categoria::class, 'id_categoria')],
            'id_marca'        => ['nullable', 'integer', Rule::exists(Marca::class, 'id_marca')],
            'id_unidad_base'  => ['required', 'integer', Rule::exists(UnidadMedida::class, 'id_unidad')],
            'codigo_barras'   => ['nullable', 'string', 'max:50', Rule::unique(Producto::class, 'codigo_barras')->ignore($productoId, 'id_producto')],
            'codigo_sunat'    => ['nullable', 'string', 'max:20'],
            'afecta_igv'      => ['boolean'],
            'es_servicio'     => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'         => 'El nombre del producto es obligatorio.',
            'id_categoria.required'   => 'Debe seleccionar una categoría.',
            'id_unidad_base.required' => 'Debe seleccionar la unidad de medida principal.',
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
        ];
    }
}
