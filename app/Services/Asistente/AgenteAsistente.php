<?php

namespace App\Services\Asistente;

use App\Models\User;
use App\Services\Asistente\Llm\ClienteLlm;
use App\Services\Asistente\Llm\ErrorProveedorLlm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Conversación con el modelo usando "function calling": el modelo decide qué herramientas
 * de solo lectura consultar (HerramientasAsistente), recibe los datos reales y redacta.
 *
 * Eventos que recibe $alEvento (para streaming hacia el navegador):
 *   ['tipo' => 'estado', 'texto' => 'Consultando ventas']   qué se está haciendo
 *   ['tipo' => 'texto', 'delta' => '...']                     fragmento de la respuesta
 *   ['tipo' => 'reiniciar']                                    descartar texto parcial previo
 */
class AgenteAsistente
{
    private const MAX_LLAMADAS_POR_RONDA = 5;

    public function __construct(
        private readonly HerramientasAsistente $herramientas,
        private readonly ClienteLlm $cliente,
    ) {
    }

    public function disponible(): bool
    {
        return $this->cliente->disponible();
    }

    /**
     * @param  array<int, array{rol: string, texto: string}>  $historial
     * @param  null|callable(array): void  $alEvento
     * @throws ErrorProveedorLlm si ningún proveedor responde; el llamador decide el respaldo.
     */
    public function responder(string $mensaje, array $historial, User $usuario, int $sucursalId, ?callable $alEvento = null): string
    {
        $inicio = microtime(true);
        $emitir = $alEvento ?? static fn () => null;
        $mensajes = [['role' => 'system', 'content' => $this->instrucciones($usuario, $sucursalId)]];
        foreach (array_slice($historial, -max(0, (int) config('asistente.historial', 8))) as $previo) {
            $mensajes[] = ['role' => $previo['rol'] === 'user' ? 'user' : 'assistant', 'content' => $previo['texto']];
        }
        $mensajes[] = ['role' => 'user', 'content' => $mensaje];

        $definiciones = $this->herramientas->definiciones($usuario);
        $maxRondas = max(1, (int) config('asistente.max_rondas', 4));
        $usadas = [];
        $tokens = 0;
        $respuesta = null;

        for ($ronda = 1; $ronda <= $maxRondas; $ronda++) {
            $emitir(['tipo' => 'estado', 'texto' => $ronda === 1 ? 'Analizando tu pregunta' : 'Preparando la respuesta']);
            $huboTexto = false;
            // En la última ronda no se ofrecen herramientas: obliga a responder con lo ya consultado.
            $respuesta = $this->cliente->completar(
                $mensajes,
                $ronda === $maxRondas ? [] : $definiciones,
                function (string $delta) use ($emitir, &$huboTexto) {
                    $huboTexto = true;
                    $emitir(['tipo' => 'texto', 'delta' => $delta]);
                },
            );
            $tokens += (int) $respuesta->tokens;
            $llamadas = array_slice($respuesta->llamadas, 0, self::MAX_LLAMADAS_POR_RONDA);

            if ($llamadas === []) {
                $texto = self::normalizarMontos(trim($respuesta->texto));
                if ($texto === '') throw new ErrorProveedorLlm('El modelo devolvió una respuesta vacía.');
                $this->registrar($respuesta, $ronda, $usadas, $tokens, $inicio);
                return $texto;
            }

            // Algunos modelos escriben un preámbulo ("Voy a revisar…") antes de pedir datos: se descarta.
            if ($huboTexto) $emitir(['tipo' => 'reiniciar']);

            $mensajes[] = ['role' => 'assistant', 'content' => $respuesta->texto !== '' ? $respuesta->texto : null, 'tool_calls' => $llamadas];
            foreach ($llamadas as $llamada) {
                $nombre = $llamada['function']['name'];
                $argumentos = json_decode($llamada['function']['arguments'], true);
                // Nombre y argumentos (fechas, textos de búsqueda) para diagnosticar respuestas imprecisas.
                $usadas[] = $nombre . ' ' . mb_substr($llamada['function']['arguments'], 0, 160);
                $emitir(['tipo' => 'estado', 'texto' => $this->herramientas->etiqueta($nombre)]);
                $argumentos = is_array($argumentos) ? $argumentos : [];
                $resultado = $this->herramientas->ejecutar($nombre, $argumentos, $usuario, $sucursalId);
                if ($aviso = $this->avisoDeAnio($argumentos, $mensaje, $historial)) $resultado['advertencia'] = $aviso;
                $mensajes[] = [
                    'role' => 'tool',
                    'tool_call_id' => $llamada['id'],
                    'content' => json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
            }
        }

        throw new ErrorProveedorLlm('El modelo no terminó la respuesta.');
    }

    /**
     * Los modelos tienden a suponer el año de su entrenamiento. Si consultan un año anterior
     * que nadie mencionó en la conversación, se les advierte junto con el resultado.
     */
    private function avisoDeAnio(array $argumentos, string $mensaje, array $historial): ?string
    {
        $hoy = CarbonImmutable::now('America/Lima');
        $conversacion = $mensaje . ' ' . implode(' ', array_column($historial, 'texto'));
        foreach ($argumentos as $clave => $valor) {
            if (! preg_match('/^(desde|hasta)/', (string) $clave) || ! is_string($valor)) continue;
            if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m)) continue;
            $anio = (int) $m[1];
            if ($anio >= $hoy->year || str_contains($conversacion, (string) $anio)) continue;
            // Año anterior legítimo: la fecha con el año actual todavía no llegó (en enero, "diciembre").
            if ($anio === $hoy->year - 1 && checkdate((int) $m[2], (int) $m[3], $hoy->year)
                && CarbonImmutable::create($hoy->year, (int) $m[2], (int) $m[3], 0, 0, 0, 'America/Lima')->isAfter($hoy)) continue;

            return "Consultaste el año {$anio}, que el usuario no mencionó. El año actual es {$hoy->year}: si no pidió otro año, vuelve a consultar con {$hoy->year}.";
        }

