<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Aísla datos operativos de la sucursal activa en peticiones autenticadas. */
class SucursalScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole() || ! auth()->check()) {
            return;
        }

        $user = auth()->user();

        // La sucursal de trabajo puede diferir de la sede si el usuario cambió de sucursal.
        if ($user?->id_sucursal) {
            $builder->where($model->qualifyColumn('id_sucursal'), $user->idSucursalActiva());
        }
    }
}
