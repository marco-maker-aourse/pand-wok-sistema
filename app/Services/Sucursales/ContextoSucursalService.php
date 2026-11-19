<?php

namespace App\Services\Sucursales;

use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

/**
 * Única fuente del contexto multisucursal.
 *
 * La sucursal de trabajo elegida se guarda solo en la sesión y se valida cada
 * vez que se resuelve: si se desactivó o el usuario perdió el acceso, se vuelve
 * a su sede principal. El navegador nunca decide la sucursal por sí solo.
 */
class ContextoSucursalService
{
    public const CLAVE_SESION = 'sucursal_activa';

    /** Sucursal con la que operan consultas, scopes y servicios en esta petición. */
    public function resolver(User $user): ?int
    {
        $sede = $user->id_sucursal ? (int) $user->id_sucursal : null;
        $sesion = $this->sesion();
        $elegida = $sesion?->get(self::CLAVE_SESION);

        if ($elegida === null || (int) $elegida === $sede) {
            return $sede;
        }

        if ($this->puedeOperar($user, (int) $elegida)) {
            return (int) $elegida;
        }

        $sesion->forget(self::CLAVE_SESION);

        return $sede;
    }

    public function cambiar(User $user, int $branchId): Sucursal
    {
        $sucursal = $user->sucursalesDisponibles()->firstWhere('id_sucursal', $branchId);
        if (! $sucursal || ! $this->sesion()) {
            throw ValidationException::withMessages(['id_sucursal' => 'No tienes acceso a la sucursal seleccionada.']);
        }

        $this->sesion()->put(self::CLAVE_SESION, $branchId);
        $user->usarSucursalActiva($branchId);

        return $sucursal;
    }

    private function puedeOperar(User $user, int $branchId): bool
    {
        return Sucursal::query()->whereKey($branchId)->where('estado', true)->exists()
            && $user->canAccessBranch($branchId);
    }

    private function sesion(): ?Session
    {
        $request = request();

        return $request->hasSession() ? $request->session() : null;
    }
}
