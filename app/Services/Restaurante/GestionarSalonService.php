<?php

namespace App\Services\Restaurante;

use App\Models\Restaurante\Area;
use App\Models\Restaurante\Mesa;
use App\Models\Restaurante\Pedido;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Configuración del salón por sucursal: áreas, mesas y estados manuales de mesa.
 * OCUPADA nunca se asigna a mano: solo la pone o la quita una cuenta.
 */
class GestionarSalonService
{
    public function __construct(private readonly BloqueoSalonService $bloqueo) {}

    public function guardarArea(array $datos, int $sucursalId, ?int $areaId = null): Area
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw new DomainException('El área necesita un nombre.');
        }

        $duplicada = Area::query()
            ->where('id_sucursal', $sucursalId)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->when($areaId, fn ($query) => $query->whereKeyNot($areaId))
            ->exists();
        if ($duplicada) {
            throw new DomainException("Ya existe el área «{$nombre}» en esta sucursal.");
        }

        $valores = [
            'nombre' => $nombre,
            'orden' => (int) ($datos['orden'] ?? 0),
            'activo' => (bool) ($datos['activo'] ?? true),
        ];

        if ($areaId === null) {
            return Area::query()->create([...$valores, 'id_sucursal' => $sucursalId]);
        }

        $area = $this->area($areaId, $sucursalId);
        if (! $valores['activo'] && $area->activo) {
            $this->validarSinCuentasAbiertas(Mesa::query()->where('id_area', $area->id_area)->pluck('id_mesa')->all(), "el área {$area->nombre}");
        }
        $area->update($valores);

        return $area;
    }

    public function guardarMesa(array $datos, int $sucursalId, ?int $mesaId = null): Mesa
    {
        $codigo = mb_strtoupper(trim((string) ($datos['codigo'] ?? '')));
        if ($codigo === '') {
            throw new DomainException('La mesa necesita un código.');
        }
        $capacidad = (int) ($datos['capacidad'] ?? 4);
        if ($capacidad < 1 || $capacidad > 50) {
            throw new DomainException('La capacidad debe estar entre 1 y 50 personas.');
        }

        // El área debe ser de la misma sucursal; la FK compuesta lo repite en la base.
        $area = $this->area((int) ($datos['id_area'] ?? 0), $sucursalId);

        $duplicada = Mesa::query()
            ->where('id_sucursal', $sucursalId)
            ->where('codigo', $codigo)
            ->when($mesaId, fn ($query) => $query->whereKeyNot($mesaId))
            ->exists();
        if ($duplicada) {
            throw new DomainException("Ya existe la mesa {$codigo} en esta sucursal.");
        }

        $valores = [
            'id_area' => $area->id_area,
            'codigo' => $codigo,
            'capacidad' => $capacidad,
            'orden' => (int) ($datos['orden'] ?? 0),
            'activo' => (bool) ($datos['activo'] ?? true),
        ];

        if ($mesaId === null) {
            return Mesa::query()->create([...$valores, 'id_sucursal' => $sucursalId, 'estado' => Mesa::DISPONIBLE]);
        }

        return DB::transaction(function () use ($mesaId, $sucursalId, $valores): Mesa {
            $mesa = $this->bloqueo->mesa($mesaId, $sucursalId);
            if (! $valores['activo'] && $mesa->activo) {
                $this->validarSinCuentasAbiertas([$mesa->id_mesa], "la mesa {$mesa->codigo}");
            }
            $mesa->update($valores);

            return $mesa;
        });
    }

    /** Reservar, liberar una reserva o sacar de servicio. */
    public function cambiarEstadoMesa(int $mesaId, string $estado, int $sucursalId): Mesa
    {
        $permitidos = [Mesa::DISPONIBLE, Mesa::RESERVADA, Mesa::FUERA_SERVICIO];
        if (! in_array($estado, $permitidos, true)) {
            throw new DomainException('Ese estado no se puede asignar manualmente.');
        }

        return DB::transaction(function () use ($mesaId, $estado, $sucursalId): Mesa {
            $mesa = $this->bloqueo->mesa($mesaId, $sucursalId);
            if ($mesa->estado === Mesa::OCUPADA) {
                throw new DomainException("La mesa {$mesa->codigo} tiene una cuenta abierta; ciérrala o transfiérela primero.");
            }
            $mesa->update(['estado' => $estado]);

            return $mesa;
        });
    }

    private function area(int $areaId, int $sucursalId): Area
    {
        $area = Area::query()->where('id_sucursal', $sucursalId)->find($areaId);
        if (! $area) {
            throw new DomainException('El área no existe en esta sucursal.');
        }

        return $area;
    }

    private function validarSinCuentasAbiertas(array $mesaIds, string $descripcion): void
    {
        $abiertas = Pedido::query()->whereIn('id_mesa', $mesaIds)->where('estado', Pedido::ABIERTO)->exists();
        if ($abiertas) {
            throw new DomainException("No se puede desactivar {$descripcion}: tiene cuentas abiertas.");
        }
    }
}
