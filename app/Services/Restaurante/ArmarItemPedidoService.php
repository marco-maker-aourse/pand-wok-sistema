<?php

namespace App\Services\Restaurante;

use App\Models\Producto;
use App\Models\ProductoPresentacion;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Convierte lo que pide el mozo (presentación, cantidad, opciones) en un ítem con
 * precio calculado en el servidor. El navegador nunca envía precios.
 *
 * Reglas:
 * - La presentación y el producto deben estar activos; los insumos no se venden.
 * - El producto no debe estar agotado en la sucursal.
 * - El precio base es el de la sucursal (PrecioSucursalService), no el general.
 * - Cada opción debe pertenecer a un grupo del plato y estar disponible en la sucursal
 *   (OpcionesSucursalService): ni desactivada ni agotada. Su recargo es el de la sucursal.
 * - Se respeta el mínimo y máximo de cada grupo por unidad (obligatorios incluidos).
 * - Una opción incluida (recargo 0) no se cobra aparte: va dentro del precio del plato.
 */
class ArmarItemPedidoService
{
    public const CANTIDAD_MAXIMA = 999;

    public function __construct(
        private readonly PrecioSucursalService $precios,
        private readonly OpcionesSucursalService $opcionesSucursal,
    ) {}

    /**
     * @param  array{id_precio:int, cantidad:float|int|string, observacion?:?string, opciones?:array<int>, selecciones?:array<int, array{id_opcion:int, cantidad:float}>}  $item
     * @return array{detalle: array<string, mixed>, modificadores: array<int, array<string, mixed>>}
     */
    public function armar(array $item, int $sucursalId): array
    {
        $cantidad = round((float) ($item['cantidad'] ?? 0), 3);
        if ($cantidad <= 0 || $cantidad > self::CANTIDAD_MAXIMA) {
            throw new DomainException('La cantidad debe ser mayor que cero y no superar '.self::CANTIDAD_MAXIMA.'.');
        }

        $precio = ProductoPresentacion::query()
            ->with('producto')
            ->where('estado', true)
            ->find((int) ($item['id_precio'] ?? 0));

        $producto = $precio?->producto;
        if (! $precio || ! $producto || ! $producto->estado) {
            throw new DomainException('El producto seleccionado no está disponible en la carta.');
        }
        if ($producto->tipo_producto === Producto::INSUMO) {
            throw new DomainException("{$producto->nombre} es un insumo y no se vende en la carta.");
        }

        $agotado = DB::table('restaurante.productos_agotados')
            ->where('id_sucursal', $sucursalId)
            ->where('id_producto', $producto->id_producto)
            ->exists();
        if ($agotado) {
            throw new DomainException("{$producto->nombre} está agotado en esta sucursal.");
        }

        $modificadores = $this->modificadores($producto, $item, $cantidad, $sucursalId);
        // Cada sucursal puede tener su propio precio (o no vender el plato).
        $precioBase = round($this->precios->precio($precio, $sucursalId), 4);
        $precioModificadores = self::recargoUnitario($modificadores, $cantidad);
        $precioUnitario = round($precioBase + $precioModificadores, 4);

        return [
            'detalle' => [
                'id_precio' => $precio->id_precio,
                'id_producto' => $producto->id_producto,
                'tipo_producto' => $producto->tipo_producto,
                'nombre_producto' => $producto->nombre,
                'cantidad' => $cantidad,
                'precio_base' => $precioBase,
                'precio_modificadores' => $precioModificadores,
                'precio_unitario' => $precioUnitario,
                'subtotal' => self::subtotal($cantidad, $precioUnitario),
                'observacion' => self::textoOpcional($item['observacion'] ?? null),
            ],
            'modificadores' => $modificadores,
        ];
    }

    public static function subtotal(float $cantidad, float $precioUnitario): float
    {
        return round($cantidad * $precioUnitario, 2);
    }

    public static function textoOpcional(?string $texto): ?string
    {
        $limpio = trim((string) $texto);

        return $limpio === '' ? null : mb_substr($limpio, 0, 255);
    }

