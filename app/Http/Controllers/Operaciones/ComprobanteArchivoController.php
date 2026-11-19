<?php

namespace App\Http\Controllers\Operaciones;

use App\Http\Controllers\Controller;
use App\Models\Venta;
use App\Services\Ventas\GenerarComprobantePdfService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Entrega artefactos fiscales privados sin exponer rutas del disco local. */
class ComprobanteArchivoController extends Controller
{
    public function download(Request $request, Venta $venta, string $tipo, GenerarComprobantePdfService $pdf): StreamedResponse
    {
        abort_unless((int) $venta->id_sucursal === (int) $request->user()->idSucursalActiva(), 404);

        if ($tipo === 'pdf' && blank($venta->enlace_pdf)) {
            try {
                $pdf->generar($venta);
                $venta->refresh();
            } catch (DomainException $exception) {
                abort(422, $exception->getMessage());
            }
        }

        [$column, $directory] = match ($tipo) {
            'xml' => ['enlace_xml', 'sunat/xml/'],
            'cdr' => ['enlace_cdr', 'sunat/cdr/'],
            'pdf' => ['enlace_pdf', 'sunat/pdf/'],
            default => abort(404),
        };

        $path = $venta->{$column};
        // La ruta proviene de la venta, pero se restringe al directorio fiscal esperado.
        abort_unless(is_string($path) && str_starts_with($path, $directory), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, basename($path));
    }
}
