import { Building, Coins, CreditCard, Plus, Smartphone, Trash2 } from 'lucide-react';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

function iconoMetodo(nombre) {
    const normalizado = (nombre || '').toLowerCase();
    if (normalizado.includes('yape') || normalizado.includes('plin')) return <Smartphone className="h-3.5 w-3.5" />;
    if (normalizado.includes('tarjeta')) return <CreditCard className="h-3.5 w-3.5" />;
    if (normalizado.includes('transferencia')) return <Building className="h-3.5 w-3.5" />;

    return <Coins className="h-3.5 w-3.5" />;
}

/** Panel táctil de cobro. La venta sigue validando montos y referencias en el servidor. */
export default function PanelPagos({ pagos, metodosPago, totalVenta, saldoPendiente, metodoDePago, onAgregar, onSeleccionarMetodo, onActualizar, onQuitar }) {
    const textoSaldo = Math.abs(saldoPendiente) <= 0.01
        ? 'Cobro distribuido correctamente.'
        : saldoPendiente > 0 ? `Pendiente: ${currency.format(saldoPendiente)}` : `Exceso: ${currency.format(Math.abs(saldoPendiente))}`;
    const tonoSaldo = Math.abs(saldoPendiente) <= 0.01 ? 'text-emerald-600' : saldoPendiente > 0 ? 'text-amber-600' : 'text-rose-600';

    return (
        <div>
            <div className="mb-1.5 flex items-center justify-between gap-2">
                <label className="block text-xs font-semibold text-slate-600">Forma de pago</label>
                <button type="button" onClick={onAgregar} disabled={pagos.length >= metodosPago.length} className="inline-flex items-center gap-1 text-xs font-semibold text-cyan-700 transition hover:text-cyan-900 disabled:cursor-not-allowed disabled:text-slate-300">
                    <Plus className="h-3.5 w-3.5" /> Cobro mixto
                </button>
            </div>
            <div className="space-y-2">
                {pagos.map((pago, indice) => {
                    const metodo = metodoDePago(pago);
                    return (
                        <div key={`${pago.id_metodo}-${indice}`} className="rounded-lg border border-slate-200 bg-white p-2">
                            <div className="mb-1.5 grid grid-cols-3 gap-1">
                                {metodosPago.map((opcion) => {
                                    const activo = Number(pago.id_metodo) === Number(opcion.id_metodo);
                                    const usadoEnOtraFila = pagos.some((otro, actual) => actual !== indice && Number(otro.id_metodo) === Number(opcion.id_metodo));
                                    return <button key={opcion.id_metodo} type="button" disabled={usadoEnOtraFila} onClick={() => onSeleccionarMetodo(indice, opcion)} style={activo ? { backgroundColor: 'var(--brand-primary, #0e7490)' } : undefined} className={`h-8 rounded-lg border px-1.5 text-xs font-semibold transition ${activo ? 'border-transparent text-white' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-cyan-300'} disabled:cursor-not-allowed disabled:opacity-35`}><span className="inline-flex max-w-full items-center gap-1 truncate">{iconoMetodo(opcion.nombre)}<span className="truncate">{opcion.nombre}</span></span></button>;
                                })}
                            </div>
                            <div className="grid grid-cols-[minmax(0,1fr)_98px_auto] items-center gap-1.5">
                                <span className="truncate px-1 text-xs font-semibold text-slate-700">{metodo?.nombre || 'Selecciona un medio'}</span>
                                <input type="number" min="0" step="0.01" value={pago.monto} onWheel={(event) => event.target.blur()} onChange={(event) => onActualizar(indice, 'monto', event.target.value)} placeholder={pagos.length === 1 ? currency.format(totalVenta) : 'S/ 0.00'} className="h-8 min-w-0 rounded-lg border border-slate-200 bg-white px-2 text-right font-mono text-xs font-bold text-slate-800 focus:border-cyan-600" aria-label={`Monto de ${metodo?.nombre || 'pago'}`} />
                                {pagos.length > 1 && <button type="button" onClick={() => onQuitar(indice)} className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600" aria-label="Quitar medio de pago"><Trash2 className="h-3.5 w-3.5" /></button>}
                            </div>
                        </div>
                    );
                })}
            </div>
            <p className={`mt-1.5 text-xs font-semibold ${tonoSaldo}`}>{textoSaldo}</p>
        </div>
    );
}
