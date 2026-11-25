<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSucursal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabajoImpresion extends Model
{
    use BelongsToSucursal;

    protected $table = 'operaciones.trabajos_impresion';
    protected $primaryKey = 'id_trabajo_impresion';

    protected $fillable = [
        'id_sucursal', 'id_terminal', 'id_impresora_cloudprnt', 'id_venta', 'id_comanda', 'tipo', 'estado', 'clave_idempotencia',
        'contenido', 'intentos', 'max_intentos', 'disponible_desde', 'reclamado_en',
        'procesado_en', 'resultado_dispositivo', 'detalle_error',
    ];

    protected function casts(): array
    {
        return [
            'contenido' => 'array', 'disponible_desde' => 'datetime', 'reclamado_en' => 'datetime',
            'procesado_en' => 'datetime', 'intentos' => 'integer', 'max_intentos' => 'integer',
        ];
    }

    /**
     * Cancela lo que esperó en la cola más de la vigencia (config impresion.vigencia_minutos):
     * al reconectar una impresora apagada no deben salir de golpe comandas y tickets viejos.
     * Cuenta desde que se creó: los reintentos por impresora apagada no alargan la vigencia.
     */
    public static function vencerPendientes(Builder $cola): int
    {
        $minutos = max(1, (int) config('impresion.vigencia_minutos', 15));

        return (clone $cola)->where('estado', 'PENDIENTE')
            ->where('created_at', '<', now()->subMinutes($minutos))
            ->update([
                'estado' => 'CANCELADO',
                'detalle_error' => "No se imprimió a tiempo: la impresora estuvo desconectada más de {$minutos} min. Reimprime si aún hace falta.",
                'procesado_en' => now(),
            ]);
    }

    public function terminal(): BelongsTo { return $this->belongsTo(TerminalPos::class, 'id_terminal', 'id_terminal'); }
    public function venta(): BelongsTo { return $this->belongsTo(Venta::class, 'id_venta', 'id_venta'); }
}
