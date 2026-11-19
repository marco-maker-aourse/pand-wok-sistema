<?php

namespace App\Http\Controllers\Operaciones;

use App\Http\Controllers\Controller;
use App\Services\Ventas\SunatEmisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SunatController extends Controller
{
    public function emitir(Request $request, SunatEmisionService $sunatService): JsonResponse
    {
        $data = $request->validate([
            'id_venta' => ['required', 'integer'],
        ]);

        $belongsToCurrentBranch = DB::table('operaciones.ventas')
            ->where('id_venta', $data['id_venta'])
            ->where('id_sucursal', $request->user()->idSucursalActiva())
            ->exists();
        abort_unless($belongsToCurrentBranch, 404);

        // Con CPE aceptado la respuesta incluye `impresion` (modo y si ya se encoló el ticket).
        return response()->json($sunatService->emitirComprobante((int) $data['id_venta']));
    }
}
