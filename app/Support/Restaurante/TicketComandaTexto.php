<?php

namespace App\Support\Restaurante;

use Illuminate\Support\Str;

/**
 * Comanda en texto plano ASCII (Star CloudPRNT text/plain): sin controles de impresora,
 * así que la jerarquía se marca con mayúsculas, separadores y posición.
 *
 * - Lo que identifica el pedido (mesa o cliente) va primero, luego la nota general del
 *   pedido: el cocinero la lee antes de empezar, no al final.
 * - Platos en mayúsculas, opciones con "+", indicaciones del plato con ">>".
 * - Una anulación repite "ANULADO" en cada plato: nadie debe prepararlo por error.
 * - Al pie, el total de platos para comprobar que el ticket salió completo.
 *
 * Recibe el bloque `comanda` del contenido del trabajo (ImprimirComandaService::contenido).
 */
final class TicketComandaTexto
{
    private const COLUMNAS = [58 => 32, 80 => 48];

    public static function render(array $comanda, ?int $anchoPapelMm = 58): string
    {
        $ancho = self::COLUMNAS[$anchoPapelMm] ?? self::COLUMNAS[58];
        $anulacion = ($comanda['tipo'] ?? 'COMANDA') === 'ANULACION';
        $lineas = [];
        $agregar = function (string $texto, string $sangria = '') use (&$lineas, $ancho): void {
            foreach (self::envolver(self::ascii($texto), $ancho - strlen($sangria)) as $i => $parte) {
                $lineas[] = ($i === 0 ? $sangria : str_repeat(' ', strlen($sangria))).$parte;
            }
        };
        $separador = function (string $caracter = '-') use (&$lineas, $ancho): void {
            $lineas[] = str_repeat($caracter, $ancho);
        };

        // Encabezado: tipo de ticket y destino del pedido.
        if ($anulacion) {
            $separador('*');
            $lineas[] = rtrim(self::centrar('ANULACION - NO PREPARAR', $ancho));
            $separador('*');
        } else {
            $separador('=');
        }
        $titulo = (($comanda['tipo'] ?? '') === 'REIMPRESION' ? 'COPIA ' : '')."COMANDA {$comanda['numero']}";
        $lineas[] = self::aLosLados(self::ascii($titulo), self::ascii((string) ($comanda['hora'] ?? '')), $ancho);
        $agregar(Str::upper(self::destino($comanda)));
        $agregar(implode(' | ', array_filter([$comanda['estacion'] ?? null, isset($comanda['pedido']) ? "Pedido {$comanda['pedido']}" : null])));
        $personas = ! empty($comanda['comensales']) ? $comanda['comensales'].' '.($comanda['comensales'] == 1 ? 'persona' : 'personas') : null;
        $atiende = implode(' | ', array_filter([! empty($comanda['mozo']) ? "Mozo: {$comanda['mozo']}" : null, $personas]));
        if ($atiende !== '') {
            $agregar($atiende);
        }
        if (($comanda['tipo'] ?? '') === 'REIMPRESION') {
            $agregar("Enviada {$comanda['fecha']} {$comanda['hora']}");
        }
        $anulacion ? $separador('*') : $separador('=');

        if (! empty($comanda['nota'])) {
            $agregar('NOTA: '.Str::upper($comanda['nota']));
            $separador();
        }

        // Platos.
        $total = 0.0;
        foreach ($comanda['items'] ?? [] as $indice => $item) {
            if ($indice > 0) {
                $lineas[] = '';
            }
            $total += (float) $item['cantidad'];
            $agregar(($anulacion ? 'ANULADO ' : '')."{$item['cantidad']} x ".Str::upper($item['nombre']));
            foreach ($item['opciones'] ?? [] as $opcion) {
                $agregar("+ {$opcion}", '    ');
            }
            if (! empty($item['observacion'])) {
                $agregar('>> '.Str::upper($item['observacion']), '    ');
            }
            if (! empty($item['cuenta'])) {
                $cuenta = $item['cuenta'];
                $agregar("Para pedido {$cuenta['numero']}".(! empty($cuenta['mesa']) ? " (mesa {$cuenta['mesa']})" : ''), '    ');
            }
        }

        $separador();
        $cantidad = self::numero($total);
        $agregar($cantidad.' '.($total == 1 ? 'plato' : 'platos').($anulacion ? ($total == 1 ? ' anulado' : ' anulados') : ''));

        return implode("\n", $lineas)."\n\n\n";
    }

    private static function destino(array $comanda): string
    {
        return match ($comanda['tipo_pedido'] ?? null) {
            'MESA' => trim('Mesa '.($comanda['mesa'] ?? '').(! empty($comanda['area']) ? " - {$comanda['area']}" : '')),
            'PARA_LLEVAR' => 'Para llevar'.(! empty($comanda['cliente']) ? " - {$comanda['cliente']}" : ''),
            'DELIVERY' => 'Delivery'.(! empty($comanda['cliente']) ? " - {$comanda['cliente']}" : ''),
            default => (string) ($comanda['tipo_pedido'] ?? ''),
        };
    }

    /** Solo ASCII imprimible: la impresora no garantiza tildes ni ñ en text/plain. */
    private static function ascii(string $texto): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^\x20-\x7E]/', ' ', Str::ascii($texto))));
    }

    /** Corta en palabras sin pasar el ancho; una palabra más larga se parte. */
    private static function envolver(string $texto, int $ancho): array
    {
        if ($texto === '') {
            return [''];
        }

        return explode("\n", wordwrap($texto, max(8, $ancho), "\n", true));
    }

    private static function aLosLados(string $izquierda, string $derecha, int $ancho): string
    {
        $espacio = $ancho - strlen($izquierda) - strlen($derecha);

        return $espacio >= 1 ? $izquierda.str_repeat(' ', $espacio).$derecha : substr("{$izquierda} {$derecha}", 0, $ancho);
    }

    private static function centrar(string $texto, int $ancho): string
    {
        return str_pad($texto, $ancho, ' ', STR_PAD_BOTH);
    }

    private static function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 3, '.', ''), '0'), '.');
    }
}
