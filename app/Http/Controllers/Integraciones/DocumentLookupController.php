<?php

namespace App\Http\Controllers\Integraciones;

use App\Http\Controllers\Controller;
use App\Exceptions\DocumentLookupException;
use App\Services\External\DocumentLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentLookupController extends Controller
{
    public function __invoke(Request $request, DocumentLookupService $lookup): JsonResponse
    {
        $data = $request->validate([
            'context' => ['required', 'in:cliente,proveedor,usuario,empresa'],
            'tipo' => ['required', 'in:dni,ruc'],
            'documento' => ['required', 'digits_between:8,11'],
        ]);

        // Validaciones estrictas antes de llamar a la API externa
        if ($data['tipo'] === 'dni' && strlen($data['documento']) !== 8) {
            return response()->json(['message' => 'El DNI debe tener 8 dígitos.'], 422);
        }

        if ($data['tipo'] === 'ruc') {
            if (strlen($data['documento']) !== 11) {
                return response()->json(['message' => 'El RUC debe tener 11 dígitos.'], 422);
            }

            if (!str_starts_with($data['documento'], '10') && !str_starts_with($data['documento'], '20')) {
                return response()->json(['message' => 'El RUC debe comenzar obligatoriamente con 10 o 20.'], 422);
            }
        }

        try {
            return response()->json(['data' => $lookup->lookup($data['tipo'], $data['documento'])]);
        } catch (DocumentLookupException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Error consulta SUNAT: ' . $exception->getMessage(), ['trace' => $exception->getTraceAsString()]);

            return response()->json([
                'message' => 'No se pudo completar la consulta documental. Inténtalo nuevamente.'
            ], 502);
        }
    }
}
