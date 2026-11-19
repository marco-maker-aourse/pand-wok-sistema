<?php

namespace App\Services\Comprobantes;

use App\Models\DatosGen;
use DomainException;

class ReservarCorrelativoService
{
    /** Reserva el número dentro de la misma transacción de la venta. */
    public function execute(int $branchId, string $codigoSunat): array
    {
        $config = DatosGen::query()->where('id_sucursal', $branchId)->lockForUpdate()->first();
        if (! $config) { throw new DomainException('La sucursal no tiene configuración de comprobantes.'); }

        $fields = match ($codigoSunat) {
            '03' => ['serie_boleta', 'ult_nro_boleta'],
            '01' => ['serie_factura', 'ult_nro_factura'],
            default => throw new DomainException('Tipo de comprobante no soportado.'),
        };
        [$seriesField, $numberField] = $fields;
        if (blank($config->{$seriesField})) { throw new DomainException('La serie del comprobante no está configurada.'); }

        $next = (int) $config->{$numberField} + 1;
        $config->update([$numberField => $next]);

        return ['serie' => $config->{$seriesField}, 'correlativo' => $next, 'igv' => (string) $config->igv_porcentaje];
    }
}
