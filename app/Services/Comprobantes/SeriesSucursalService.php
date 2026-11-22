<?php

namespace App\Services\Comprobantes;

use App\Models\SerieSucursal;
use DomainException;

/**
 * Series de comprobantes de cada sucursal, guardadas en configuracion.series_sucursal.
 * Una serie solo puede pertenecer a una sucursal: SUNAT asigna la numeración por serie.
 */
class SeriesSucursalService
{
    public const CAMPOS = ['serie_boleta', 'serie_factura', 'serie_guia'];

    /** @return array{serie_boleta:?string, serie_factura:?string, serie_guia:?string} */
    public function deSucursal(int $sucursal): array
    {
        $registro = SerieSucursal::query()->find($sucursal, self::CAMPOS);

        return array_merge(array_fill_keys(self::CAMPOS, null), $registro?->only(self::CAMPOS) ?? []);
    }

    /** Guarda las series de la sucursal; nunca reinicia sus correlativos. */
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
        foreach (array_filter($this->normalizar($series)) as $serie) {
            $enOtraSucursal = SerieSucursal::query()->where('id_sucursal', '!=', $sucursal)
                ->where(fn ($q) => $q->where('serie_boleta', $serie)->orWhere('serie_factura', $serie)->orWhere('serie_guia', $serie))
                ->exists();

            if ($enOtraSucursal) {
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
