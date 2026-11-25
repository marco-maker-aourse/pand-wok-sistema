import { Building, Coins, CreditCard, Plus, Smartphone, Trash2 } from 'lucide-react';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

function iconoMetodo(nombre) {
    const normalizado = (nombre || '').toLowerCase();
    if (normalizado.includes('yape') || normalizado.includes('plin')) return <Smartphone className="h-3.5 w-3.5" />;
    if (normalizado.includes('tarjeta')) return <CreditCard className="h-3.5 w-3.5" />;
    if (normalizado.includes('transferencia')) return <Building className="h-3.5 w-3.5" />;

    return <Coins className="h-3.5 w-3.5" />;
}

/** Billetes y monedas con los que suele pagarse: atajos de "recibido" a partir del importe. */
function montosRapidos(importe) {
    const sugeridos = [10, 20, 50, 100, 200].filter((billete) => billete > importe);
    return [...new Set([Math.ceil(importe), ...sugeridos])].filter((valor) => valor > importe).slice(0, 3);
}

/**
 * Panel táctil de cobro. La venta sigue validando montos y referencias en el servidor.
 * Con `detalle` muestra lo que el servidor exige: efectivo recibido (con vuelto y montos
 * rápidos; vacío = pago exacto) y número de operación para Yape, transferencia, etc.
 */
export default function PanelPagos({ pagos, metodosPago, totalVenta, saldoPendiente, metodoDePago, onAgregar, onSeleccionarMetodo, onActualizar, onQuitar, detalle = false }) {
    const textoSaldo = Math.abs(saldoPendiente) <= 0.01
        ? 'Cobro distribuido correctamente.'
        : saldoPendiente > 0 ? `Pendiente: ${currency.format(saldoPendiente)}` : `Exceso: ${currency.format(Math.abs(saldoPendiente))}`;
    const tonoSaldo = Math.abs(saldoPendiente) <= 0.01 ? 'text-emerald-600' : saldoPendiente > 0 ? 'text-amber-600' : 'text-rose-600';

    return (
        <div>
            <div className="mb-1.5 flex items-center justify-between gap-2">
                <label className="block text-xs font-semibold text-slate-600">Forma de pago</label>
                <button type="button" onClick={onAgregar} disabled={pagos.length >= metodosPago.length} className="inline-flex items-center gap-1 text-xs font-semibold text-brand-ink transition hover:opacity-80 disabled:cursor-not-allowed disabled:text-slate-300">
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
                                    return <button key={opcion.id_metodo} type="button" disabled={usadoEnOtraFila} onClick={() => onSeleccionarMetodo(indice, opcion)} className={`h-8 rounded-lg border px-1.5 text-xs font-semibold transition ${activo ? 'border-transparent bg-brand text-on-brand' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-brand-border'} disabled:cursor-not-allowed disabled:opacity-35`}><span className="inline-flex max-w-full items-center gap-1 truncate">{iconoMetodo(opcion.nombre)}<span className="truncate">{opcion.nombre}</span></span></button>;
                                })}
                            </div>
                            <div className="grid grid-cols-[minmax(0,1fr)_98px_auto] items-center gap-1.5">
                                <span className="truncate px-1 text-xs font-semibold text-slate-700">{metodo?.nombre || 'Selecciona un medio'}</span>
                                <input type="number" min="0" step="0.01" value={pago.monto} onWheel={(event) => event.target.blur()} onChange={(event) => onActualizar(indice, 'monto', event.target.value)} placeholder={pagos.length === 1 ? currency.format(totalVenta) : 'S/ 0.00'} className="h-8 min-w-0 rounded-lg border border-slate-200 bg-white px-2 text-right font-mono text-xs font-bold text-slate-800 focus:border-brand" aria-label={`Monto de ${metodo?.nombre || 'pago'}`} />
                                {pagos.length > 1 && <button type="button" onClick={() => onQuitar(indice)} className="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600" aria-label="Quitar medio de pago"><Trash2 className="h-3.5 w-3.5" /></button>}
                            </div>
                            {detalle && metodo?.tipo === 'EFECTIVO' && (() => {
                                const importe = pago.monto === '' && pagos.length === 1 ? totalVenta : Number(pago.monto || 0);
                                const recibido = pago.monto_recibido === '' ? null : Number(pago.monto_recibido);
                                const vuelto = recibido !== null ? recibido - importe : 0;
                                return (
                                    <div className="mt-2 rounded-lg bg-slate-50 p-2">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <label className="text-xs font-semibold text-slate-600" htmlFor={`recibido-${indice}`}>Recibido</label>
                                            <input id={`recibido-${indice}`} type="number" min="0" step="0.10" inputMode="decimal" value={pago.monto_recibido} onWheel={(event) => event.target.blur()}
                                                onChange={(event) => onActualizar(indice, 'monto_recibido', event.target.value)} placeholder={`${currency.format(importe)} exacto`}
                                                className="h-10 w-32 rounded-lg border border-slate-200 bg-white px-2 text-right font-mono text-sm font-bold text-slate-800 focus:border-brand" />
                                            <button type="button" onClick={() => onActualizar(indice, 'monto_recibido', '')} className={`h-10 rounded-lg border px-2.5 text-xs font-semibold ${recibido === null ? 'border-brand bg-brand-soft text-brand-ink' : 'border-slate-200 bg-white text-slate-600'}`}>Exacto</button>
                                            {montosRapidos(importe).map((valor) => (
                                                <button key={valor} type="button" onClick={() => onActualizar(indice, 'monto_recibido', String(valor))} className={`h-10 rounded-lg border px-2.5 font-mono text-xs font-semibold ${recibido === valor ? 'border-brand bg-brand-soft text-brand-ink' : 'border-slate-200 bg-white text-slate-600'}`}>S/ {valor}</button>
                                            ))}
                                        </div>
                                        {recibido !== null && (
                                            <p className={`mt-1.5 text-sm font-semibold ${vuelto >= 0 ? 'text-emerald-700' : 'text-rose-600'}`}>
                                                {vuelto >= 0 ? `Vuelto: ${currency.format(vuelto)}` : `Falta: ${currency.format(-vuelto)}`}
                                            </p>
                                        )}
                                    </div>
                                );
                            })()}
                            {detalle && metodo?.requiere_referencia && (
                                <div className="mt-2">
                                    <input type="text" inputMode="numeric" value={pago.nro_referencia} maxLength={100} onChange={(event) => onActualizar(indice, 'nro_referencia', event.target.value)}
                                        placeholder={`N.º de operación de ${metodo.nombre}`} aria-label={`Número de operación de ${metodo.nombre}`}
                                        className={`h-10 w-full rounded-lg border bg-white px-2 text-sm ${String(pago.nro_referencia || '').trim() ? 'border-slate-200' : 'border-amber-400'} focus:border-brand`} />
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
            <p className={`mt-1.5 text-xs font-semibold ${tonoSaldo}`}>{textoSaldo}</p>
        </div>
    );
}
