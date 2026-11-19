import { Plus, Trash2, TrendingUp } from 'lucide-react';
import { Button, Field, IconButton, Input, Select, cx } from '@/Components/ui';

const formatMoneda = (val) => `S/ ${Number(val || 0).toFixed(2)}`;
const SIN_FLECHAS = '[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none';
const EMPAQUES_RAPIDOS = [['SIXPACK', 6, 'Sixpack ×6'], ['DOCENA', 12, 'Docena ×12'], ['CAJA', 24, 'Caja ×24'], ['SACO', 50, 'Saco ×50']];

function CampoSoles({ label, value, onChange, required, hint, min = '0' }) {
    return (
        <Field label={label} required={required} hint={hint}>
            <Input
                type="number"
                step="0.01"
                min={min}
                placeholder="0.00"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                onWheel={(e) => e.target.blur()}
                className={cx('font-mono tabular-nums', SIN_FLECHAS)}
            />
        </Field>
    );
}

/** Fila de empaque mayorista con su rentabilidad de referencia. */
function FilaEmpaque({ fila, indice, f }) {
    const { costoNum, ventaUnitariaNum, unidadNombre, catalogoLocalPresentaciones, actualizarPresentacionFila, eliminarPresentacionFila } = f;
    const factorFila = Math.max(Number(fila.factor_conversion) || 1, 1);
    const precioFilaNum = Number(fila.precio_venta || 0);
    const costoFilaTotal = costoNum * factorFila;
    const unitarioEnFila = factorFila > 0 && precioFilaNum > 0 ? precioFilaNum / factorFila : 0;
    const gananciaFila = costoFilaTotal > 0 && precioFilaNum > costoFilaTotal ? precioFilaNum - costoFilaTotal : 0;
    const margenFilaPorc = costoFilaTotal > 0 ? ((precioFilaNum - costoFilaTotal) / costoFilaTotal) * 100 : 0;
    const ahorroClienteFila = ventaUnitariaNum > 0 && unitarioEnFila > 0 && unitarioEnFila < ventaUnitariaNum ? ventaUnitariaNum - unitarioEnFila : 0;
    const actualizar = (campo) => (e) => actualizarPresentacionFila(fila.id_temp, campo, e.target.value);

    return (
        <li className="space-y-2.5 rounded-lg border border-slate-200 bg-slate-50 p-3">
            <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-slate-700">Empaque {indice + 1}</span>
                <IconButton icon={Trash2} tone="danger" size="sm" label={`Quitar empaque ${indice + 1}`} onClick={() => eliminarPresentacionFila(fila.id_temp)} />
            </div>
            <div className="grid grid-cols-1 items-end gap-2.5 sm:grid-cols-12">
                <Field label="Tipo de empaque" required className="sm:col-span-5">
                    <Select value={fila.id_presentacion} onChange={actualizar('id_presentacion')}>
                        <option value="">Selecciona…</option>
                        {catalogoLocalPresentaciones.map((p) => <option key={p.id_presentacion} value={p.id_presentacion}>{p.nombre.toUpperCase()}</option>)}
                    </Select>
                </Field>
                <Field label={`Contiene (${unidadNombre.toLowerCase()})`} required className="sm:col-span-3">
                    <Input type="number" min="1" step="1" placeholder="12" value={fila.factor_conversion} onChange={actualizar('factor_conversion')} onWheel={(e) => e.target.blur()} className={cx('text-center font-mono tabular-nums', SIN_FLECHAS)} />
                </Field>
                <div className="sm:col-span-4">
                    <CampoSoles label="Precio de venta (S/)" required min="0.01" value={fila.precio_venta} onChange={(valor) => actualizarPresentacionFila(fila.id_temp, 'precio_venta', valor)} />
                </div>
            </div>
            {precioFilaNum > 0 && (
                <p className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-white px-2.5 py-1.5 text-xs text-slate-700 tabular-nums">
                    <span>Sale a <strong className="font-medium">{formatMoneda(unitarioEnFila)}</strong> por unidad</span>
                    {costoNum > 0 && <span className="text-emerald-700">Ganancia {formatMoneda(gananciaFila)} (+{margenFilaPorc.toFixed(1)}%)</span>}
                    {ahorroClienteFila > 0 && <span className="text-brand-ink">Ahorro al cliente {formatMoneda(ahorroClienteFila)} c/u</span>}
                </p>
            )}
        </li>
    );
}

