<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error controlado al consultar un proveedor de identidad externo.
 *
 * El código HTTP se conserva para comunicar una causa útil sin filtrar el
 * cuerpo, token o detalles internos del proveedor.
 */
class DocumentLookupException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 502)
    {
        parent::__construct($message);
    }
}
