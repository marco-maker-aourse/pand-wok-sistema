/**
 * Reglas del tablero de cocina, sin React: se prueban con `node --test tests/js`.
 */

/** Minutos de espera desde que la comanda llegó a cocina. */
export const minutosDesde = (iso, ahora) => (iso ? Math.max(0, Math.floor((ahora - Date.parse(iso)) / 60000)) : 0);

export const reloj = (minutos) => (minutos < 60 ? `${minutos} min` : `${Math.floor(minutos / 60)} h ${String(minutos % 60).padStart(2, '0')}`);

/** Hasta 8 min a tiempo, hasta 15 min en espera, después atrasada. */
export const URGENCIA = { A_TIEMPO: 'a_tiempo', EN_ESPERA: 'en_espera', ATRASADA: 'atrasada' };

export function urgencia(minutos) {
    if (minutos >= 15) return URGENCIA.ATRASADA;
    if (minutos >= 8) return URGENCIA.EN_ESPERA;
    return URGENCIA.A_TIEMPO;
}

/**
 * Comandas por hacer que no estaban en el tablero anterior del mismo filtro (sucursal y
 * estación). La primera carga y los cambios de filtro no cuentan como pedidos nuevos.
 *
 * @returns {{ nuevas: number[], vistos: { filtro: string, ids: Set<number> } }}
 */
export function comandasNuevas(anteriores, comandas, filtro) {
    const ids = new Set(comandas.map((c) => c.id_comanda));
    const comparables = anteriores?.filtro === filtro;
    const nuevas = comparables
        ? comandas.filter((c) => c.estado === 'PENDIENTE' && !anteriores.ids.has(c.id_comanda)).map((c) => c.id_comanda)
        : [];

    return { nuevas, vistos: { filtro, ids } };
}
