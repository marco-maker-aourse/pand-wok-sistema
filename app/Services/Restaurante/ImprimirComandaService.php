<?php

namespace App\Services\Restaurante;

use App\Models\Impresora;
use App\Models\Restaurante\Comanda;
use App\Models\Restaurante\Estacion;
use App\Models\Restaurante\PedidoDetalle;
use App\Models\TrabajoImpresion;
use DomainException;
use Illuminate\Database\Eloquent\Collection as Modelos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Encola la impresión de comandas en la cola existente (operaciones.trabajos_impresion).
 * El agente de la terminal de la estación la toma y la envía a la impresora de cocina.
 *
 * Duplicados: cada impresión tiene una clave única (comanda:{id}, comanda:{id}:anulacion:…,
 * comanda:{id}:reimpresion:{solicitud}); repetir la misma solicitud devuelve el mismo trabajo.
 * Sin impresora configurada no se encola nada: la pantalla de cocina sigue funcionando.
 */
class ImprimirComandaService
{
    public const COMANDA = 'COMANDA';
    public const ANULACION = 'ANULACION';
    public const REIMPRESION = 'REIMPRESION';

    public function comanda(Comanda $comanda): ?TrabajoImpresion
    {
        $items = $comanda->detalles()->with('modificadores')->where('estado', '!=', PedidoDetalle::ANULADO)->get();

        return $this->encolar($comanda, "comanda:{$comanda->id_comanda}", self::COMANDA, $items);
    }

    /** Conserva la solicitud original y permite una sola impresión activa por decisión del operador. */
    public function reimprimir(Comanda $comanda, ?string $solicitud = null): ?TrabajoImpresion
    {
        $clave = "comanda:{$comanda->id_comanda}:reimpresion:".($solicitud ?? Str::uuid());

        return DB::transaction(function () use ($comanda, $clave): ?TrabajoImpresion {
            $actual = Comanda::query()->where('id_sucursal', $comanda->id_sucursal)
                ->lockForUpdate()->findOrFail($comanda->getKey());
            $trabajos = TrabajoImpresion::withoutGlobalScopes()->where('id_comanda', $actual->getKey());
            $previo = (clone $trabajos)->where('clave_idempotencia', $clave)->first();
            if ($previo) {
                return $previo;
            }
            if ($trabajos->whereIn('estado', ['PENDIENTE', 'ENTREGADO_AGENTE'])->exists()) {
                throw new DomainException('Ya hay una impresión en cola o en curso para esta comanda.');
            }

            $items = $actual->detalles()->with('modificadores')->where('estado', '!=', PedidoDetalle::ANULADO)->get();

            return $this->encolar($actual, $clave, self::REIMPRESION, $items);
        }, 3);
    }

    /** Avisa a la estación que unos ítems ya enviados se anularon. */
    public function anulacion(Comanda $comanda, Collection $detalles): ?TrabajoImpresion
    {
        $ids = $detalles->pluck('id_detalle_pedido')->sort()->implode('-');
        $prefijo = "comanda:{$comanda->id_comanda}:anulacion:";
        $clave = $prefijo.(strlen($prefijo.$ids) <= 120 ? $ids : hash('sha256', $ids));

        return $this->encolar($comanda, $clave, self::ANULACION, (new Modelos($detalles->all()))->load('modificadores'));
    }

    /** Estado de impresión más reciente de cada comanda: id_comanda => estado del trabajo. */
    public function estados(array $comandaIds): Collection
    {
        if ($comandaIds === []) {
            return collect();
        }

        return TrabajoImpresion::withoutGlobalScopes()
            ->whereIn('id_comanda', $comandaIds)
            ->orderBy('id_trabajo_impresion')
            ->get(['id_comanda', 'estado', 'detalle_error'])
            ->keyBy('id_comanda')
            ->map(fn (TrabajoImpresion $trabajo): array => ['estado' => $trabajo->estado, 'error' => $trabajo->detalle_error]);
    }

