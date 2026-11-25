<?php

namespace App\Services\Configuracion;

use App\Models\Empresa;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Cache;

/**
 * Punto único de lectura de los Datos Generales (configuracion.empresa).
 *
 * El sistema atiende a una sola empresa con varias sucursales. La empresa tiene
 * una sola fila: RUC, razón social, IGV, logo, colores y textos son comunes a
 * todas las sucursales y a todos los roles. Lo propio de cada sucursal (nombre,
 * dirección, código de establecimiento y series) vive en sus propias tablas.
 */
class DatosGeneralesService
{
    private const MINUTOS_CACHE = 10;

    private const CLAVE_VERSION = 'empresa_config_version';

    /** Campos de la empresa que se muestran y editan en pantalla. */
    public const CAMPOS_EDITABLES = [
        'id_empresa', 'ruc_empresa', 'razon_social', 'nombre_comercial', 'direccion_fiscal',
        'igv_porcentaje', 'uit_valor', 'pie_ticket', 'logo_path',
        'color_primario', 'color_secundario', 'texto_login_titulo', 'texto_login_descripcion',
    ];

    private const VALORES_VISUALES = [
        'color_primario'          => '#0e7490',
        'color_secundario'        => '#0f172a',
        'texto_login_titulo'      => 'Bienvenido',
        'texto_login_descripcion' => 'Punto de venta y gestión del restaurante.',
    ];

    /** La empresa configurada, o null si todavía no se registró. */
    public function empresa(): ?Empresa
    {
        return Empresa::query()->orderBy('id_empresa')->first();
    }

    /**
     * Registro que se muestra y edita en Datos Generales. Si todavía no existe se
     * devuelve uno nuevo sin persistir: una consulta no crea datos fiscales.
     */
    public function paraEdicion(): Empresa
    {
        return $this->empresa() ?? new Empresa(self::VALORES_VISUALES);
    }

    /** Datos del emisor que se imprimen en tickets y comprobantes de la sucursal. */
    public function emisor(int $branchId): ?Empresa
    {
        $empresa = $this->empresa();

        if (! $empresa) {
            return null;
        }

        $sucursal = Sucursal::query()->whereKey($branchId)->first(['nombre', 'direccion', 'telefono', 'codigo_establecimiento']);

        return (new Empresa)->forceFill([
            'ruc_empresa' => $empresa->ruc_empresa,
            'razon_social' => $empresa->razon_social,
            'nombre_comercial' => $empresa->nombre_comercial,
            'direccion_fiscal' => $empresa->direccion_fiscal,
            'pie_ticket' => $empresa->pie_ticket,
            'igv_porcentaje' => $empresa->igv_porcentaje,
            'codigo_establecimiento' => $sucursal?->codigo_establecimiento,
            // Establecimiento donde se emite: en multisucursal puede diferir del domicilio fiscal.
            'establecimiento_nombre' => $sucursal?->nombre,
            'establecimiento_direccion' => $sucursal?->direccion,
            'establecimiento_telefono' => $sucursal?->telefono,
        ]);
    }

    /** Identidad visual compartida por Inertia (`organization` / `empresa`) para todos los roles. */
    public function identidad(): ?array
    {
        $clave = 'empresa_config_'.$this->version();

        return Cache::remember($clave, now()->addMinutes(self::MINUTOS_CACHE), function (): ?array {
            $data = $this->empresa();

            return $data ? [
                'id_empresa' => $data->id_empresa,
                'nombre_comercial' => $data->nombre_comercial,
                'razon_social' => $data->razon_social,
                'logo_url' => $this->logoUrl($data->logo_path),
                'color_primario' => $data->color_primario ?? self::VALORES_VISUALES['color_primario'],
                'color_secundario' => $data->color_secundario ?? self::VALORES_VISUALES['color_secundario'],
                'texto_login_titulo' => $data->texto_login_titulo ?? self::VALORES_VISUALES['texto_login_titulo'],
                'texto_login_descripcion' => $data->texto_login_descripcion ?? self::VALORES_VISUALES['texto_login_descripcion'],
            ] : null;
        });
    }

    /** Tras guardar se invalida la identidad en caché. */
    public function olvidarIdentidad(): void
    {
        Cache::forever(self::CLAVE_VERSION, $this->version() + 1);
    }

    public function logoUrl(?string $path): ?string
    {
        return $path ? '/storage/'.ltrim($path, '/') : null;
    }

    private function version(): int
    {
        return (int) Cache::get(self::CLAVE_VERSION, 1);
    }
}
