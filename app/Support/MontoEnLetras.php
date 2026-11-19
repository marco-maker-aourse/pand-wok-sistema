<?php

namespace App\Support;

/**
 * Importe en letras para comprobantes: "SON: CIENTO VEINTE CON 50/100 SOLES".
 * Es la misma leyenda que SUNAT define como código 1000 (monto expresado en letras).
 */
final class MontoEnLetras
{
    private const UNIDADES = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
        'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE',
        'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];

    private const DECENAS = ['', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];

    private const CENTENAS = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    public static function soles(float|int|string $importe): string
    {
        // Se trabaja en céntimos enteros para no arrastrar errores de coma flotante (0.1 + 0.2).
        $centimos = (int) round(abs((float) $importe) * 100);
        $entero = intdiv($centimos, 100);
        $decimales = $centimos % 100;

        return sprintf('SON: %s CON %02d/100 SOLES', self::numero($entero), $decimales);
    }

    /** Número entero en letras (0 a 999 999 999 999). */
    public static function numero(int $numero): string
    {
        if ($numero === 0) return 'CERO';

        $partes = [];
        $millonesDeMillon = intdiv($numero, 1_000_000_000_000);
        if ($millonesDeMillon > 0) return (string) $numero; // Fuera del rango de un comprobante.

        $milesDeMillon = intdiv($numero, 1_000_000_000);
        $millones = intdiv($numero % 1_000_000_000, 1_000_000);
        $miles = intdiv($numero % 1_000_000, 1_000);
        $resto = $numero % 1_000;

        if ($milesDeMillon > 0 || $millones > 0) {
            $totalMillones = $milesDeMillon * 1000 + $millones;
            $partes[] = $totalMillones === 1 ? 'UN MILLÓN' : self::apocopar(self::hastaMil($totalMillones)) . ' MILLONES';
        }
        if ($miles > 0) {
            $partes[] = $miles === 1 ? 'MIL' : self::apocopar(self::hastaMil($miles)) . ' MIL';
        }
        if ($resto > 0) {
            $partes[] = self::hastaMil($resto);
        }

        return implode(' ', $partes);
    }

    /** 1 a 999 999 (para "millones" se combinan grupos de miles). */
    private static function hastaMil(int $numero): string
    {
        if ($numero >= 1000) {
            $miles = intdiv($numero, 1000);
            $resto = $numero % 1000;
            $texto = $miles === 1 ? 'MIL' : self::apocopar(self::hastaMil($miles)) . ' MIL';
            return $resto > 0 ? $texto . ' ' . self::hastaMil($resto) : $texto;
        }
        if ($numero === 100) return 'CIEN';

        $centena = intdiv($numero, 100);
        $decena = $numero % 100;
        $texto = self::CENTENAS[$centena];

        if ($decena > 0) {
            $textoDecena = $decena < 30
                ? self::UNIDADES[$decena]
                : self::DECENAS[intdiv($decena, 10)] . ($decena % 10 > 0 ? ' Y ' . self::UNIDADES[$decena % 10] : '');
            $texto = trim($texto . ' ' . $textoDecena);
        }

        return $texto;
    }

    /** Delante de "MIL" o "MILLONES": "VEINTIUNO" → "VEINTIÚN", "UNO" → "UN". */
    private static function apocopar(string $texto): string
    {
        return preg_replace(['/VEINTIUNO$/u', '/UNO$/u'], ['VEINTIÚN', 'UN'], $texto) ?? $texto;
    }
}
