<?php

namespace App\Services\Configuracion;

use App\Models\DatosGen;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Cache;

/**
 * Punto único de lectura de los Datos Generales (operaciones.datos_gen).
 *
 * El esquema guarda un registro por sucursal y exige un RUC único, de modo que
 * las sucursales de una misma empresa no pueden repetir el registro:
 *
 * - Una sucursal con registro propio es su propia empresa (otro RUC) y usa sus datos.
 * - Una sucursal sin registro usa los datos de la empresa principal: nombre,
 *   razón social, RUC, logo, colores y textos son globales para todas sus
 *   sucursales y para todos los roles. Su dirección y código de establecimiento
 *   son los de la propia sucursal (inventario.sucursales).
 *
 * La empresa principal es la del establecimiento 0000 (domicilio fiscal) o, si
 * no está identificado, el primer registro creado.
 */
class DatosGeneralesService
{
    private const MINUTOS_CACHE = 10;

    private const CLAVE_VERSION = 'empresa_config_version';

    /** Campos propios de cada registro de empresa que se muestran en pantalla. */
    public const CAMPOS_EDITABLES = [
        'id_datos_gen', 'id_sucursal', 'ruc_empresa', 'razon_social', 'nombre_comercial', 'direccion_fiscal',
        'igv_porcentaje', 'uit_valor', 'serie_boleta', 'serie_factura', 'serie_guia', 'pie_ticket', 'logo_path',
        'color_primario', 'color_secundario', 'texto_login_titulo', 'texto_login_descripcion',
    ];

    /** Numeración de comprobantes: solo se edita en el registro propio de la sucursal. */
    public const CAMPOS_NUMERACION = ['serie_boleta', 'serie_factura', 'serie_guia'];

    private const VALORES_VISUALES = [
        'color_primario'          => '#0e7490',
        'color_secundario'        => '#0f172a',
        'texto_login_titulo'      => 'Bienvenido al Sistema',
        'texto_login_descripcion' => 'Plataforma comercial y punto de venta centralizado.',
    ];

    /** Registro de la empresa principal (compartido por las sucursales sin registro propio). */
    public function principal(): ?DatosGen
    {
        return DatosGen::query()
            ->orderByRaw(
                'CASE WHEN id_sucursal IN (SELECT id_sucursal FROM inventario.sucursales WHERE codigo_establecimiento = ?) THEN 0 ELSE 1 END',
                ['0000'],
            )
            ->orderBy('id_datos_gen')
            ->first();
    }

    /** Registro que define la empresa de la sucursal: el propio o, si no tiene, el principal. */
    public function empresaDe(?int $branchId): ?DatosGen
    {
        $propio = $branchId ? DatosGen::query()->where('id_sucursal', $branchId)->first() : null;

        return $propio ?? $this->principal();
    }

    public function tieneRegistroPropio(int $branchId): bool
    {
        return DatosGen::query()->where('id_sucursal', $branchId)->exists();
    }

    /**
     * Registro que se muestra y edita en Datos Generales. Si todavía no existe
     * ninguno se devuelve uno nuevo sin persistir: una consulta no crea datos fiscales.
     */
    public function paraEdicion(int $branchId): DatosGen
    {
        return $this->empresaDe($branchId)
            ?? new DatosGen(['id_sucursal' => $branchId, ...self::VALORES_VISUALES]);
    }

    /** Datos del emisor que se imprimen en tickets y comprobantes de la sucursal. */
    public function emisor(int $branchId): ?DatosGen
    {
        $empresa = $this->empresaDe($branchId);

        if (! $empresa) {
            return null;
        }

        $sucursal = Sucursal::query()->whereKey($branchId)->first(['nombre', 'direccion', 'telefono', 'codigo_establecimiento']);

        return (new DatosGen)->forceFill([
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

    /**
     * Identidad visual compartida por Inertia (`organization` / `empresa`) para
     * todos los roles. Sin sesión (login) se muestra la empresa principal.
     */
    public function identidad(?int $branchId): ?array
    {
        $clave = 'empresa_config_'.$this->version().'_'.($branchId ?? 'principal');

        return Cache::remember($clave, now()->addMinutes(self::MINUTOS_CACHE), function () use ($branchId): ?array {
            $data = $this->empresaDe($branchId);

            return $data ? [
                'id_datos_gen' => $data->id_datos_gen,
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

    /**
     * Tras guardar se invalida la identidad de todas las sucursales a la vez:
     * los datos de la empresa principal los comparten varias sucursales.
     */
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
