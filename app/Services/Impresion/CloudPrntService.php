<?php

namespace App\Services\Impresion;

use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Cola HTTP de comandas Star CloudPRNT. Una descarga física por trabajo y confirmación idempotente. */
class CloudPrntService
{
    public const FORMATO = 'text/plain';

    public function consultar(Impresora $impresora, int $codigo, bool $ocupada): ?int
    {
        return DB::transaction(function () use ($impresora, $codigo, $ocupada): ?int {
            $this->bloquearImpresora($impresora);
            $cola = $this->cola($impresora);
            $enCurso = (clone $cola)->where('estado', 'ENTREGADO_AGENTE');

            $impresora->update([
                'estado_conexion' => $this->codigoCorrecto($codigo) ? 'conectada' : 'error',
                'ultima_verificacion' => now(),
                'ultimo_error_conexion' => $this->codigoCorrecto($codigo) ? null : "Estado CloudPRNT {$codigo}.",
            ]);

            if ($ocupada) {
                return null;
            }

            (clone $enCurso)->where('reclamado_en', '<=', now()->subMinutes(2))->update([
                'estado' => 'REQUIERE_REVISION',
                'detalle_error' => 'La impresora no confirmó el trabajo. Revisa el papel antes de reimprimir.',
                'procesado_en' => now(),
            ]);

            if (! $this->codigoCorrecto($codigo) || $enCurso->exists()) {
                return null;
            }

            return $this->pendientes($cola)->value('id_trabajo_impresion');
        }, 3);
    }

    public function descargar(Impresora $impresora, int $id): string
    {
        return DB::transaction(function () use ($impresora, $id): string {
            $this->bloquearImpresora($impresora);
            $cola = $this->cola($impresora);
            $trabajo = (clone $cola)->findOrFail($id);
            abort_unless((int) $this->pendientes($cola)->value('id_trabajo_impresion') === $id, 409);
            abort_if($this->cola($impresora)->where('estado', 'ENTREGADO_AGENTE')->exists(), 409);
            $ticket = $this->ticket($trabajo->contenido['comanda']);

            $trabajo->update([
                'estado' => 'ENTREGADO_AGENTE',
                'reclamado_en' => now(),
                'intentos' => $trabajo->intentos + 1,
            ]);

            return $ticket;
        }, 3);
    }

    public function confirmar(Impresora $impresora, int $id, int $codigo): void
    {
        DB::transaction(function () use ($impresora, $id, $codigo): void {
            $this->bloquearImpresora($impresora);
            $trabajo = $this->cola($impresora)->findOrFail($id);
            $correcto = $this->codigoCorrecto($codigo);
            $estado = $correcto ? 'CONFIRMADO' : 'FALLIDO';
            if ($trabajo->estado === $estado) {
                return;
            }

            // Una impresora puede rechazar un media type sin haber descargado el trabajo.
            $rechazadoAntesDeDescarga = ! $correcto && $trabajo->estado === 'PENDIENTE'
                && (int) $this->pendientes($this->cola($impresora))->value('id_trabajo_impresion') === $id;
            abort_unless($rechazadoAntesDeDescarga || in_array($trabajo->estado, ['ENTREGADO_AGENTE', 'REQUIERE_REVISION'], true), 409);

            $error = $correcto ? null : "La impresora informó el código CloudPRNT {$codigo}.";
            $trabajo->update([
                'estado' => $estado,
                'procesado_en' => now(),
                'resultado_dispositivo' => (string) $codigo,
                'detalle_error' => $error,
            ]);
            $impresora->update([
                'ultima_impresion' => now(),
                'ultimo_resultado_impresion' => $correcto ? 'correcta' : 'fallida',
                'ultimo_error_impresion' => $error,
            ]);
        }, 3);
    }

    private function bloquearImpresora(Impresora $impresora): void
    {
        Impresora::withoutGlobalScopes()->where('activo', true)
            ->where('cloudprnt_clave_hash', $impresora->cloudprnt_clave_hash)
            ->lockForUpdate()->findOrFail($impresora->getKey(), ['id_impresora']);
    }

    private function cola(Impresora $impresora): Builder
    {
        return TrabajoImpresion::withoutGlobalScopes()->where('id_sucursal', $impresora->id_sucursal)
            ->where('id_impresora_cloudprnt', $impresora->id_impresora)
            ->where('tipo', 'COMANDA');
    }

    private function pendientes(Builder $cola): Builder
    {
        return $cola->where('estado', 'PENDIENTE')->where('disponible_desde', '<=', now())
            ->orderBy('id_trabajo_impresion');
    }

    private function codigoCorrecto(int $codigo): bool
    {
        return $codigo >= 200 && $codigo < 300;
    }

    /** Texto ASCII sin controles de impresora. La impresora aplica el corte del trabajo. */
    private function ticket(array $comanda): string
    {
        $cuenta = match ($comanda['tipo_pedido']) {
            'MESA' => 'Mesa '.$comanda['mesa'],
            'PARA_LLEVAR' => 'Para llevar',
            'DELIVERY' => 'Delivery',
            default => $comanda['tipo_pedido'],
        };

        $lineas = [
            "{$comanda['tipo']} #{$comanda['numero']}",
            $comanda['estacion'],
            "{$cuenta} / Pedido #{$comanda['pedido']}",
            "{$comanda['fecha']} {$comanda['hora']}",
        ];
        foreach (['mozo' => 'Mozo', 'nota' => 'Nota'] as $campo => $etiqueta) {
            if (! empty($comanda[$campo])) {
                $lineas[] = "{$etiqueta}: {$comanda[$campo]}";
            }
        }
        $lineas[] = str_repeat('-', 28);
        foreach ($comanda['items'] as $item) {
            $lineas[] = "{$item['cantidad']} x {$item['nombre']}";
            if (! empty($item['cuenta'])) {
                $cuentaItem = $item['cuenta'];
                $destino = $cuentaItem['mesa'] ? "Mesa {$cuentaItem['mesa']} / " : '';
                $lineas[] = "  {$destino}Pedido #{$cuentaItem['numero']}";
            }
            foreach ($item['opciones'] as $opcion) {
                $lineas[] = "  + {$opcion}";
            }
            if ($item['observacion']) {
                $lineas[] = "  Nota: {$item['observacion']}";
            }
        }

        $lineas = array_map(fn (string $linea): string => preg_replace('/[^\x20-\x7E]/', ' ', Str::ascii($linea)), $lineas);

        return implode("\n", $lineas)."\n\n";
    }
}
