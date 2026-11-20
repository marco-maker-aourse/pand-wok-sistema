/**
 * Reglas de pago del cobro del restaurante (POS y dividir cuenta), las mismas que valida el
 * servidor (RegistrarVentaService): sin dependencias, para poder probarlas con node --test.
 */

const centimos = (valor) => Math.round(Number(valor || 0) * 100);
const fmt = (c) => (c / 100).toFixed(2);
const soles = (c) => `S/ ${fmt(c)}`;

/** Monto efectivo de cada pago: un único pago sin monto cubre todo el total. */
export function montoDePago(pago, pagos, total) {
    return pago.monto === '' && pagos.length === 1 ? centimos(total) : centimos(pago.monto);
}

/** Saldo por asignar en céntimos (positivo = falta, negativo = sobra). */
export function saldoPagos(pagos, total) {
    return centimos(total) - pagos.reduce((suma, pago) => suma + montoDePago(pago, pagos, total), 0);
}

/**
 * Qué impide cobrar, en una frase; null si los pagos están completos.
 * El servidor valida lo mismo: esto solo evita que el botón quede bloqueado sin explicación.
 */
export function motivoPagos(pagos, metodos, total) {
    if (metodos.length === 0) return 'No hay métodos de pago activos: agrégalos en Configuración → Métodos de pago.';
    if (centimos(total) <= 0) return 'El total debe ser mayor que cero.';
    for (const pago of pagos) {
        const metodo = metodos.find((m) => Number(m.id_metodo) === Number(pago.id_metodo));
        if (!metodo) return 'Elige el método de pago.';
        const monto = montoDePago(pago, pagos, total);
        if (monto <= 0) return `Indica el monto pagado con ${metodo.nombre}.`;
        if (metodo.requiere_referencia && !String(pago.nro_referencia ?? '').trim()) return `Escribe el número de operación de ${metodo.nombre}.`;
        if (metodo.tipo === 'EFECTIVO' && pago.monto_recibido !== '' && centimos(pago.monto_recibido) < monto) {
            return `El efectivo recibido no alcanza: faltan ${soles(monto - centimos(pago.monto_recibido))}.`;
        }
    }
    const saldo = saldoPagos(pagos, total);
    if (saldo > 0) return `Falta asignar ${soles(saldo)} en los pagos.`;
    if (saldo < 0) return `Los pagos superan el total en ${soles(-saldo)}.`;
    return null;
}

/**
 * Pagos listos para el servidor. El efectivo sin monto recibido se toma como pago exacto
 * (igual que la caja de Ventas): el servidor exige ese dato para calcular el vuelto.
 */
export function pagosParaEnviar(pagos, metodos, total) {
    return pagos.map((pago) => {
        const metodo = metodos.find((m) => Number(m.id_metodo) === Number(pago.id_metodo));
        const monto = montoDePago(pago, pagos, total);
        const efectivo = metodo?.tipo === 'EFECTIVO';
        return {
            id_metodo: Number(pago.id_metodo),
            monto: fmt(monto),
            monto_recibido: efectivo ? fmt(pago.monto_recibido === '' ? monto : centimos(pago.monto_recibido)) : null,
            nro_referencia: String(pago.nro_referencia ?? '').trim() || null,
        };
    });
}

/** Vuelto en céntimos del pago en efectivo (0 si no corresponde). */
export function vueltoDe(pago, pagos, metodos, total) {
    const metodo = metodos.find((m) => Number(m.id_metodo) === Number(pago.id_metodo));
    if (metodo?.tipo !== 'EFECTIVO' || pago.monto_recibido === '') return 0;
    return Math.max(0, centimos(pago.monto_recibido) - montoDePago(pago, pagos, total));
}
