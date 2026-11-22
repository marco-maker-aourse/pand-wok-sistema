import { Minus, Plus, Trash2 } from 'lucide-react';
import { IconButton } from '@/Components/ui';
import { formatoMoneda } from './formato';
import { SelectorPresentacion } from './SelectoresPos';

/**
 * Ítem del ticket: presentación, cantidad, subtotal y margen de referencia.
 * Los cálculos de costo y margen son solo informativos y no alteran el total.
 */
export default function LineaCarrito({ item, unidad, onQuitar, onPresentacion, onPaso, onCantidad, onCantidadBlur }) {
    const costoEmpaque = Number(item.precio_costo_kardex || 0) * Number(item.factor_conversion || 1);
    const precioVenta = Number(item.precio || 0);
    const ganancia = precioVenta - costoEmpaque;
    const margen = costoEmpaque > 0 ? (ganancia / costoEmpaque) * 100 : 0;
    const esGanancia = ganancia >= 0;

    return (
        <li className="animate-entrada space-y-2 rounded-lg border border-slate-200 bg-white p-3">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-slate-900">{item.nombre}</p>
                    <p className="text-xs text-slate-500">Unidad base: {unidad}</p>
                </div>
                <IconButton icon={Trash2} tone="danger" size="sm" label={`Quitar ${item.nombre}`} onClick={onQuitar} />
            </div>

            <SelectorPresentacion presentaciones={item.presentaciones} seleccionadoId={item.id_prod_pres} onCambiar={onPresentacion} unidadNombre={unidad} />

            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center overflow-hidden rounded-lg border border-slate-300">
                    <button type="button" onClick={() => onPaso(-1)} aria-label="Disminuir cantidad" className="grid h-8 w-8 place-items-center text-slate-600 hover:bg-slate-100">
                        <Minus className="h-3.5 w-3.5" aria-hidden="true" />
                    </button>
                    <input
                        type="text"
                        inputMode="decimal"
                        aria-label={`Cantidad de ${item.nombre}`}
                        value={item.cantidad}
                        onWheel={(e) => e.target.blur()}
                        onChange={(e) => onCantidad(e.target.value)}
                        onBlur={onCantidadBlur}
                        className="!min-h-0 h-8 w-16 !rounded-none !border-0 !border-x !border-slate-200 text-center font-mono text-sm font-medium !shadow-none"
                    />
                    <button type="button" onClick={() => onPaso(1)} aria-label="Aumentar cantidad" className="grid h-8 w-8 place-items-center text-slate-600 hover:bg-slate-100">
                        <Plus className="h-3.5 w-3.5" aria-hidden="true" />
                    </button>
                </div>
                <p className="text-right">
                    <span className="block text-xs text-slate-500">Subtotal</span>
                    <span className="text-sm font-semibold text-slate-900 tabular-nums">{formatoMoneda.format(item.subtotal)}</span>
                </p>
            </div>

            <div className="flex items-center justify-between gap-2 border-t border-slate-100 pt-2 text-xs text-slate-500 tabular-nums">
                <span>Costo ref. {costoEmpaque > 0 ? formatoMoneda.format(costoEmpaque) : 'S/ 0.00'}</span>
                <span>
                    Margen{' '}
                    <strong className={esGanancia ? 'font-medium text-emerald-700' : 'font-medium text-rose-600'}>
                        {esGanancia ? `+${formatoMoneda.format(ganancia)}` : `-${formatoMoneda.format(Math.abs(ganancia))}`}
                    </strong>{' '}
                    ({esGanancia ? `+${margen.toFixed(1)}%` : `${margen.toFixed(1)}%`})
                </span>
            </div>
        </li>
    );
}
