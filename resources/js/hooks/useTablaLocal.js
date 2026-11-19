import { useEffect, useMemo, useState } from 'react';

const comparador = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

function valorOrden(registro, campo) {
    const valor = typeof campo === 'function' ? campo(registro) : registro?.[campo];
    if (valor === null || valor === undefined) return '';
    return typeof valor === 'boolean' ? Number(valor) : valor;
}

/**
 * Ordenación y paginación en el cliente para listados que el servidor ya entrega completos.
 * No cambia los datos: solo decide qué filas se muestran y en qué orden.
 *
 * - `campos`: { claveColumna: 'campo' | (registro) => valor }. Declárelo como constante
 *   de módulo para que el orden no se recalcule en cada render.
 * - Vuelve a la página 1 cuando cambian los registros (por ejemplo, al filtrar).
 */
export default function useTablaLocal(registros, { campos = {}, porPagina = 25, ordenInicial = null } = {}) {
    const [orden, setOrden] = useState(ordenInicial); // { columna, direccion: 'asc' | 'desc' }
    const [pagina, setPagina] = useState(1);

    useEffect(() => setPagina(1), [registros]);

    const ordenados = useMemo(() => {
        if (!orden || !campos[orden.columna]) return registros;
        const campo = campos[orden.columna];
        const signo = orden.direccion === 'desc' ? -1 : 1;
        return [...registros].sort((a, b) => {
            const va = valorOrden(a, campo);
            const vb = valorOrden(b, campo);
            const resultado = typeof va === 'number' && typeof vb === 'number' ? va - vb : comparador.compare(String(va), String(vb));
            return resultado * signo;
        });
    }, [registros, orden, campos]);

    const totalPaginas = Math.max(1, Math.ceil(ordenados.length / porPagina));
    const paginaActual = Math.min(pagina, totalPaginas);
    const visibles = ordenados.slice((paginaActual - 1) * porPagina, paginaActual * porPagina);

    const ordenarPor = (columna) => setOrden((actual) => {
        if (actual?.columna !== columna) return { columna, direccion: 'asc' };
        if (actual.direccion === 'asc') return { columna, direccion: 'desc' };
        return null;
    });

    return {
        visibles,
        total: ordenados.length,
        orden,
        ordenarPor,
        paginacion: {
            pagina: paginaActual,
            totalPaginas,
            desde: ordenados.length ? (paginaActual - 1) * porPagina + 1 : 0,
            hasta: Math.min(paginaActual * porPagina, ordenados.length),
            total: ordenados.length,
            irA: (n) => setPagina(Math.min(Math.max(1, n), totalPaginas)),
        },
    };
}