    private function encolar(Comanda $comanda, string $clave, string $tipoComanda, Collection $items): ?TrabajoImpresion
    {
        $estacion = $comanda->id_estacion
            ? Estacion::withoutGlobalScopes()->with(['impresora', 'terminal'])->find($comanda->id_estacion)
            : null;

        if (! $this->puedeImprimir($estacion) || $items->isEmpty()) {
            return null;
        }

        return TrabajoImpresion::withoutGlobalScopes()->firstOrCreate(
            ['clave_idempotencia' => $clave],
            [
                'id_sucursal' => $comanda->id_sucursal,
                'id_terminal' => $estacion->id_terminal,
                'id_impresora_cloudprnt' => $estacion->id_terminal === null ? $estacion->id_impresora : null,
                'id_comanda' => $comanda->id_comanda,
                'tipo' => 'COMANDA',
                'estado' => 'PENDIENTE',
                'contenido' => $this->contenido($comanda, $estacion, $tipoComanda, $items),
            ],
        );
    }

    private function puedeImprimir(?Estacion $estacion): bool
    {
        return $estacion
            && $estacion->activo
            && $estacion->impresora?->activo
            && ($estacion->id_terminal ? $estacion->terminal?->activo : $estacion->impresora->cloudprnt_clave_hash);
    }

    /**
     * Contrato con el agente (tipo COMANDA, versión 1). Sin precios: la cocina solo
     * necesita qué preparar, para quién y con qué indicaciones.
     */
    private function contenido(Comanda $comanda, Estacion $estacion, string $tipoComanda, Collection $items): array
    {
        $comanda->loadMissing(['pedido.mesa.area', 'pedido.usuario', 'usuario']);
        $pedido = $comanda->pedido;
        (new Modelos($items->all()))->loadMissing(['pedido:id_pedido,numero,tipo,id_mesa', 'pedido.mesa:id_mesa,codigo']);
        $emision = ($comanda->enviada_en ?? now())->copy()->setTimezone(config('app.timezone_negocio', 'America/Lima'));
        $impresora = $estacion->impresora;

        return [
            'version' => 1,
            'copias' => $estacion->copias,
            'impresora' => $this->impresora($impresora),
            'comanda' => [
                'tipo' => $tipoComanda,
                'numero' => $comanda->numero,
                'estacion' => $estacion->nombre,
                'pedido' => $pedido->numero,
                'tipo_pedido' => $pedido->tipo,
                'mesa' => $pedido->mesa?->codigo,
                'area' => $pedido->mesa?->area?->nombre,
                'cliente' => $pedido->nombre_contacto,
                'comensales' => $pedido->comensales,
                'mozo' => $comanda->usuario?->nombres ?? $pedido->usuario?->nombres,
                'nota' => $pedido->observacion,
                'fecha' => $emision->format('d/m/Y'),
                'hora' => $emision->format('H:i'),
                'items' => $items->map(fn (PedidoDetalle $detalle): array => [
                    'cantidad' => rtrim(rtrim(number_format((float) $detalle->cantidad, 3, '.', ''), '0'), '.'),
                    'nombre' => $detalle->nombre_producto,
                    'opciones' => $detalle->modificadores->pluck('nombre_opcion')->values()->all(),
                    'observacion' => $detalle->observacion,
                    'cuenta' => (int) $detalle->id_pedido === (int) $pedido->id_pedido ? null : [
                        'numero' => $detalle->pedido->numero,
                        'tipo' => $detalle->pedido->tipo,
                        'mesa' => $detalle->pedido->mesa?->codigo,
                    ],
                ])->values()->all(),
            ],
        ];
    }

    private function impresora(Impresora $impresora): array
    {
        return [
            'id' => $impresora->id_impresora,
            'conexion' => $impresora->tipo_conexion,
            'identificador' => $impresora->identificador,
            'puerto' => $impresora->puerto,
            'ancho_papel_mm' => $impresora->ancho_papel_mm,
            'escpos' => $impresora->soporta_escpos,
            'corte_automatico' => $impresora->corte_automatico,
            // La cocina nunca abre el cajón de dinero.
            'abre_cajon' => false,
        ];
    }
}
