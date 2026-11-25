import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Búsqueda que filtra en el servidor (`?buscar=`) con una pausa corta al escribir, para
 * listados paginados: así encuentra registros de todas las páginas, no solo de la actual.
 * `only` limita la recarga a las props del listado.
 */
export default function useBusquedaServidor(ruta, inicial = '', only = [], espera = 350) {
    const [busqueda, setBusqueda] = useState(inicial ?? '');
    const [buscando, setBuscando] = useState(false);
    const primera = useRef(true);

    useEffect(() => {
        if (primera.current) {
            primera.current = false;
            return undefined;
        }
        const temporizador = setTimeout(() => {
            const termino = busqueda.trim();
            router.get(ruta, termino ? { buscar: termino } : {}, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only,
                onStart: () => setBuscando(true),
                onFinish: () => setBuscando(false),
            });
        }, espera);
        return () => clearTimeout(temporizador);
    }, [busqueda]); // eslint-disable-line react-hooks/exhaustive-deps

    return { busqueda, setBusqueda, buscando };
}
