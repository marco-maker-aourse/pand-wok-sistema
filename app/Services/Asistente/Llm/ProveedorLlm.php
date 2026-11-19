<?php

namespace App\Services\Asistente\Llm;

/** Conexión a un proveedor compatible con la API de OpenAI, leída de config/asistente.php. */
final class ProveedorLlm
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $url,
        public readonly ?string $token,
        public readonly string $modelo,
        public readonly ?string $razonamiento = null,
    ) {
    }

    /** Devuelve null si la conexión está incompleta (sin URL, modelo o clave requerida). */
    public static function desdeConfig(string $nombre, array $config): ?self
    {
        $url = trim((string) ($config['url'] ?? ''));
        $modelo = trim((string) ($config['modelo'] ?? ''));
        $token = trim((string) ($config['token'] ?? '')) ?: null;
        $requiereToken = (bool) ($config['requiere_token'] ?? true);

        if ($url === '' || $modelo === '' || ($requiereToken && $token === null)) return null;
        if (! filter_var($url, FILTER_VALIDATE_URL)) return null;

        $razonamiento = trim((string) ($config['razonamiento'] ?? '')) ?: null;
        // reasoning_effort solo lo aceptan modelos de razonamiento; enviarlo a otros provoca error 400.
        if ($razonamiento && ! preg_match('/gpt-oss|o\d|gpt-5|reason/i', $modelo)) $razonamiento = null;

        return new self($nombre, $url, $token, $modelo, $razonamiento);
    }
}
