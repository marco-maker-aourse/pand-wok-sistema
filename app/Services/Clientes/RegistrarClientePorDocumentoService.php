<?php

namespace App\Services\Clientes;

use App\Exceptions\DocumentLookupException;
use App\Models\Cliente;
use App\Models\TipoDocumento;
use App\Rules\Ruc;
use App\Services\External\DocumentLookupService;
use DomainException;

/**
 * Cliente a partir de su DNI o RUC, para emitir el comprobante en el momento del cobro.
 *
 * 1. Si ya existe (activo), se devuelve.
 * 2. Si no, se consulta la API de DNI/RUC (API Perú) y se registra con su nombre y dirección.
 * 3. Si la API no está configurada o no responde, se registra con el nombre escrito por el
 *    cajero; sin nombre se informa que hace falta (requiereNombre).
 *
 * El tipo se deduce del largo: 8 dígitos = DNI, 11 = RUC (con prefijo válido).
 */
class RegistrarClientePorDocumentoService
{
    public function __construct(private readonly DocumentLookupService $consultas) {}

    /** @return array{cliente: Cliente, origen: string} origen: local | consulta | manual */
    public function ejecutar(string $documento, ?string $nombre = null, ?string $direccion = null): array
    {
        $documento = preg_replace('/\D/', '', $documento) ?? '';
        $esRuc = strlen($documento) === 11;

        if (! $esRuc && strlen($documento) !== 8) {
            throw new DomainException('Escribe un DNI de 8 dígitos o un RUC de 11.');
        }
        if ($esRuc && ! preg_match(Ruc::PATRON, $documento)) {
            throw new DomainException(Ruc::MENSAJE);
        }

        if ($cliente = Cliente::query()->where('num_documento', $documento)->first()) {
            if (! $cliente->estado) {
                throw new DomainException('El cliente con ese documento está inactivo.');
            }

            return ['cliente' => $cliente, 'origen' => 'local'];
        }

        $tipo = TipoDocumento::query()->where('codigo_sunat', $esRuc ? '6' : '1')->where('estado', true)->first();
        if (! $tipo) {
            throw new DomainException('El tipo de documento '.($esRuc ? 'RUC' : 'DNI').' no está habilitado en el catálogo.');
        }

        $datos = null;
        $origen = 'manual';
        try {
            $resultado = $this->consultas->lookup($esRuc ? 'ruc' : 'dni', $documento);
            $datos = ['nombre' => $resultado['nombre'], 'direccion' => $resultado['direccion'] ?? null];
            $origen = 'consulta';
        } catch (DocumentLookupException $exception) {
            // 404: el documento no existe en RENIEC/SUNAT; no se registra con un nombre inventado.
            if ($exception->status === 404) {
                throw new DomainException('El documento no fue encontrado en '.($esRuc ? 'SUNAT' : 'RENIEC').'. Revísalo.');
            }
            $nombre = trim((string) $nombre);
            if ($nombre === '') {
                throw new RequiereNombreException($exception->status === 503
                    ? 'No se pudo consultar el documento en este momento. Escribe el nombre del cliente.'
                    : $exception->getMessage());
            }
            $datos = ['nombre' => $nombre, 'direccion' => $direccion];
        }

        $cliente = Cliente::query()->firstOrCreate(['num_documento' => $documento], [
            'id_tipo_documento' => $tipo->id_tipo_documento,
            'nombre_razon_social' => mb_strtoupper(mb_substr(trim($datos['nombre']), 0, 200)),
            'direccion_fiscal' => filled($datos['direccion']) ? mb_substr(trim((string) $datos['direccion']), 0, 255) : null,
            'estado' => true,
        ]);

        if (! $cliente->estado) {
            throw new DomainException('El cliente con ese documento está inactivo.');
        }

        return ['cliente' => $cliente, 'origen' => $cliente->wasRecentlyCreated ? $origen : 'local'];
    }
}
