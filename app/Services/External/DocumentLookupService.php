<?php

namespace App\Services\External;

use App\Exceptions\DocumentLookupException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DocumentLookupService
{
    public function isConfigured(?string $type = null): bool
    {
        $hasToken = filled(config('services.api_peru.token'));

        if ($type !== null) {
            return $hasToken && filled(config("services.api_peru.{$type}_url"));
        }

        return $hasToken && (filled(config('services.api_peru.dni_url')) || filled(config('services.api_peru.ruc_url')));
    }

    /**
     * @return array{documento: string, nombre: string, direccion: ?string, estado: ?string}
     */
    public function lookup(string $type, string $document): array
    {
        $url = config("services.api_peru.{$type}_url");
        $token = config('services.api_peru.token');

        if (blank($url) || blank($token)) {
            throw new DocumentLookupException('La consulta documental no está configurada.', 503);
        }

        $url = str_contains($url, '{document}')
            ? str_replace('{document}', rawurlencode($document), $url)
            : rtrim($url, '/').'/'.rawurlencode($document);

        // El proveedor se define por entorno: ApiPeruDev usa Bearer; APIsPerú clásico usa query token.
        // El secreto nunca se transmite al navegador ni se registra en logs.
        $request = Http::acceptJson()
            ->timeout(8)
            ->retry(2, 250);

        try {
            $response = config('services.api_peru.auth_mode') === 'query'
                ? $request->get($url, ['token' => $token])
                : $request->withToken($token)->get($url);
        } catch (ConnectionException) {
            throw new DocumentLookupException(
                'No fue posible conectar con el proveedor de consultas. Inténtalo nuevamente.',
                503,
            );
        }

        if ($response->failed()) {
            if ($response->status() === 404) {
                throw new DocumentLookupException('El documento no fue encontrado.', 404);
            }

            if ($response->status() === 401) {
                throw new DocumentLookupException('Token de consulta no autorizado o expirado en ApiPeruDev.', 503);
            }

            throw new DocumentLookupException('El servicio de consulta documental no está disponible. Inténtalo nuevamente.', 503);
        }

        $payload = $response->json('data', $response->json());

        $name = $type === 'ruc'
            ? ($payload['razonSocial'] ?? $payload['nombre_o_razon_social'] ?? $payload['nombre_razon_social'] ?? $payload['razon_social'] ?? null)
            : ($payload['nombre_completo'] ?? trim(implode(' ', array_filter([
                $payload['nombres'] ?? null,
                $payload['apellidoPaterno'] ?? null,
                $payload['apellido_paterno'] ?? null,
                $payload['apellidoMaterno'] ?? null,
                $payload['apellido_materno'] ?? null,
            ]))));

        if (blank($name)) {
            throw new DocumentLookupException('El documento no fue encontrado.', 404);
        }

        return [
            'documento' => $document,
            'nombre'    => $name,
            'direccion' => $payload['direccion'] ?? $payload['domicilio_fiscal'] ?? $payload['direccion_fiscal'] ?? null,
            'estado'    => $payload['estado'] ?? null,
        ];
    }
}
