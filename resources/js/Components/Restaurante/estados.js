/** Etiquetas y tonos de estado. Una sola fuente para salón, cuentas y cocina. */

export const ESTADO_MESA = {
    DISPONIBLE: { etiqueta: 'Libre', tono: 'success', tarjeta: 'bg-white ring-emerald-200 hover:ring-emerald-400', punto: 'bg-emerald-500' },
    OCUPADA: { etiqueta: 'Ocupada', tono: 'brand', tarjeta: 'bg-brand-soft ring-brand-border hover:ring-brand', punto: 'bg-brand' },
    RESERVADA: { etiqueta: 'Reservada', tono: 'warning', tarjeta: 'bg-amber-50 ring-amber-200 hover:ring-amber-400', punto: 'bg-amber-500' },
    FUERA_SERVICIO: { etiqueta: 'Fuera de servicio', tono: 'neutral', tarjeta: 'bg-slate-50 ring-slate-200 opacity-70', punto: 'bg-slate-400' },
};

export const ESTADO_ITEM = {
    PENDIENTE: { etiqueta: 'Por enviar', tono: 'warning' },
    ENVIADO: { etiqueta: 'En cocina', tono: 'info' },
    EN_PREPARACION: { etiqueta: 'Preparando', tono: 'info' },
    LISTO: { etiqueta: 'Listo', tono: 'success' },
    ENTREGADO: { etiqueta: 'Entregado', tono: 'neutral' },
    ANULADO: { etiqueta: 'Anulado', tono: 'danger' },
};

export const ESTADO_COMANDA = {
    PENDIENTE: { etiqueta: 'Pendiente', tono: 'warning' },
    EN_PREPARACION: { etiqueta: 'En preparación', tono: 'info' },
    LISTA: { etiqueta: 'Lista', tono: 'success' },
    ENTREGADA: { etiqueta: 'Entregada', tono: 'neutral' },
    ANULADA: { etiqueta: 'Anulada', tono: 'danger' },
};

export const ESTADO_PEDIDO = {
    ABIERTO: { etiqueta: 'Abierta', tono: 'success' },
    CERRADO: { etiqueta: 'Cerrada', tono: 'neutral' },
    ANULADO: { etiqueta: 'Anulada', tono: 'danger' },
    UNIDO: { etiqueta: 'Unida a otra cuenta', tono: 'neutral' },
};

export const TIPO_PRODUCTO = {
    PREPARADO: { etiqueta: 'Preparado', descripcion: 'Pasa por cocina' },
    DIRECTO: { etiqueta: 'Venta directa', descripcion: 'Se entrega tal cual (bebidas, envasados)' },
    INSUMO: { etiqueta: 'Insumo', descripcion: 'Solo inventario; no aparece en la carta' },
};

/** Texto del historial de una cuenta. */
export function describirEvento(evento) {
    const d = evento.datos ?? {};
    switch (evento.tipo) {
        case 'PEDIDO_ABIERTO': return d.comensales ? `Abrió la cuenta (${d.comensales} comensales)` : 'Abrió la cuenta';
        case 'ITEM_AGREGADO': return `Agregó ${d.cantidad} × ${d.producto}${d.opciones?.length ? ` (${d.opciones.join(', ')})` : ''}`;
        case 'ITEM_MODIFICADO': return `Modificó ${d.producto}: ${d.antes?.cantidad} → ${d.despues?.cantidad}`;
        case 'ITEM_ANULADO': return `Quitó ${d.producto}${d.motivo ? ` — ${d.motivo}` : ''}`;
        case 'PEDIDO_ANULADO': return `Anuló la cuenta — ${d.motivo}`;
        case 'MESA_TRANSFERIDA': return `Cambió de mesa ${d.mesa_origen} → ${d.mesa_destino}`;
        case 'PEDIDO_UNIDO': return `Unió esta cuenta a la #${d.pedido_destino}`;
        case 'CUENTA_ABSORBIDA': return `Recibió la cuenta #${d.pedido_origen} (${d.items} ítems)`;
        case 'ITEMS_MOVIDOS': return `Movió a la cuenta #${d.pedido_destino}: ${(d.productos ?? []).join(', ')}`;
        case 'ITEMS_RECIBIDOS': return `Recibió de la cuenta #${d.pedido_origen}: ${(d.productos ?? []).join(', ')}`;
        case 'COBRO_REGISTRADO': return `Registró el cobro ${d.comprobante}`;
        case 'PEDIDO_CERRADO': return 'Cerró la cuenta';
        case 'COMANDA_ENVIADA': return d.comandas?.length ? `Envió a cocina ${d.comandas.map((numero) => `#${numero}`).join(', ')}` : 'Registró los ítems de entrega directa';
        default: return evento.tipo;
    }
}
