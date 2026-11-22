<?php

namespace App\Services\Comprobantes;

use App\Models\SerieSucursal;
use App\Services\Configuracion\DatosGeneralesService;
use DomainException;

class ReservarCorrelativoService
{
    public function __construct(private readonly DatosGeneralesService $datosGenerales = new DatosGeneralesService) {}

    /**
     * Reserva el número dentro de la misma transacción de la venta. Cada sucursal
     * numera en su fila de configuracion.series_sucursal; el IGV es el de la empresa.
     */
    public function execute(int $branchId, string $codigoSunat): array
    {
        [$seriesField, $numberField] = match ($codigoSunat) {
            '03' => ['serie_boleta', 'ult_nro_boleta'],
            '01' => ['serie_factura', 'ult_nro_factura'],
            default => throw new DomainException('Tipo de comprobante no soportado.'),
        };

        $config = SerieSucursal::query()->whereKey($branchId)->lockForUpdate()->first();
        if (! $config) {
            throw new DomainException('Configura las series de boleta y factura de esta sucursal en Datos generales.');
        }
        if (blank($config->{$seriesField})) {
            throw new DomainException('La serie del comprobante no está configurada para esta sucursal (Datos generales).');
        }

        $igv = $this->datosGenerales->empresa()?->igv_porcentaje;
        if ($igv === null) {
            throw new DomainException('Configura el IGV de la empresa en Datos generales.');
        }

        $next = (int) $config->{$numberField} + 1;
        $config->update([$numberField => $next]);

        return ['serie' => $config->{$seriesField}, 'correlativo' => $next, 'igv' => (string) $igv];
    }
}
