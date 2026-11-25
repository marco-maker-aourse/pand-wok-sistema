import { useState } from 'react';
import { ChevronDown, Trash2 } from 'lucide-react';
import { Field, IconButton, Input, Select, cx } from '@/Components/ui';
import { esInsumo } from './useIngresoCompra';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const MARGENES = [15, 20, 25, 30];
const EMPAQUES = ['caja', 'cajas', 'paquete', 'paquetes', 'saco', 'sacos', 'docena', 'docenas', 'display', 'displays'];

export const nombreUnidad = (item) => item?.unidad_base?.nombre || item?.unidadBase?.nombre || 'unidad';
/** Si la unidad base es un empaque, el contenido se expresa en "unidades". */
export const unidadContenido = (item) => (EMPAQUES.includes(nombreUnidad(item).toLowerCase().trim()) ? 'unidades' : nombreUnidad(item));

export function cantidadBase(cantidad, unidad) {
    const total = Number(cantidad || 0);
    const texto = Number.isInteger(total) ? total : total.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
    return `${texto} ${unidad}${Math.abs(total) === 1 ? '' : 's'}`;
}

export const etiquetaPresentacion = (nombre, factor, unidad) => `${nombre || 'Presentación'} · ${cantidadBase(Number(factor) || 1, unidad)}`;

/** Línea de la compra: lo que entra al inventario y su costo. */
export default function LineaIngreso({ item, onPresentacion, onCampo, onCantidadLista, onQuitar }) {
    const insumo = esInsumo(item);
    const [conPrecio, setConPrecio] = useState(!insumo);
    const costo = Number(item.precio_costo || 0);
    const venta = Number(item.precio_venta || 0);
    const factor = Math.max(Number(item.factor_conversion) || 1, 1);
    const cantidad = Number(item.cantidad) || 0;
    const margen = costo > 0 && venta > 0 ? ((venta - costo) / costo) * 100 : null;
    const presentacion = item.presentaciones.find((p) => String(p.id_prod_pres) === String(item.id_prod_pres));

    return (
        <li className="space-y-3 rounded-xl bg-white p-3.5 ring-1 ring-slate-200">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-slate-900">{item.nombre}</p>
                    <p className="text-xs text-slate-500">
                        Entran <span className="font-mono font-medium text-slate-800 tabular-nums">{cantidadBase(cantidad * factor, nombreUnidad(item))}</span>
                        {costo > 0 && <> · {currency.format(costo / factor)} c/u</>}
                    </p>
                </div>
                <IconButton icon={Trash2} tone="danger" label={`Quitar ${item.nombre}`} onClick={() => onQuitar(item.uid)} />
            </div>

            <Field label="Se compra como">
                <Select value={item.id_prod_pres || ''} onChange={(e) => onPresentacion(item.uid, e.target.value)}>
                    {item.presentaciones.map((p) => (
                        <option key={p.id_prod_pres} value={p.id_prod_pres}>{etiquetaPresentacion(p.presentacion?.nombre || p.nombre, p.factor_conversion, unidadContenido(item))}</option>
                    ))}
                </Select>
            </Field>

            <div className="grid grid-cols-2 gap-2">
                <Field label={`Cantidad (${presentacion?.presentacion?.nombre || 'empaques'})`}>
                    <Input inputMode="decimal" className="text-center font-mono" value={item.cantidad} placeholder="1"
                        onChange={(e) => onCampo(item.uid, 'cantidad', e.target.value)} onBlur={() => onCantidadLista(item.uid)} />
                </Field>
                <Field label="Costo por empaque (S/)">
                    <Input type="number" inputMode="decimal" step="0.01" min="0" className="text-right font-mono" value={item.precio_costo} placeholder="0.00"
                        onWheel={(e) => e.target.blur()} onChange={(e) => onCampo(item.uid, 'precio_costo', e.target.value)} />
                </Field>
            </div>

            {insumo ? (
                <p className="text-xs text-slate-500">Insumo de cocina: no tiene precio de venta.</p>
            ) : (
                <div className="rounded-lg bg-slate-50 ring-1 ring-slate-200">
                    <button type="button" onClick={() => setConPrecio((v) => !v)} aria-expanded={conPrecio}
                        className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-xs font-medium text-slate-700">
                        <span>Precio de venta {venta > 0 && <span className="font-mono text-slate-900">{currency.format(venta)}</span>}{margen !== null && <span className={cx('ml-2', margen >= 0 ? 'text-emerald-700' : 'text-rose-700')}>margen {margen.toFixed(1)}%</span>}</span>
                        <ChevronDown className={cx('h-4 w-4 transition-transform', conPrecio && 'rotate-180')} aria-hidden="true" />
                    </button>
                    {conPrecio && (
                        <div className="grid grid-cols-2 gap-2 border-t border-slate-200 p-3">
                            <Field label="Margen (%)">
                                <div className="space-y-1.5">
                                    <Input type="number" inputMode="decimal" step="0.1" className="text-center font-mono" value={item.porcentaje_utilidad ?? ''}
                                        onWheel={(e) => e.target.blur()} onChange={(e) => onCampo(item.uid, 'porcentaje_utilidad', e.target.value)} />
                                    <div className="flex gap-1">
                                        {MARGENES.map((m) => (
                                            <button key={m} type="button" onClick={() => onCampo(item.uid, 'porcentaje_utilidad', m)} aria-pressed={Number(item.porcentaje_utilidad) === m}
                                                className={cx('h-7 flex-1 rounded-md text-[0.6875rem] font-semibold tabular-nums ring-1', Number(item.porcentaje_utilidad) === m ? 'bg-brand text-on-brand ring-brand' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-100')}>
                                                {m}%
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </Field>
                            <Field label="Precio de venta (S/)" hint="Actualiza el precio de esta presentación.">
                                <Input type="number" inputMode="decimal" step="0.01" min="0" className="text-right font-mono" value={item.precio_venta} placeholder="0.00"
                                    onWheel={(e) => e.target.blur()} onChange={(e) => onCampo(item.uid, 'precio_venta', e.target.value)} />
                            </Field>
                        </div>
                    )}
                </div>
            )}

            <p className="flex justify-between border-t border-slate-100 pt-2 text-xs text-slate-500">
                <span>Subtotal</span>
                <span className="font-mono font-semibold text-slate-900 tabular-nums">{currency.format(item.subtotal)}</span>
            </p>
        </li>
    );
}
