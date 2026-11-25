<?php

namespace App\Services\Comprobantes;

use App\Models\TipoComprobante;
use App\Models\SerieSucursal;
use App\Models\Venta;
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
            TipoComprobante::BOLETA => ['serie_boleta', 'ult_nro_boleta'],
            TipoComprobante::FACTURA => ['serie_factura', 'ult_nro_factura'],
            Venta::NOTA_VENTA => ['serie_nota_venta', 'ult_nro_nota_venta'],
            default => throw new DomainException('Tipo de comprobante no soportado.'),
        };

        $config = SerieSucursal::query()->whereKey($branchId)->lockForUpdate()->first();
        if (! $config) {
            throw new DomainException('Configura las series de boleta y factura de esta sucursal en Datos generales.');
        }
        // La nota de venta es interna: si la sucursal no definió serie, toma NV + su número (NV02).
        if ($codigoSunat === Venta::NOTA_VENTA && blank($config->{$seriesField})) {
            $config->{$seriesField} = 'NV'.str_pad((string) min(99, $branchId), 2, '0', STR_PAD_LEFT);
        }
        if (blank($config->{$seriesField})) {
            throw new DomainException('La serie del comprobante no está configurada para esta sucursal (Datos generales).');
        }

        $igv = $this->datosGenerales->empresa()?->igv_porcentaje;
        if ($igv === null) {
            throw new DomainException('Configura el IGV de la empresa en Datos generales.');
        }

        $next = (int) $config->{$numberField} + 1;
        $config->update([$numberField => $next, $seriesField => $config->{$seriesField}]);

        return ['serie' => $config->{$seriesField}, 'correlativo' => $next, 'igv' => (string) $igv];
    }
}