    /**
     * Valida y congela las opciones elegidas.
     *
     * Acepta "opciones" (ids; cada una aplica a todas las unidades, formato anterior) y
     * "selecciones" ([{id_opcion, cantidad}], para repartir: 2 menús, 1 sopa wantán y 1 de
     * pollo). Por grupo, la suma de cantidades debe quedar entre mínimo × cantidad y
     * máximo × cantidad del ítem, y una opción no puede superar la cantidad del ítem.
     *
     * @return array<int, array{id_opcion:int, id_precio:?int, nombre_grupo:string, nombre_opcion:string, precio_adicional:float, cantidad:float}>
     */
    private function modificadores(Producto $producto, array $item, float $cantidadItem, int $sucursalId): array
    {
        $elegidas = $this->selecciones($item, $cantidadItem);
        $reglas = $this->opcionesSucursal->reglas([$producto->id_producto])->get($producto->id_producto, collect());
        $opciones = $this->opcionesSucursal->opciones($reglas->pluck('id_grupo')->all(), $sucursalId);
        $grupos = $reglas->keyBy('id_grupo');

        foreach ($elegidas as $id => $cantidad) {
            $opcion = $opciones->get($id);
            if (! $opcion) {
                throw new DomainException("Una de las opciones elegidas no corresponde a {$producto->nombre}.");
            }
            if ($opcion->estado === OpcionesSucursalService::AGOTADA) {
                throw new DomainException("{$opcion->nombre} está agotado en esta sucursal.");
            }
            if ($opcion->estado !== OpcionesSucursalService::DISPONIBLE) {
                throw new DomainException("{$opcion->nombre} no está disponible en esta sucursal.");
            }
            if ($opcion->id_precio !== null && $producto->tipo_producto !== Producto::PREPARADO) {
                throw new DomainException("Las opciones con producto propio solo se ofrecen en platos preparados ({$opcion->nombre}).");
            }
            if ($cantidad > $cantidadItem) {
                throw new DomainException("{$opcion->nombre} no puede elegirse más veces que la cantidad del plato.");
            }
        }

        foreach ($grupos as $grupo) {
            $total = round(collect($elegidas)->filter(fn (float $c, int $id) => $opciones[$id]->id_grupo === $grupo->id_grupo)->sum(), 3);
            $minimo = round($grupo->seleccion_minima * $cantidadItem, 3);
            $maximo = round($grupo->seleccion_maxima * $cantidadItem, 3);
            if ($total < $minimo) {
                throw new DomainException("Elige al menos {$grupo->seleccion_minima} opción(es) en «{$grupo->nombre}» por cada plato.");
            }
            if ($total > $maximo) {
                throw new DomainException("Puedes elegir como máximo {$grupo->seleccion_maxima} opción(es) en «{$grupo->nombre}» por cada plato.");
            }
        }

        // Orden estable para cocina y tickets: el de los grupos del plato y el de sus opciones.
        $posicion = $reglas->pluck('id_grupo')->flip();
        uksort($elegidas, fn (int $a, int $b) => [$posicion[$opciones[$a]->id_grupo], $opciones[$a]->orden, $a]
            <=> [$posicion[$opciones[$b]->id_grupo], $opciones[$b]->orden, $b]);

        $resultado = [];
        foreach ($elegidas as $id => $cantidad) {
            $opcion = $opciones[$id];
            $resultado[] = [
                'id_opcion' => $opcion->id_opcion,
                'id_precio' => $opcion->id_precio,
                'nombre_grupo' => mb_substr($grupos[$opcion->id_grupo]->nombre, 0, 80),
                'nombre_opcion' => mb_substr($opcion->nombre, 0, 80),
                'precio_adicional' => $opcion->precio_adicional,
                'cantidad' => $cantidad,
            ];
        }

        return $resultado;
    }

    /** @return array<int, float> cantidad por id_opcion */
    private function selecciones(array $item, float $cantidadItem): array
    {
        $elegidas = [];
        $sumar = function (int $id, float $cantidad) use (&$elegidas): void {
            if (isset($elegidas[$id])) {
                throw new DomainException('Una opción se eligió más de una vez.');
            }
            if ($cantidad <= 0) {
                throw new DomainException('La cantidad de cada opción debe ser mayor que cero.');
            }
            $elegidas[$id] = $cantidad;
        };

        foreach ($item['opciones'] ?? [] as $id) {
            $sumar((int) $id, $cantidadItem);
        }
        foreach ($item['selecciones'] ?? [] as $seleccion) {
            $sumar((int) ($seleccion['id_opcion'] ?? 0), round((float) ($seleccion['cantidad'] ?? 0), 3));
        }

        return $elegidas;
    }

    /** Recargo por unidad: suma de recargo × veces elegida, repartida entre las unidades del ítem. */
    public static function recargoUnitario(array $modificadores, float $cantidadItem): float
    {
        $total = array_sum(array_map(fn (array $m): float => $m['precio_adicional'] * $m['cantidad'], $modificadores));

        return round($total / $cantidadItem, 4);
    }
}