        return null;
    }

    /**
     * Normaliza montos al formato peruano aunque el modelo los reescriba:
     * "S/ 8 898,00" o "S/ 8.898,00" → "S/ 8,898.00"; "S/ 197,73" → "S/ 197.73".
     */
    public static function normalizarMontos(string $texto): string
    {
        return preg_replace_callback('/S\/\s?(\d{1,3}(?:[ \x{00A0}\x{202F}.,]\d{3})*)([.,]\d{2})?(?!\d)/u', function (array $m) {
            $entero = preg_replace('/[^\d]/u', '', $m[1]);
            $decimales = isset($m[2]) && $m[2] !== '' ? substr($m[2], 1) : null;
            // "S/ 1,888" sin decimales y con grupo de 3: ya es formato de miles válido.
            return 'S/ ' . number_format((int) $entero, 0, '.', ',') . ($decimales !== null ? '.' . $decimales : '');
        }, $texto) ?? $texto;
    }

    /** Métricas de uso. No se registra el contenido de la conversación. */
    private function registrar($respuesta, int $rondas, array $herramientas, int $tokens, float $inicio): void
    {
        Log::info('asistente.respuesta', [
            'proveedor' => $respuesta->proveedor,
            'modelo' => $respuesta->modelo,
            'rondas' => $rondas,
            'herramientas' => $herramientas,
            'tokens' => $tokens ?: null,
            'ms' => (int) round((microtime(true) - $inicio) * 1000),
        ]);
    }

    protected function instrucciones(User $usuario, int $sucursalId): string
    {
        $ahora = CarbonImmutable::now('America/Lima');
        $sucursal = DB::table('inventario.sucursales')->where('id_sucursal', $sucursalId)->value('nombre') ?? 'Sede principal';
        $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        $f = fn (CarbonImmutable $fecha) => $fecha->toDateString();

        return implode("\n", [
            'Eres el asistente del sistema de ventas e inventario de una tienda en Perú. Conversas con el personal (cajeros, administradores) en un chat dentro del sistema.',
            '',
            '# Contexto',
            "- Sucursal activa: {$sucursal}. Todo lo que consultas es solo de esta sucursal.",
            '- Ahora: ' . $dias[$ahora->dayOfWeek] . ' ' . $f($ahora) . ', ' . $ahora->format('H:i') . ' (hora de Lima). El año actual es ' . $ahora->year . ': si el usuario no indica año, usa siempre ' . $ahora->year . ' (o ' . ($ahora->year - 1) . ' solo si la fecha aún no ha llegado este año).',
            '- Usuario: ' . ($usuario->nombres ?: 'usuario') . '.',
            '- Fechas ya calculadas: hoy ' . $f($ahora) . '; ayer ' . $f($ahora->subDay())
                . '; esta semana ' . $f($ahora->startOfWeek()) . ' a ' . $f($ahora)
                . '; semana pasada ' . $f($ahora->startOfWeek()->subWeek()) . ' a ' . $f($ahora->startOfWeek()->subDay())
                . '; este mes ' . $f($ahora->startOfMonth()) . ' a ' . $f($ahora)
                . '; mes pasado ' . $f($ahora->subMonthNoOverflow()->startOfMonth()) . ' a ' . $f($ahora->startOfMonth()->subDay())
                . '; últimos 7 días ' . $f($ahora->subDays(6)) . ' a ' . $f($ahora)
                . '; últimos 30 días ' . $f($ahora->subDays(29)) . ' a ' . $f($ahora) . '.',
            '',
            '# Precisión (lo más importante)',
            '- Todo dato del negocio sale de las herramientas. Nunca inventes, estimes ni completes cifras, nombres o fechas.',
            '- Copia montos, cantidades y porcentajes EXACTAMENTE como los devuelve la herramienta, carácter por carácter (formato peruano: S/ 8,898.00 con coma de miles y punto decimal). No los recalcules, redondees ni cambies de formato.',
            '- Antes de afirmar que no hay datos, comprueba que consultaste el período y el filtro correctos. Si el usuario o el historial muestran que sí hubo movimiento, vuelve a consultar.',
            '- Para comparar períodos usa comparar_ventas (ya trae diferencia y variación). No hagas cuentas tú.',
            '- Indica siempre el período al que corresponde el dato ("hoy", "del 16/11 al 18/11").',
            '- Si la herramienta no trae datos, dilo claramente ("no hubo ventas…"). Si la pregunta es ambigua (qué producto, qué fecha), pregunta antes de suponer.',
            '- Si una herramienta devuelve "error", explícalo con sencillez. Si no tienes permiso, dilo.',
            '',
            '# Alcance',
            '- Solo consultas. No puedes registrar ventas, anular comprobantes, cambiar precios ni stock: indica el módulo donde hacerlo.',
            '- Puedes responder dudas generales de uso del sistema o de la tienda, pero sin inventar datos.',
            '- Usa el historial para entender seguimientos como "¿y ayer?" o "¿y el segundo?".',
            '',
            '# Estilo',
            '- Español peruano, cordial y directo. Primero el dato principal; normalmente 1 a 4 frases.',
            '- Resalta las cifras clave con **negrita**. Para varios elementos usa una lista con "- " (máximo 10). Sin tablas ni encabezados.',
            '- Si aporta valor, cierra con una sugerencia breve de siguiente paso.',
            '- No menciones herramientas, JSON, bases de datos, SQL, modelos de IA ni identificadores internos.',
        ]);
    }
}
