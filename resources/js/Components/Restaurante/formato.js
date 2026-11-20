/** Utilidades de presentación del restaurante. Los importes ya vienen calculados del servidor. */

const moneda = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const cantidadFmt = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 3 });

export const soles = (valor) => moneda.format(Number(valor) || 0);
export const cantidad = (valor) => cantidadFmt.format(Number(valor) || 0);

/**
 * Clave de idempotencia para una operación. Usa crypto.getRandomValues, disponible también
 * en http de la red local (tablets), donde crypto.randomUUID no existe.
 */
export function nuevaClave() {
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
}

/** "hace 12 min" / "hace 1 h 05 min" a partir de un ISO-8601. */
export function tiempoDesde(iso, ahora = Date.now()) {
    if (!iso) return '';
    const minutos = Math.max(0, Math.floor((ahora - new Date(iso).getTime()) / 60000));
    if (minutos < 1) return 'recién';
    if (minutos < 60) return `${minutos} min`;
    const horas = Math.floor(minutos / 60);
    return `${horas} h ${String(minutos % 60).padStart(2, '0')} min`;
}

export const hora = (iso) => (iso ? new Date(iso).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }) : '');

/** Primer mensaje de error de una respuesta de validación de Inertia. */
export const primerError = (errores) => Object.values(errores ?? {}).flat()[0] ?? 'No se pudo completar la operación.';

export const TIPOS_PEDIDO = {
    MESA: 'Mesa',
    PARA_LLEVAR: 'Para llevar',
    DELIVERY: 'Delivery',
};

/** Título corto de una cuenta: "Mesa M3", "Para llevar · Ana", "Delivery #12". */
export function tituloCuenta(cuenta) {
    if (cuenta.tipo === 'MESA') return `Mesa ${cuenta.mesa?.codigo ?? cuenta.mesa ?? ''}`.trim();
    const base = TIPOS_PEDIDO[cuenta.tipo] ?? cuenta.tipo;
    return cuenta.nombre_contacto ? `${base} · ${cuenta.nombre_contacto}` : `${base} #${cuenta.numero}`;
}

/**
 * Cambia la cantidad de una línea del borrador. Si cada opción acompaña a todas las unidades
 * (1 menú con sopa wantán), las opciones suben con ella; si se repartieron entre unidades
 * (1 sopa wantán y 1 de pollo) se devuelve la línea sin cambios: hay que reabrir el plato.
 */
export function cambiarCantidadLinea(linea, delta) {
    const nueva = linea.cantidad + delta;
    const selecciones = linea.selecciones ?? [];
    if (selecciones.some((s) => s.cantidad !== linea.cantidad)) return { linea, repartida: true };
    return { linea: { ...linea, cantidad: nueva, selecciones: selecciones.map((s) => ({ ...s, cantidad: nueva })) }, repartida: false };
}