/** Paso 3: costo, precio suelto y empaques por mayor. */
export default function PasoPrecios({ f }) {
    const { data, setData, unidadNombre, costoNum, ventaUnitariaNum, gananciaUnitaria, margenPorcentajeUnitario, agregarPresentacionFila } = f;
    const unidad = unidadNombre.toLowerCase();

    return (
        <div className="space-y-4">
            <section className="space-y-3 rounded-2xl bg-white p-4 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                <header>
                    <h3 className="text-sm font-semibold text-slate-900">Venta suelta</h3>
                    <p className="text-xs text-slate-500">Precios por 1 {unidad}.</p>
                </header>
                <div className="grid grid-cols-2 gap-3">
                    <CampoSoles label="Costo de compra (S/)" hint="Costo del proveedor." value={data.precio_costo} onChange={(valor) => setData('precio_costo', valor)} />
                    <CampoSoles label="Precio de venta (S/)" required min="0.01" hint={`Precio en caja por 1 ${unidad}.`} value={data.precio_venta_unitario} onChange={(valor) => setData('precio_venta_unitario', valor)} />
                </div>
                {costoNum > 0 && ventaUnitariaNum > 0 && (
                    <p className={cx('flex items-center justify-between gap-3 rounded-md px-3 py-2 text-xs tabular-nums', gananciaUnitaria > 0 ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800')}>
                        <span className="flex items-center gap-2">
                            <TrendingUp className="h-4 w-4 shrink-0" aria-hidden="true" />
                            Ganancia de {formatMoneda(gananciaUnitaria)} por {unidad}
                        </span>
                        <strong className="font-medium">{margenPorcentajeUnitario > 0 ? `+${margenPorcentajeUnitario.toFixed(1)}%` : `${margenPorcentajeUnitario.toFixed(1)}%`} de margen</strong>
                    </p>
                )}
            </section>

            <section className="space-y-3 rounded-2xl bg-white p-4 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                <header className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 className="text-sm font-semibold text-slate-900">Empaques y venta por mayor</h3>
                        <p className="text-xs text-slate-500">Cajas, sixpacks, docenas o sacos. Es opcional.</p>
                    </div>
                    <Button size="sm" variant="secondary" icon={Plus} onClick={() => agregarPresentacionFila('', 12)}>Agregar empaque</Button>
                </header>
                <div className="flex flex-wrap items-center gap-1.5">
                    <span className="mr-1 text-xs text-slate-500">Rápidos:</span>
                    {EMPAQUES_RAPIDOS.map(([nombre, factor, etiqueta]) => (
                        <button key={nombre} type="button" onClick={() => agregarPresentacionFila(nombre, factor)} className="rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-700 hover:border-brand-border hover:bg-brand-soft hover:text-brand-ink">
                            + {etiqueta}
                        </button>
                    ))}
                </div>
                {data.presentaciones_adicionales.length > 0 ? (
                    <ul className="space-y-2.5">
                        {data.presentaciones_adicionales.map((fila, indice) => <FilaEmpaque key={fila.id_temp} fila={fila} indice={indice} f={f} />)}
                    </ul>
                ) : (
                    <p className="rounded-lg border border-dashed border-slate-300 p-4 text-center text-xs text-slate-500">
                        Sin empaques adicionales: el producto se venderá solo suelto.
                    </p>
                )}
            </section>
        </div>
    );
}
