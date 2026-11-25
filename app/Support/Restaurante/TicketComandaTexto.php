<?php

namespace App\Support\Restaurante;

use Illuminate\Support\Str;

/**
 * Comanda en texto plano ASCII para Star CloudPRNT (text/plain). Sigue el diseño de la
 * comanda del agente de impresión, adaptado a una impresora sin negrita ni negativo:
 *
 *   MESA M2                    14:32     <- a quién va, con la hora
 *   #41 - Caliente - Ana - 3 pers.
 *   --------------------------------
 *   [NOTA] Cliente con prisa             <- antes de los platos
 *   2   CHAUFA DE POLLO                  <- cantidad en su columna
 *       [MENU]                           <- etiqueta que distingue platos parecidos
 *       + Sopa wantan
 *       ! SIN CEBOLLA CHINA              <- indicación del plato
 *   1   TALLARIN SALTADO DE CARNE CON    <- sangría francesa
 *       VERDURAS
 *   MESA M2 --------------- 3 platos     <- cierre, destino y total en un renglón
 *
 * Ahorro de papel: sin renglones en blanco entre platos, un solo separador y sin avance al
 * final (la impresora avanza hasta su cuchilla al cortar el trabajo).
 *
 * Una anulación abre con "NO PREPARAR" y lleva [ANULADO] en cada plato, sin la nota del
 * pedido ni las indicaciones (para anular basta saber qué plato). Una reimpresión lleva
 * [COPIA] con la hora original. Recibe el bloque `comanda` del trabajo de impresión.
 */
final class TicketComandaTexto
{
    private const COLUMNAS = [58 => 32, 80 => 48];

    public static function render(array $comanda, ?int $anchoPapelMm = 58): string
    {
        $ancho = self::COLUMNAS[$anchoPapelMm] ?? self::COLUMNAS[58];
        $anulada = ($comanda['tipo'] ?? 'COMANDA') === 'ANULACION';
        $destino = self::destino($comanda);
        $lineas = [];
        $separador = function () use (&$lineas, $ancho): void {
            $lineas[] = str_repeat('-', $ancho);
        };
        // Renglones con sangría francesa: el primero tras `prefijo`, los siguientes alineados con él.
        $parrafo = function (string $prefijo, string $texto) use (&$lineas, $ancho): void {
            foreach (self::envolver(self::ascii($texto), $ancho - strlen($prefijo)) as $i => $parte) {
                $lineas[] = rtrim(($i === 0 ? $prefijo : str_repeat(' ', strlen($prefijo))).$parte);
            }
        };

        if ($anulada) {
            // Un solo renglón de aviso: "*** NO PREPARAR - ANULADOS ***" (llena el ancho).
            $lineas[] = str_pad(' NO PREPARAR - ANULADOS ', $ancho, '*', STR_PAD_BOTH);
        }

        $lineas[] = self::aLosLados($destino, self::ascii((string) ($comanda['hora'] ?? '')), $ancho);
        if (($comanda['tipo'] ?? '') === 'REIMPRESION') {
            $lineas[] = '[COPIA] Original '.substr((string) ($comanda['fecha'] ?? ''), 0, 5).' '.($comanda['hora'] ?? '');
        }
        $parrafo('', implode(' - ', array_filter([
            "#{$comanda['numero']}",
            $comanda['estacion'] ?? null,
            $comanda['mozo'] ?? null,
            ! empty($comanda['comensales']) ? "{$comanda['comensales']} pers." : null,
        ])));
        $separador();

        if (! $anulada && ! empty($comanda['nota'])) {
            $parrafo('[NOTA] ', (string) $comanda['nota']);
        }

        $items = $comanda['items'] ?? [];
        $columna = max(4, ...array_map(fn (array $item): int => strlen((string) $item['cantidad']) + 2, $items ?: [['cantidad' => '']]));
        $sangria = str_repeat(' ', $columna);
        $total = 0.0;
        foreach ($items as $item) {
            $total += (float) $item['cantidad'];
            // "Chaufa de pollo [Menú]": la etiqueta que distingue platos parecidos va aparte.
            $nombre = trim((string) $item['nombre']);
            $etiqueta = preg_match('/^(.*?)\s*\[([^\]]+)\]$/u', $nombre, $partes) ? $partes[2] : null;
            $nombre = $etiqueta !== null ? $partes[1] : $nombre;

            $parrafo(str_pad((string) $item['cantidad'], $columna), Str::upper($nombre));
            $marcas = array_filter([$anulada ? '[ANULADO]' : null, $etiqueta !== null ? '['.Str::upper(self::ascii($etiqueta)).']' : null]);
            if ($marcas !== []) {
                $lineas[] = $sangria.implode(' ', $marcas);
            }
            foreach ($item['opciones'] ?? [] as $opcion) {
                $parrafo("{$sangria}+ ", (string) $opcion);
            }
            if (! $anulada && ! empty($item['observacion'])) {
                $parrafo("{$sangria}! ", Str::upper((string) $item['observacion']));
            }
            if (! empty($item['cuenta'])) {
                $cuenta = $item['cuenta'];
                $parrafo($sangria, "Para pedido {$cuenta['numero']}".(! empty($cuenta['mesa']) ? " (mesa {$cuenta['mesa']})" : ''));
            }
        }

        $cantidad = rtrim(rtrim(number_format($total, 3, '.', ''), '0'), '.');
        $pie = $cantidad.' '.($total == 1 ? 'plato' : 'platos').($anulada ? ($total == 1 ? ' anulado' : ' anulados') : '');
        $izquierda = strlen($destino) + strlen($pie) + 4 <= $ancho ? "{$destino} " : '';
        $lineas[] = $izquierda.str_repeat('-', max(2, $ancho - strlen($izquierda) - strlen($pie) - 1)).' '.$pie;

        return implode("\n", $lineas)."\n";
    }

