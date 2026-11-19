<?php

namespace App\Http\Controllers\Operaciones;

use App\Http\Controllers\Controller;

use App\Models\Ingreso;
use App\Models\Kardex;
use App\Models\Salida;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperacionConsultaController extends Controller
{
    /** Todas las consultas operativas se limitan a la sucursal de sesión y se paginan. */
    public function kardex(Request $request): Response
    {
        $search = $request->string('buscar')->trim()->value();

        $rows = Kardex::query()
            ->select(['id_kardex', 'id_producto', 'tipo_movimiento', 'referencia', 'cantidad_entrada', 'cantidad_salida', 'stock_actual'])
            ->where('id_sucursal', $request->user()->id_sucursal)
            ->with('producto:id_producto,nombre,codigo_barras')
            ->when($search, fn ($query) => $query->whereHas('producto', fn ($products) => $products->where('nombre', 'ILIKE', "%{$search}%")->orWhere('codigo_barras', 'ILIKE', "%{$search}%")))
            ->orderByDesc('id_kardex')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Kardex $item) => ['id' => $item->id_kardex, 'primary' => $item->producto?->nombre ?? 'Producto', 'secondary' => $item->referencia, 'values' => [$item->tipo_movimiento, number_format((float) $item->cantidad_entrada, 2), number_format((float) $item->cantidad_salida, 2), number_format((float) $item->stock_actual, 2)]]);

        return $this->render('Kardex', 'Movimiento', ['Tipo', 'Entrada', 'Salida', 'Saldo'], $rows, $search);
    }

    public function ingresos(Request $request): Response
    {
        $rows = Ingreso::query()
            ->select(['id_ingreso', 'id_proveedor', 'tipo_comprobante', 'num_comprobante', 'fecha_emision', 'total_ingreso', 'estado'])
            ->where('id_sucursal', $request->user()->id_sucursal)
            ->orderByDesc('id_ingreso')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Ingreso $item) => ['id' => $item->id_ingreso, 'primary' => "Ingreso #{$item->id_ingreso}", 'secondary' => $item->fecha_emision?->format('d/m/Y'), 'values' => [$item->tipo_comprobante, $item->num_comprobante, 'S/ '.number_format((float) $item->total_ingreso, 2), $item->estado]]);

        return $this->render('Ingresos', 'Registro', ['Comprobante', 'Número', 'Total', 'Estado'], $rows);
    }

    public function salidas(Request $request): Response
    {
        $rows = Salida::query()
            ->select(['id_salida', 'motivo', 'observacion', 'fecha_salida', 'estado'])
            ->where('id_sucursal', $request->user()->id_sucursal)
            ->orderByDesc('id_salida')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Salida $item) => ['id' => $item->id_salida, 'primary' => "Salida #{$item->id_salida}", 'secondary' => $item->observacion ?? 'Sin observación', 'values' => [$item->motivo, $item->fecha_salida?->format('d/m/Y H:i'), $item->estado]]);

        return $this->render('Salidas', 'Registro', ['Motivo', 'Fecha', 'Estado'], $rows);
    }

    public function usuarios(Request $request): Response
    {
        $query = User::query()
            ->select(['id_usuario', 'id_rol', 'id_sucursal', 'nombres', 'email', 'estado'])
            ->with(['rol:id_rol,nombre', 'sucursal:id_sucursal,nombre'])
            ->when(! $request->user()->isAdministrator(), fn ($users) => $users->where('id_sucursal', $request->user()->id_sucursal))
            ->orderBy('nombres');

        $rows = $query->paginate(20)
            ->withQueryString()
            ->through(fn (User $item) => ['id' => $item->id_usuario, 'primary' => $item->nombres, 'secondary' => $item->email, 'values' => [$item->rol?->nombre ?? 'Sin rol', $item->sucursal?->nombre ?? 'Sin sucursal', $item->estado ? 'Activo' : 'Inactivo']]);

        return $this->render('Usuarios', 'Usuario', ['Rol', 'Sucursal', 'Estado'], $rows);
    }

    private function render(string $title, string $entity, array $columns, mixed $rows, string $search = ''): Response
    {
        return Inertia::render('Operations/Index', [
            'operation' => compact('title', 'entity', 'columns', 'rows'),
            'filters' => ['buscar' => $search],
        ]);
    }
}
