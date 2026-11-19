<?php

namespace App\Services\Comprobantes;

use App\Models\DatosGen;
use App\Models\SerieSucursal;
use DomainException;

/**
 * Series de comprobantes de cada sucursal.
 *
 * - La sucursal con registro propio en datos_gen (la principal) guarda ahí sus series.
 * - Las demás las guardan en operaciones.series_sucursal.
 * Una serie solo puede pertenecer a una sucursal: SUNAT asigna la numeración por serie.
 */
class SeriesSucursalService
{
    public const CAMPOS = ['serie_boleta', 'serie_factura', 'serie_guia'];

    /** @return array{serie_boleta:?string, serie_factura:?string, serie_guia:?string} */
    public function deSucursal(int $sucursal): array
    {
        $registro = DatosGen::query()->where('id_sucursal', $sucursal)->first(self::CAMPOS)
            ?? SerieSucursal::query()->find($sucursal, self::CAMPOS);

        return array_merge(array_fill_keys(self::CAMPOS, null), $registro?->only(self::CAMPOS) ?? []);
    }

    /** Guarda las series de una sucursal sin registro propio en datos_gen. */
    public function guardar(int $sucursal, array $series): SerieSucursal
    {
        $series = $this->normalizar($series);
        $this->validarUnicas($sucursal, $series);

        $registro = SerieSucursal::query()->firstOrNew(['id_sucursal' => $sucursal]);
        $registro->fill($series)->save();

        return $registro;
    }

    /** Una serie no puede repetirse entre sucursales (ni entre tipos de comprobante). */
    public function validarUnicas(int $sucursal, array $series): void
    {
        $series = array_filter($this->normalizar($series));
        foreach ($series as $campo => $serie) {
            $enEmpresa = DatosGen::query()->where('id_sucursal', '!=', $sucursal)
                ->where(fn ($q) => $q->whereIn('serie_boleta', [$serie])->orWhereIn('serie_factura', [$serie])->orWhereIn('serie_guia', [$serie]))
                ->exists();
            $enSucursales = SerieSucursal::query()->where('id_sucursal', '!=', $sucursal)
                ->where(fn ($q) => $q->where('serie_boleta', $serie)->orWhere('serie_factura', $serie)->orWhere('serie_guia', $serie))
                ->exists();

            if ($enEmpresa || $enSucursales) {
                throw new DomainException("La serie {$serie} ya la usa otra sucursal. Cada sucursal necesita su propia serie (p. ej. B002, F002).");
            }
        }
    }

    private function normalizar(array $series): array
    {
        return collect(self::CAMPOS)->mapWithKeys(fn (string $campo) => [
            $campo => filled($series[$campo] ?? null) ? mb_strtoupper(trim((string) $series[$campo])) : null,
        ])->all();
    }
}
