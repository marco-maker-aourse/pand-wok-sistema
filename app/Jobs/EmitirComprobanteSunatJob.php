<?php

namespace App\Jobs;

use App\Services\Ventas\SunatEmisionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/** Emite después de confirmar la venta, sin bloquear el punto de venta. */
class EmitirComprobanteSunatJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public int $uniqueFor = 900;
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $ventaId) {}

    public function uniqueId(): string
    {
        return (string) $this->ventaId;
    }

    public function handle(SunatEmisionService $sunat): void
    {
        $result = $sunat->emitirComprobante($this->ventaId);

        // Sólo los problemas transitorios vuelven a la cola. Un rechazo del CDR
        // queda asociado definitivamente al correlativo emitido.
        if (! $result['success'] && $result['estado'] === 'PENDIENTE') {
            throw new RuntimeException($result['mensaje']);
        }
    }
}
