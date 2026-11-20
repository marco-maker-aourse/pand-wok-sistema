<?php

namespace App\Http\Controllers\Restaurante;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurante\GuardarGrupoModificadorRequest;
use App\Http\Requests\Restaurante\GuardarOpcionModificadorRequest;
use App\Models\Producto;
use App\Models\Restaurante\GrupoModificador;
use App\Models\Restaurante\OpcionModificador;
use App\Services\Restaurante\GestionarCartaService;
use App\Services\Restaurante\OpcionesSucursalService;
use App\Services\Restaurante\PrecioSucursalService;
use App\Services\Restaurante\PresentarRestauranteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Carta del restaurante sobre el catálogo de productos existente: tipo de producto,
 * agotados de la sucursal y modificadores. Altas de productos y precios siguen en Productos.
 */
class CartaController extends Controller
{
    use ConvierteErroresDeDominio;

    public function __construct(
        private readonly GestionarCartaService $carta,
        private readonly PresentarRestauranteService $presentar,
        private readonly PrecioSucursalService $precios,
        private readonly OpcionesSucursalService $opciones,
    ) {}

    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::in(Producto::TIPOS)],
        ]);

        return Inertia::render('Restaurante/Carta', [
            ...$this->presentar->cartaAdministracion($request->user()->idSucursalActiva(), $filtros['buscar'] ?? null, $filtros['tipo'] ?? null),
            'filtros' => ['buscar' => $filtros['buscar'] ?? '', 'tipo' => $filtros['tipo'] ?? ''],
        ]);
    }

    public function tipo(Request $request, int $producto): RedirectResponse
    {
        $datos = $request->validate(['tipo_producto' => ['required', Rule::in(Producto::TIPOS)]]);
        $this->enDominio(fn () => $this->carta->cambiarTipo($producto, $datos['tipo_producto']), 'tipo_producto');

        return back()->with('success', 'Tipo de producto actualizado.');
    }

    public function agotado(Request $request, int $producto): RedirectResponse
    {
        $datos = $request->validate(['agotado' => ['required', 'boolean']]);
        $usuario = $request->user();
        $this->carta->marcarAgotado($producto, (bool) $datos['agotado'], $usuario->id_usuario, $usuario->idSucursalActiva());

        return back()->with('success', $datos['agotado'] ? 'Producto marcado como agotado en esta sucursal.' : 'Producto disponible de nuevo.');
    }

    /**
     * Precio propio de la sucursal activa: un importe, "no se vende aquí" o volver al
     * precio general (precio_venta vacío y vende = true).
     */
    public function precioSucursal(Request $request, int $precio): RedirectResponse
    {
        $datos = $request->validate([
            'precio_venta' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'vende' => ['required', 'boolean'],
        ]);
        $this->enDominio(fn () => $this->precios->fijar(
            $precio,
            $request->user()->idSucursalActiva(),
            isset($datos['precio_venta']) ? (float) $datos['precio_venta'] : null,
            (bool) $datos['vende'],
        ), 'precio_venta');

        return back()->with('success', 'Precio de la sucursal actualizado.');
    }

    public function grupos(Request $request, int $producto): RedirectResponse
    {
        $datos = $request->validate([
            'grupos' => ['present', 'array', 'max:20'], 'grupos.*' => ['integer'],
            'reglas' => ['nullable', 'array', 'max:20'],
            'reglas.*.id_grupo' => ['required', 'integer'],
            'reglas.*.seleccion_minima' => ['nullable', 'integer', 'min:0', 'max:20'],
            'reglas.*.seleccion_maxima' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
        $reglas = collect($datos['reglas'] ?? [])->keyBy('id_grupo')->all();
        $this->enDominio(fn () => $this->carta->asignarGrupos($producto, $datos['grupos'], $reglas), 'grupos');

        return back()->with('success', 'Opciones del producto actualizadas.');
    }

    /** Recargo propio y disponibilidad de una opción en la sucursal activa. */
    public function opcionSucursal(Request $request, int $opcion): RedirectResponse
    {
        $datos = $request->validate([
            'precio_adicional' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'disponible' => ['required', 'boolean'],
        ]);
        $this->enDominio(fn () => $this->opciones->fijar(
            $opcion,
            $request->user()->idSucursalActiva(),
            isset($datos['precio_adicional']) ? (float) $datos['precio_adicional'] : null,
            (bool) $datos['disponible'],
        ), 'precio_adicional');

        return back()->with('success', 'Opción actualizada en esta sucursal.');
    }

    /** Variante física: de qué existencia se descuenta al vender la presentación. */
    public function variante(Request $request, int $precio): RedirectResponse
    {
        $datos = $request->validate(['id_producto_stock' => ['nullable', 'integer', 'min:1']]);
        $this->enDominio(fn () => $this->carta->fijarVariante($precio, $datos['id_producto_stock'] ?? null), 'id_producto_stock');

        return back()->with('success', 'Existencia de la presentación actualizada.');
    }

    public function guardarGrupo(GuardarGrupoModificadorRequest $request, ?GrupoModificador $grupo = null): RedirectResponse
    {
        $datos = $request->validated();
        $grupo ? $grupo->update($datos) : GrupoModificador::query()->create($datos);

        return back()->with('success', $grupo ? 'Grupo actualizado.' : 'Grupo creado.');
    }

    public function guardarOpcion(GuardarOpcionModificadorRequest $request, ?GrupoModificador $grupo = null, ?OpcionModificador $opcion = null): RedirectResponse
    {
        $this->enDominio(fn () => $this->carta->guardarOpcion($opcion, $grupo?->id_grupo, $request->validated()), 'id_precio');

        return back()->with('success', $opcion ? 'Opción actualizada.' : 'Opción agregada.');
    }
}
