<?php

namespace App\Services\Restaurante;

use App\Models\Categoria;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\ProductoPresentacion;
use App\Models\Sucursal;
use App\Models\UnidadMedida;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Importa una carta con precios por sede al catálogo (genérico, no atado a un negocio).
 *
 * Formato de entrada (el mismo que publica /api/carta-publica):
 * {
 *   "sedes": { "<slug>": { "nombre": "...", "direccion": "...", "horario": "..." } },
 *   "carta": [ { "categoria": "...", "iconoNombre": "...", "oferta": "...",
 *                "platos": [ { "id": "c01", "nro": "01", "nombre": "...", "precios": { "<slug>": 14 } } ] } ]
 * }
 *
 * - Cada sede se asocia a una sucursal por su slug (o por el mapa explícito recibido).
 * - Plato sin precio en una sede = no se vende en esa sucursal.
 * - Es repetible: el id del plato se guarda en productos.codigo_externo y una nueva
 *   importación actualiza nombre, categoría, número y precios en vez de duplicar.
 * - Con $simular = true todo se revierte al final y solo se devuelve el resumen.
 */
class ImportarCartaService
{
    private const PRESENTACION = 'Unidad';

    public function __construct(private readonly PrecioSucursalService $precios) {}

    /**
     * @param  array<string, int>  $mapaExplicito  slug de sede => id_sucursal
     * @return array{categorias:int, creados:int, actualizados:int, precios_sucursal:int, no_disponibles:int, sedes:array<string, ?string>, simulado:bool}
     */
    public function importar(array $datos, array $mapaExplicito = [], bool $simular = false): array
    {
        $this->validarFormato($datos);
        $sedes = $this->resolverSedes(array_keys($datos['sedes']), $mapaExplicito);

        $resumen = [
            'categorias' => 0, 'creados' => 0, 'actualizados' => 0, 'precios_sucursal' => 0, 'no_disponibles' => 0,
            'sedes' => array_map(fn (?Sucursal $sucursal) => $sucursal?->nombre, $sedes),
            'simulado' => $simular,
        ];

        DB::beginTransaction();
        try {
            $unidad = $this->unidadBase();
            $presentacion = $this->presentacion();

            foreach (array_values($datos['carta']) as $orden => $bloque) {
                $categoria = $this->categoria($bloque, $orden);
                $resumen['categorias']++;

                foreach ($bloque['platos'] as $plato) {
                    [$producto, $nuevo] = $this->producto($plato, $categoria, $bloque['categoria'], $unidad->id_unidad);
                    $resumen[$nuevo ? 'creados' : 'actualizados']++;

                    $precio = ProductoPresentacion::query()->updateOrCreate(
                        ['id_producto' => $producto->id_producto, 'id_presentacion' => $presentacion->id_presentacion],
                        ['factor_conversion' => 1, 'precio_venta' => $this->precioGeneral($plato['precios']), 'estado' => true],
                    );

                    foreach ($sedes as $slug => $sucursal) {
                        if (! $sucursal) {
                            continue;
                        }
                        $valor = $plato['precios'][$slug] ?? null;
                        $this->precios->fijar($precio->id_precio, $sucursal->id_sucursal, $valor === null ? null : (float) $valor, $valor !== null);
                        $resumen[$valor === null ? 'no_disponibles' : 'precios_sucursal']++;
                    }
                }
            }

            $simular ? DB::rollBack() : DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        return $resumen;
    }

    private function validarFormato(array $datos): void
    {
        if (! is_array($datos['sedes'] ?? null) || $datos['sedes'] === [] || ! is_array($datos['carta'] ?? null)) {
            throw new DomainException('El archivo debe tener "sedes" (objeto) y "carta" (lista de categorías).');
        }

        $ids = [];
        foreach ($datos['carta'] as $bloque) {
            if (! is_string($bloque['categoria'] ?? null) || trim($bloque['categoria']) === '' || ! is_array($bloque['platos'] ?? null)) {
                throw new DomainException('Cada categoría necesita "categoria" y "platos".');
            }
            foreach ($bloque['platos'] as $plato) {
                $id = $plato['id'] ?? null;
                if (! is_string($id) || $id === '' || ! is_string($plato['nombre'] ?? null) || ! is_array($plato['precios'] ?? null) || $plato['precios'] === []) {
                    throw new DomainException('Cada plato necesita "id", "nombre" y al menos un precio.');
                }
                if (isset($ids[$id])) {
                    throw new DomainException("El id de plato «{$id}» está repetido.");
                }
                $ids[$id] = true;
                foreach ($plato['precios'] as $slug => $valor) {
                    if (! array_key_exists($slug, $datos['sedes']) || ! is_numeric($valor) || $valor < 0) {
                        throw new DomainException("Precio inválido para «{$plato['nombre']}» en la sede «{$slug}».");
                    }
                }
            }
        }
    }

    /** @return array<string, ?Sucursal> slug => sucursal (null si no hay sucursal asociada) */
    private function resolverSedes(array $slugs, array $mapaExplicito): array
    {
        $sedes = [];
        foreach ($slugs as $slug) {
            $sucursal = isset($mapaExplicito[$slug])
                ? Sucursal::query()->find($mapaExplicito[$slug])
                : Sucursal::query()->where('slug', $slug)->first();

            if (isset($mapaExplicito[$slug]) && ! $sucursal) {
                throw new DomainException("La sucursal {$mapaExplicito[$slug]} indicada para «{$slug}» no existe.");
            }
            $sedes[$slug] = $sucursal;
        }

        return $sedes;
    }

    private function unidadBase(): UnidadMedida
    {
        return UnidadMedida::query()->firstOrCreate(['codigo_sunat' => UnidadMedida::UNIDAD], ['nombre' => 'Unidad', 'estado' => true]);
    }

    private function presentacion(): Presentacion
    {
        return Presentacion::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower(self::PRESENTACION)])->first()
            ?? Presentacion::query()->create(['nombre' => self::PRESENTACION, 'estado' => true]);
    }

    private function categoria(array $bloque, int $orden): Categoria
    {
        $nombre = trim($bloque['categoria']);
        $categoria = Categoria::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first()
            ?? Categoria::query()->create(['nombre' => $nombre, 'estado' => true]);

        DB::table('restaurante.categorias_carta')->updateOrInsert(
            ['id_categoria' => $categoria->id_categoria],
            [
                'orden' => $orden,
                'icono' => isset($bloque['iconoNombre']) ? mb_substr((string) $bloque['iconoNombre'], 0, 40) : null,
                'nota' => isset($bloque['oferta']) ? mb_substr((string) $bloque['oferta'], 0, 255) : null,
                'visible' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return $categoria;
    }

    /** @return array{0: Producto, 1: bool} producto y si fue creado */
    private function producto(array $plato, Categoria $categoria, string $nombreCategoria, int $unidadId): array
    {
        $valores = [
            'nombre' => mb_substr(trim($plato['nombre']), 0, 200),
            'id_categoria' => $categoria->id_categoria,
            'codigo_carta' => isset($plato['nro']) ? mb_substr((string) $plato['nro'], 0, 10) : null,
            'estado' => true,
        ];

        $producto = Producto::query()->where('codigo_externo', $plato['id'])->first();
        if ($producto) {
            $producto->update($valores);

            return [$producto, false];
        }

        // Las bebidas se venden tal cual; el resto pasa por cocina.
        $esBebida = str_starts_with(mb_strtolower($nombreCategoria), 'bebida');

        return [Producto::query()->create([
            ...$valores,
            'codigo_externo' => $plato['id'],
            'id_unidad_base' => $unidadId,
            'tipo_producto' => $esBebida ? Producto::DIRECTO : Producto::PREPARADO,
            'afecta_igv' => true,
            'es_servicio' => false,
        ]), true];
    }

    /** Precio general de respaldo: el más repetido entre sedes (ante empate, el mayor). */
    private function precioGeneral(array $precios): float
    {
        $conteo = [];
        foreach ($precios as $valor) {
            $clave = number_format((float) $valor, 2, '.', '');
            $conteo[$clave] = ($conteo[$clave] ?? 0) + 1;
        }
        uksort($conteo, fn ($a, $b) => [$conteo[$b], (float) $b] <=> [$conteo[$a], (float) $a]);

        return (float) array_key_first($conteo);
    }
}