    private static function destino(array $comanda): string
    {
        $destino = match ($comanda['tipo_pedido'] ?? null) {
            'MESA' => 'Mesa '.($comanda['mesa'] ?? ''),
            'PARA_LLEVAR' => 'Para llevar'.(! empty($comanda['cliente']) ? " - {$comanda['cliente']}" : ''),
            'DELIVERY' => 'Delivery'.(! empty($comanda['cliente']) ? " - {$comanda['cliente']}" : ''),
            default => (string) ($comanda['tipo_pedido'] ?? ''),
        };

        return Str::upper(self::ascii(trim($destino)));
    }

    /** Solo ASCII imprimible: la impresora no garantiza tildes ni ñ en text/plain. */
    private static function ascii(string $texto): string
    {
        // "1 × Sopa" -> "1 x Sopa": Str::ascii() eliminaría el signo y el reparto se leería mal.
        $texto = str_replace(['×', '·'], ['x', '-'], $texto);

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\x20-\x7E]/', ' ', Str::ascii($texto))));
    }

    /** Corta en palabras sin pasar el ancho; una palabra más larga se parte. */
    private static function envolver(string $texto, int $ancho): array
    {
        return $texto === '' ? [''] : explode("\n", wordwrap($texto, max(8, $ancho), "\n", true));
    }

    /** Texto a la izquierda y a la derecha del mismo renglón; si no caben, en dos renglones. */
    private static function aLosLados(string $izquierda, string $derecha, int $ancho): string
    {
        if ($izquierda === '') {
            return str_pad($derecha, $ancho, ' ', STR_PAD_LEFT);
        }
        $espacio = $ancho - strlen($izquierda) - strlen($derecha);

        return $espacio >= 1
            ? $izquierda.str_repeat(' ', $espacio).$derecha
            : substr($izquierda, 0, $ancho)."\n".str_pad($derecha, $ancho, ' ', STR_PAD_LEFT);
    }
}
