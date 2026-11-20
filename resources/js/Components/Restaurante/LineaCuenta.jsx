import { Minus, Plus, Trash2 } from 'lucide-react';
import { Badge, IconButton, cx } from '@/Components/ui';
import { ESTADO_ITEM } from './estados';
import { cantidad as fmtCantidad, soles } from './formato';

/**
 * Línea de la cuenta. Los ítems "por enviar" se ajustan con +/−; los que ya salieron a
 * cocina solo se pueden anular (con permiso y motivo, según el servidor).
 */
export default function LineaCuenta({ item, editable, ocupado, onCantidad, onQuitar }) {
    const estado = ESTADO_ITEM[item.estado] ?? ESTADO_ITEM.PENDIENTE;
    const anulado = item.estado === 'ANULADO';
    const pendiente = item.estado === 'PENDIENTE';

    return (
        <li className={cx('px-4 py-3', anulado && 'opacity-60')}>
            <div className="flex items-start gap-3">
                <div className="min-w-0 flex-1">
                    <p className={cx('text-sm font-medium text-slate-900', anulado && 'line-through')}>
                        <span className="font-mono tabular-nums">{fmtCantidad(item.cantidad)}</span> × {item.nombre_producto}
                    </p>
                    {item.modificadores?.length > 0 && (
                        <p className="text-xs text-slate-600">{item.modificadores.map((m) => m.etiqueta ?? m.nombre_opcion).join(' · ')}</p>
                    )}
                    {item.observacion && <p className="text-xs text-amber-700 italic">“{item.observacion}”</p>}
                    {anulado && item.motivo_anulacion && <p className="text-xs text-rose-600">Anulado: {item.motivo_anulacion}</p>}
                    <div className="mt-1 flex flex-wrap gap-1.5">
                        <Badge tone={estado.tono}>{estado.etiqueta}</Badge>
                        {item.cantidad_cobrada > 0 && <Badge tone="success">{fmtCantidad(item.cantidad_cobrada)} cobrado(s)</Badge>}
                    </div>
                </div>
                <div className="flex flex-col items-end gap-1.5">
                    <span className={cx('font-mono text-sm font-semibold tabular-nums', anulado && 'line-through')}>{soles(item.subtotal)}</span>
                    {editable && !anulado && item.cantidad_cobrada === 0 && (
                        <div className="flex items-center gap-1">
                            {pendiente && (
                                <>
                                    <IconButton icon={Minus} label="Uno menos" className="h-10 w-10 ring-1 ring-slate-200" disabled={ocupado || item.cantidad <= 1} onClick={() => onCantidad(item, item.cantidad - 1)} />
                                    <IconButton icon={Plus} label="Uno más" className="h-10 w-10 ring-1 ring-slate-200" disabled={ocupado} onClick={() => onCantidad(item, item.cantidad + 1)} />
                                </>
                            )}
                            <IconButton icon={Trash2} label={pendiente ? 'Quitar' : 'Anular'} className="h-10 w-10 ring-1 ring-slate-200" tone="danger" disabled={ocupado} onClick={() => onQuitar(item)} />
                        </div>
                    )}
                </div>
            </div>
        </li>
    );
}
