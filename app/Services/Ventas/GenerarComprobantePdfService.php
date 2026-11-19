<?php

namespace App\Services\Ventas;

use App\Models\Venta;
use App\Services\Comprobantes\PresentarComprobanteService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/** Genera la representación impresa desde la venta persistida, nunca desde el navegador. */
class GenerarComprobantePdfService
{
    public function __construct(private readonly PresentarComprobanteService $presentacion) {}

    public function generar(Venta $venta): string
    {
        // El archivo descargable es la representación fiscal final: solo se emite
        // cuando el CDR confirma que el comprobante tiene validez tributaria.
        if ($venta->estado_sunat !== 'ACEPTADO') {
            throw new \DomainException('El PDF oficial estará disponible cuando SUNAT acepte el comprobante.');
        }

        $disk = Storage::disk('local');
        if (filled($venta->enlace_pdf) && $disk->exists($venta->enlace_pdf)) {
            return $venta->enlace_pdf;
        }

        $filename = implode('-', [
            $venta->serie,
            str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT),
        ]).'.pdf';
        $path = "sunat/pdf/{$filename}";

        $comprobante = $this->presentacion->paraVenta($venta);
        $qr = null;
        if (filled($comprobante['qr_fiscal'])) {
            $writer = new Writer(new ImageRenderer(new RendererStyle(160, 4), new SvgImageBackEnd()));
            $qr = 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($comprobante['qr_fiscal']));
        }
        $pdf = Pdf::loadView('pdf.comprobante', compact('comprobante', 'qr'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false);

        $disk->put($path, $pdf->output());
        $venta->forceFill(['enlace_pdf' => $path])->save();

        return $path;
    }
}
