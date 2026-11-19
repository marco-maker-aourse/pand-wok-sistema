<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;

use App\Models\Lote;
use Illuminate\Http\Request;

class LoteController extends Controller
{
    // Listar lotes con stock activo y sus relaciones
    public function index()
    {
        $lotes = Lote::with(['producto', 'sucursal'])
            ->where('estado', true)
            ->get();

        return response()->json($lotes);
    }

    // Obtener un lote especifico por ID
    public function show($id)
    {
        $lote = Lote::with(['producto', 'sucursal'])->findOrFail($id);

        return response()->json($lote);
    }
}
