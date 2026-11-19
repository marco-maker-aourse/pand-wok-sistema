<?php

namespace App\Models\Concerns;

use App\Models\Scopes\SucursalScope;

/** Agrega el aislamiento multi-sucursal a modelos con id_sucursal. */
trait BelongsToSucursal
{
    protected static function bootBelongsToSucursal(): void
    {
        static::addGlobalScope(new SucursalScope());
    }
}
