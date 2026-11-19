<?php

namespace App\Services\Asistente\Llm;

use RuntimeException;

final class ErrorProveedorLlm extends RuntimeException
{
    public function __construct(string $mensaje, public readonly ?int $estado = null, public readonly ?int $reintentarEn = null)
    {
        parent::__construct($mensaje);
    }

    public function esLimiteDeUso(): bool
    {
        return $this->estado === 429;
    }
}
