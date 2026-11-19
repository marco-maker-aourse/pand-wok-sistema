<?php

namespace App\Services\Inventario;

use App\Models\Ingreso;
use App\Models\Kardex;
use App\Models\Salida;
use App\Models\Venta;

/**
 * Qué operación generó un movimiento de Kardex. Se guarda junto al movimiento para que el
 * kardex valorizado muestre el documento de origen sin tener que adivinarlo después.
 */
final class OrigenMovimiento
{
    public const VENTA = 'VENTA';

    public const INGRESO = 'INGRESO';

    public const SALIDA = 'SALIDA';

    public const AJUSTE = 'AJUSTE';

    public function __construct(
        public readonly string $tipo,
        public readonly ?int $idReferencia = null,
        public readonly ?string $documento = null,
        public readonly ?int $idUsuario = null,
    ) {}

    public static function venta(Venta $venta): self
    {
        $numero = $venta->serie && $venta->correlativo !== null
            ? $venta->serie.'-'.str_pad((string) $venta->correlativo, 8, '0', STR_PAD_LEFT)
            : 'VENTA-'.$venta->id_venta;

        return new self(self::VENTA, (int) $venta->id_venta, $numero, $venta->id_usuario ? (int) $venta->id_usuario : null);
    }

    /** El comprobante del proveedor (serie-número) identifica el ingreso; si falta, su número interno. */
    public static function ingreso(Ingreso $ingreso): self
    {
        $serie = trim((string) $ingreso->serie_comprobante);
        $numero = trim((string) $ingreso->num_comprobante);
        $documento = $numero !== ''
            ? ($serie !== '' ? "{$serie}-{$numero}" : $numero)
            : 'ING-'.str_pad((string) $ingreso->id_ingreso, 6, '0', STR_PAD_LEFT);

        return new self(self::INGRESO, (int) $ingreso->id_ingreso, $documento, $ingreso->id_usuario ? (int) $ingreso->id_usuario : null);
    }

    public static function salida(Salida $salida): self
    {
        return new self(self::SALIDA, (int) $salida->id_salida, 'SAL-'.str_pad((string) $salida->id_salida, 6, '0', STR_PAD_LEFT), $salida->id_usuario ? (int) $salida->id_usuario : null);
    }

    /** El ajuste no tiene documento: se guarda su motivo para la auditoría. */
    public static function ajuste(string $motivo, ?int $idUsuario): self
    {
        return new self(self::AJUSTE, null, mb_substr(trim($motivo), 0, 60) ?: null, $idUsuario);
    }

    /**
     * Columnas a guardar en Kardex. Vacío mientras la migración que las crea no se haya
     * aplicado: así el código puede desplegarse antes que la migración sin romper ventas.
     */
    public function columnas(): array
    {
        if (! Kardex::registraOrigen()) {
            return [];
        }

        return [
            'tipo_operacion' => $this->tipo,
            'id_referencia' => $this->idReferencia,
            'documento' => $this->documento,
            'id_usuario' => $this->idUsuario,
        ];
    }
}
