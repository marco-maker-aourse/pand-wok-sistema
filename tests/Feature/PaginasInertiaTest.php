<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * En Windows el sistema de archivos no distingue mayúsculas y estos errores pasan
 * desapercibidos; en Linux (GitHub Actions y el hosting) la página no se encuentra.
 * Aquí se compara contra el listado real de cada carpeta, que sí respeta mayúsculas.
 */
class PaginasInertiaTest extends TestCase
{
    public function test_la_carpeta_de_paginas_configurada_existe_con_ese_nombre_exacto(): void
    {
        foreach (config('inertia.pages.paths') as $ruta) {
            $this->assertTrue(
                $this->existeExacto(base_path(), str_replace(base_path().DIRECTORY_SEPARATOR, '', $ruta)),
                "La carpeta de páginas {$ruta} no existe con esas mayúsculas."
            );
        }
    }

    public function test_cada_componente_renderizado_existe_con_ese_nombre_exacto(): void
    {
        $componentes = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $archivo) {
            if ($archivo->getExtension() === 'php') {
                preg_match_all("/Inertia::render\\(\\s*'([^']+)'/", file_get_contents($archivo->getPathname()), $coincidencias);
                $componentes = [...$componentes, ...$coincidencias[1]];
            }
        }
        $componentes = array_unique($componentes);
        $this->assertNotEmpty($componentes);

        // Ruta de páginas relativa al proyecto: así también se validan sus mayúsculas.
        $raiz = str_replace(base_path().DIRECTORY_SEPARATOR, '', config('inertia.pages.paths')[0]);
        $extensiones = config('inertia.pages.extensions');
        $faltantes = array_values(array_filter($componentes, function (string $componente) use ($raiz, $extensiones): bool {
            foreach ($extensiones as $extension) {
                if ($this->existeExacto(base_path(), "{$raiz}/{$componente}.{$extension}")) {
                    return false;
                }
            }

            return true;
        }));

        $this->assertSame([], $faltantes, 'Componentes Inertia sin archivo con ese nombre exacto.');
    }

    /** Recorre la ruta por segmentos comparando con scandir(), que conserva mayúsculas. */
    private function existeExacto(string $base, string $relativa): bool
    {
        $actual = $base;
        foreach (preg_split('#[\\\\/]#', $relativa) as $segmento) {
            if ($segmento === '' || ! is_dir($actual) || ! in_array($segmento, scandir($actual), true)) {
                return false;
            }
            $actual .= DIRECTORY_SEPARATOR.$segmento;
        }

        return true;
    }
}
