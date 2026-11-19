<?php

namespace App\Http\Controllers\Restaurante;

use DomainException;
use Illuminate\Validation\ValidationException;

/**
 * Las reglas de negocio de los servicios lanzan DomainException; en HTTP se devuelven
 * como error de validación para que Inertia las muestre sin romper la página.
 */
trait ConvierteErroresDeDominio
{
    /**
     * @template T
     * @param  callable(): T  $operacion
     * @return T
     */
    protected function enDominio(callable $operacion, string $campo = 'general'): mixed
    {
        try {
            return $operacion();
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([$campo => $exception->getMessage()]);
        }
    }
}
