/**
 * Reglas de la página de reportes del restaurante, sin React: se prueban con `node --test tests/js`.
 */

/** Variación porcentual contra el período anterior; null si no hay base para comparar. */
export function variacion(actual, anterior) {
    const base = Number(anterior);
    if (!base || actual == null) return null;
    return Math.round(((Number(actual) - base) / base) * 1000) / 10;
}

/**
 * Horas del día con ventas, sin huecos entre la primera y la última (una hora sin ventas en
 * medio del servicio también es información).
 */
export function horasDelServicio(horas) {
    if (!horas.length) return [];
    const porHora = new Map(horas.map((fila) => [Number(fila.hora), fila]));
    const valores = [...porHora.keys()];
    const resultado = [];
    for (let h = Math.min(...valores); h <= Math.max(...valores); h += 1) {
        resultado.push(porHora.get(h) ?? { hora: h, cobros: 0, total: 0 });
    }
    return resultado;
}

const DIA_MS = 86400000;
const formatoLima = new Intl.DateTimeFormat('es-PE', { timeZone: 'America/Lima', year: 'numeric', month: '2-digit', day: '2-digit' });
const fechaLima = (fecha) => {
    const partes = Object.fromEntries(formatoLima.formatToParts(fecha).map(({ type, value }) => [type, value]));
    return `${partes.year}-${partes.month}-${partes.day}`;
};

export function diasDelPeriodo(dias, desde, hasta) {
    const porDia = new Map(dias.map((fila) => [fila.dia, fila]));
    const resultado = [];
    const fin = Date.parse(hasta);
    for (let fecha = Date.parse(desde); fecha <= fin; fecha += DIA_MS) {
        const dia = new Date(fecha).toISOString().slice(0, 10);
        resultado.push(porDia.get(dia) ?? { dia, cobros: 0, total: 0 });
    }

    return resultado;
}

/** Rango de fechas (YYYY-MM-DD, hora de Lima) de cada atajo de período. */
export function rangoDePeriodo(periodo, ahora = new Date()) {
    const hoy = fechaLima(ahora);
    const restar = (dias) => fechaLima(new Date(Date.parse(`${hoy}T12:00:00-05:00`) - dias * DIA_MS));
    switch (periodo) {
        case 'hoy': return { desde: hoy, hasta: hoy };
        case 'ayer': return { desde: restar(1), hasta: restar(1) };
        case '7dias': return { desde: restar(6), hasta: hoy };
        case '30dias': return { desde: restar(29), hasta: hoy };
        case 'mes': return { desde: `${hoy.slice(0, 8)}01`, hasta: hoy };
        default: return null;
    }
}

/** Atajo que corresponde a un rango, o null si es un rango a medida. */
export function periodoDeRango(desde, hasta, ahora = new Date()) {
    return ['hoy', 'ayer', '7dias', '30dias', 'mes'].find((periodo) => {
        const rango = rangoDePeriodo(periodo, ahora);
        return rango.desde === desde && rango.hasta === hasta;
    }) ?? null;
}

/** Participación de cada fila en el total (0-100), para las barras horizontales. */
export function conParticipacion(filas, campo = 'total') {
    const total = filas.reduce((suma, fila) => suma + (Number(fila[campo]) || 0), 0);
    return filas.map((fila) => ({ ...fila, participacion: total ? Math.round(((Number(fila[campo]) || 0) / total) * 1000) / 10 : 0 }));
}
