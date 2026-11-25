<?php

namespace App\Services\Ventas;

use App\Services\Configuracion\DatosGeneralesService;
use Illuminate\Support\Facades\DB;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use Throwable;

/**
 * Revisa, sin enviar nada a SUNAT, si la facturación electrónica de una sucursal está lista y
 * dice exactamente qué falta. Nunca expone claves, rutas ni el contenido del certificado:
 * solo si cada requisito se cumple y una indicación para resolverlo.
 */
class DiagnosticoSunatService
{
    public function __construct(private readonly DatosGeneralesService $datosGenerales) {}

    /**
     * @return array{listo: bool, entorno: string, revisiones: array<int, array{clave: string, ok: bool, titulo: string, detalle: string}>}
     */
    public function revisar(int $sucursalId): array
    {
        $empresa = $this->datosGenerales->empresaDe($sucursalId);
        $codigo = DB::table('inventario.sucursales')->where('id_sucursal', $sucursalId)->value('codigo_establecimiento');
        $endpoint = (string) config('services.sunat.endpoint_cpe', '');
        $beta = $endpoint === '' || str_contains($endpoint, 'beta');
        $certificado = $this->certificado();

        $revisiones = [
            $this->item('ruc', (bool) preg_match('/^\d{11}$/', (string) $empresa?->ruc_empresa), 'RUC del emisor', 'Configura el RUC de 11 dígitos en Datos generales.'),
            $this->item('razon_social', filled($empresa?->razon_social), 'Razón social', 'Escríbela en Datos generales tal como figura en SUNAT.'),
            $this->item('direccion', filled($empresa?->direccion_fiscal), 'Dirección fiscal', 'Escríbela en Datos generales.'),
            $this->item('establecimiento', (bool) preg_match('/^\d{4}$/', (string) $codigo), 'Código de establecimiento de esta sucursal',
                'Escribe el código SUNAT de 4 dígitos de la sucursal (0000 para el domicilio fiscal) en Sucursales.'),
            $this->item('usuario_sol', trim((string) config('services.sunat.usuario_sol', '')) !== '', 'Usuario SOL', 'Falta SUNAT_USUARIO_SOL en el .env del servidor.'),
            $this->item('clave_sol', (string) config('services.sunat.clave_sol', '') !== '', 'Clave SOL', 'Falta SUNAT_CLAVE_SOL en el .env del servidor.'),
            $this->item('certificado', $certificado['ok'], 'Certificado digital', $certificado['detalle']),
            $this->item('extensiones', extension_loaded('soap') && extension_loaded('openssl') && extension_loaded('zip') && class_exists(XMLSecurityDSig::class),
                'Extensiones PHP (soap, openssl, zip) y firma XML', 'Activa las extensiones soap, openssl y zip en el PHP del hosting.'),
            $this->item('entorno', ! $beta, $beta ? 'Envío a SUNAT de PRUEBAS (beta)' : 'Envío a SUNAT de producción',
                $beta ? 'Los comprobantes aceptados en beta no tienen validez. Configura SUNAT_ENDPOINT_CPE con el servicio de producción.' : ''),
        ];

        return [
            'listo' => collect($revisiones)->every('ok'),
            'entorno' => $beta ? 'beta' : 'produccion',
            'revisiones' => $revisiones,
        ];
    }

    /** @return array{ok: bool, detalle: string} */
    private function certificado(): array
    {
        $ruta = (string) config('services.sunat.certificado', 'keys/certificado.p12');
        $ruta = str_starts_with($ruta, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $ruta) ? $ruta : storage_path('app/'.$ruta);
        if (! is_file($ruta) || ! is_readable($ruta)) {
            return ['ok' => false, 'detalle' => 'No se encontró el archivo del certificado (.pfx/.p12) en el servidor.'];
        }
        try {
            $partes = [];
            if (! openssl_pkcs12_read((string) file_get_contents($ruta), $partes, (string) config('services.sunat.certificado_clave', ''))) {
                return ['ok' => false, 'detalle' => 'El certificado no abre: la contraseña (SUNAT_CERTIFICATE_PASSWORD) no corresponde.'];
            }
            $datos = openssl_x509_parse($partes['cert'] ?? '');
            $vence = isset($datos['validTo_time_t']) ? (int) $datos['validTo_time_t'] : null;
            if ($vence !== null && $vence < time()) {
                return ['ok' => false, 'detalle' => 'El certificado venció el '.date('d/m/Y', $vence).'. Renuévalo.'];
            }

            return ['ok' => true, 'detalle' => $vence ? 'Vigente hasta el '.date('d/m/Y', $vence).'.' : ''];
        } catch (Throwable) {
            return ['ok' => false, 'detalle' => 'No se pudo leer el certificado.'];
        }
    }

    private function item(string $clave, bool $ok, string $titulo, string $ayuda): array
    {
        return ['clave' => $clave, 'ok' => $ok, 'titulo' => $titulo, 'detalle' => $ok && $clave !== 'certificado' ? '' : $ayuda];
    }
}
