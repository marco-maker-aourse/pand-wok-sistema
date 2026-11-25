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

/**
 * Toca la campana de comanda nueva ("ding-dong"), más insistente con varias comandas (tope 3).
 * Si el navegador suspendió el audio (pantalla apagada, pestaña atrás), primero lo reanuda.
 * Devuelve false si no pudo sonar: hace falta que alguien toque la pantalla.
 */
export async function sonarCampana(contexto, comandas = 1) {
    if (!contexto) return false;
    if (contexto.state !== 'running') {
        try { await contexto.resume(); } catch { /* el navegador exige un toque */ }
    }
    if (contexto.state !== 'running') return false;

    const inicio = contexto.currentTime;
    for (let r = 0; r < Math.min(Math.max(comandas, 1), 3); r++) {
        [[988, 0], [740, 0.22]].forEach(([frecuencia, desfase]) => {
            const t = inicio + r * 0.75 + desfase;
            const tono = contexto.createOscillator();
            const volumen = contexto.createGain();
            tono.type = 'triangle';
            tono.frequency.value = frecuencia;
            volumen.gain.setValueAtTime(0.0001, t);
            // Fuerte para una cocina ruidosa, sin saturar cuando las dos notas se solapan.
            volumen.gain.exponentialRampToValueAtTime(0.6, t + 0.02);
            volumen.gain.exponentialRampToValueAtTime(0.0001, t + 0.6);
            tono.connect(volumen).connect(contexto.destination);
            tono.start(t);
            tono.stop(t + 0.62);
        });
    }
    return true;
}
