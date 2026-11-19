<?php

namespace App\Services\Asistente\Llm;

final class RespuestaLlm
{
    public string $proveedor = '';
    public string $modelo = '';
    public int $milisegundos = 0;

    /** @param list<array{id: string, type: string, function: array{name: string, arguments: string}}> $llamadas */
    public function __construct(
        public readonly string $texto,
        public readonly array $llamadas = [],
        public readonly ?int $tokens = null,
    ) {
    }
}
