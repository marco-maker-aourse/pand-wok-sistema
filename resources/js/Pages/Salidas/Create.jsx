import { useEffect, useMemo, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CheckCircle2, ChevronRight, ClipboardList, Minus, PackageMinus, Search, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge, Button, Card, ConfirmDialog, Dialog, EmptyState, Field, IconButton, Input, SearchInput, Select, Textarea, cx, toast } from '@/Components/ui';

const MOTIVOS_RESPALDO = ['MERMA', 'VENCIMIENTO', 'DETERIORO / ROTURA', 'AJUSTE DE INVENTARIO', 'USO INTERNO'];

const cantidadTexto = (valor) => {
    const n = Number(valor || 0);
    return Number.isInteger(n) ? String(n) : n.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
};
const unidad = (p) => p?.unidad_base?.nombre || 'unidades';
const nombrePresentacion = (p) => p?.presentacion?.nombre || p?.nombre || 'Unidad';
const etiquetaPresentacion = (p, producto) => (Number(p.factor_conversion) > 1 ? `${nombrePresentacion(p)} · ${cantidadTexto(p.factor_conversion)} ${unidad(producto)}` : nombrePresentacion(p));

/** Baja de inventario por merma, vencimiento, deterioro o ajuste, con justificación para el kardex. */
export default function Create({ productos = [], motivos = [] }) {
    const listaMotivos = motivos.length ? motivos : MOTIVOS_RESPALDO;
    const [busqueda, setBusqueda] = useState('');
    const [lineas, setLineas] = useState([]);
    const [confirmando, setConfirmando] = useState(false);
    const [registrada, setRegistrada] = useState(null);
    const [vista, setVista] = useState('catalogo');
    const { data, setData, post, processing } = useForm({ motivo: listaMotivos[0], observacion: '', detalles: [] });

    useEffect(() => { setData('detalles', lineas); }, [lineas]); // eslint-disable-line react-hooks/exhaustive-deps

    const visibles = useMemo(() => {
        const q = busqueda.toLowerCase().trim();
        return q ? productos.filter((p) => (p.nombre || '').toLowerCase().includes(q) || (p.codigo_barras || '').includes(q)) : productos;
    }, [productos, busqueda]);
    const enLista = useMemo(() => lineas.reduce((m, l) => ({ ...m, [l.id_producto]: (m[l.id_producto] ?? 0) + 1 }), {}), [lineas]);

    const agregar = (producto) => {
        const presentaciones = producto.presentaciones || [];
        const primera = presentaciones[0] || null;
        setLineas((actual) => [{
            uid: `${producto.id_producto}_${Date.now()}`,
            id_producto: producto.id_producto,
            id_prod_pres: primera ? primera.id_prod_pres : null,
            nombre: producto.nombre,
            presentaciones,
            unidad_base: producto.unidad_base,
            factor_conversion: primera ? Number(primera.factor_conversion) : 1,
            cantidad: 1,
            lote: producto.lote_recomendado?.codigo_lote || producto.lotes?.[0]?.codigo_lote || '',
            lotes: producto.lotes || [],
            stock_maximo: producto.stock_actual ?? 0,
        }, ...actual]);
    };

    const cambiar = (uid, cambios) => setLineas((actual) => actual.map((l) => (l.uid === uid ? { ...l, ...cambios } : l)));
    const cambiarPresentacion = (uid, id) => {
        const linea = lineas.find((l) => l.uid === uid);
        const pres = linea?.presentaciones.find((p) => String(p.id_prod_pres) === String(id));
        cambiar(uid, { id_prod_pres: pres ? pres.id_prod_pres : linea.id_prod_pres, factor_conversion: pres ? Number(pres.factor_conversion) : 1 });
    };

    const faltante = lineas.length === 0 ? 'Agrega al menos un producto.' : !data.observacion.trim() ? 'Escribe la justificación de la baja.' : null;

    const revisar = (e) => {
        e.preventDefault();
        if (faltante) {
            toast.warning('Falta completar la salida', faltante);
            return;
        }
        setConfirmando(true);
    };

    const confirmar = () => post('/salidas', {
        preserveScroll: true,
        onSuccess: () => {
            setConfirmando(false);
            setRegistrada({ motivo: data.motivo, observacion: data.observacion, lineas: lineas.length });
        },
        onError: (errores) => {
            setConfirmando(false);
            toast.error('No se pudo registrar la salida', Object.values(errores)[0] || 'Revisa los datos e inténtalo de nuevo.');
        },
    });

    const panel = 'flex h-[calc(100dvh-12.5rem)] min-h-[26rem] flex-col overflow-hidden lg:h-[calc(100dvh-9rem)]';

    return (
        <AuthenticatedLayout header="Nueva salida" sinBarraInferior>
            <Head title="Nueva salida de inventario" />

            <div className="mb-3 flex items-center gap-2">
                <Button variant="ghost" icon={ArrowLeft} href="/salidas">Salidas</Button>
                <div className="ml-auto grid grid-cols-2 gap-1 rounded-xl bg-slate-200/60 p-1 lg:hidden" role="tablist" aria-label="Vista">
                    {[['catalogo', 'Productos'], ['salida', lineas.length ? `Baja (${lineas.length})` : 'Baja']].map(([clave, etiqueta]) => (
                        <button key={clave} type="button" role="tab" aria-selected={vista === clave} onClick={() => setVista(clave)}
                            className={cx('h-9 rounded-lg px-3 text-sm font-medium', vista === clave ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600')}>
                            {etiqueta}
                        </button>
                    ))}
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_28rem]">
                <Card className={cx(panel, vista !== 'catalogo' && 'hidden lg:flex')}>
                    <div className="border-b border-slate-100 p-3 sm:p-4">
                        <SearchInput value={busqueda} onChange={setBusqueda} placeholder="Buscar insumo o producto…" label="Buscar producto para dar de baja" autoFocus />
                    </div>
                    <div className="flex-1 overflow-y-auto p-3 sm:p-4">
                        {visibles.length === 0 ? (
                            <EmptyState icon={Search} title="No hay productos que coincidan" description="Prueba con otra palabra." />
                        ) : (
                            <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 xl:grid-cols-4">
                                {visibles.map((p) => {
                                    const variantes = p.controla_stock === false;
                                    const stock = Number(p.stock_actual ?? 0);
                                    const sinStock = !variantes && stock <= 0;
                                    const veces = enLista[p.id_producto] ?? 0;
                                    return (
                                        <button key={p.id_producto} type="button" disabled={variantes || sinStock} onClick={() => agregar(p)}
                                            className={cx('group relative flex min-h-24 flex-col justify-between rounded-xl bg-white p-3 text-left ring-1 transition-[box-shadow,transform] enabled:hover:ring-rose-300 enabled:active:scale-[0.98] disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-60', veces ? 'ring-2 ring-rose-400' : 'ring-slate-200')}>
                                            {veces > 0 && <span className="absolute -top-2 -right-2 grid h-6 min-w-6 place-items-center rounded-full bg-rose-600 px-1.5 font-mono text-xs font-bold text-white">{veces}</span>}
                                            <span className="text-sm leading-snug font-medium text-slate-900">{p.nombre}</span>
                                            <span className="mt-2 flex items-end justify-between gap-2">
                                                {variantes ? (
                                                    <span className="text-xs text-slate-500">Stock en: {p.stock_en.join(', ')}</span>
                                                ) : sinStock ? (
                                                    <Badge tone="danger">Sin stock</Badge>
                                                ) : (
                                                    <span className="font-mono text-xs text-slate-600 tabular-nums">{cantidadTexto(stock)} {unidad(p)}</span>
                                                )}
                                                {!variantes && !sinStock && <Minus className="h-4 w-4 shrink-0 text-slate-300 group-hover:text-rose-600" aria-hidden="true" />}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </Card>

                <Card as="form" onSubmit={revisar} className={cx(panel, vista !== 'salida' && 'hidden lg:flex')}>
                    <div className="space-y-3 border-b border-slate-100 bg-slate-50/60 p-4">
                        <fieldset>
                            <legend className="mb-1.5 text-xs font-medium text-slate-700">Motivo</legend>
                            <div className="flex flex-wrap gap-1.5">
                                {listaMotivos.map((m) => (
                                    <button key={m} type="button" aria-pressed={data.motivo === m} onClick={() => setData('motivo', m)}
                                        className={cx('h-8 rounded-lg px-2.5 text-xs font-medium ring-1 transition-colors', data.motivo === m ? 'bg-brand text-on-brand ring-brand' : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50')}>
                                        {m.charAt(0) + m.slice(1).toLowerCase()}
                                    </button>
                                ))}
                            </div>
                        </fieldset>
                        <Field label="Justificación" required hint="Queda en el kardex: qué pasó y cuándo.">
                            <Textarea rows={2} value={data.observacion} onChange={(e) => setData('observacion', e.target.value)} placeholder="Ej.: pollo vencido detectado en la cámara el 25/11" />
                        </Field>
                    </div>

                    <div className="flex-1 overflow-y-auto overscroll-contain p-3">
                        {lineas.length === 0 ? (
                            <EmptyState icon={ClipboardList} title="Nada para dar de baja" description="Elige los productos afectados." />
                        ) : (
                            <ul className="space-y-2.5">
                                {lineas.map((l) => {
                                    const baja = (Number(l.cantidad) || 0) * l.factor_conversion;
                                    const excede = l.stock_maximo > 0 && baja > l.stock_maximo;
                                    return (
                                        <li key={l.uid} className="space-y-2.5 rounded-xl bg-white p-3.5 ring-1 ring-slate-200">
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-semibold text-slate-900">{l.nombre}</p>
                                                    <p className={cx('text-xs', excede ? 'font-medium text-rose-700' : 'text-slate-500')}>
                                                        Descuenta <span className="font-mono tabular-nums">{cantidadTexto(baja)} {unidad(l)}</span> de {cantidadTexto(l.stock_maximo)}
                                                    </p>
                                                </div>
                                                <IconButton icon={Trash2} tone="danger" label={`Quitar ${l.nombre}`} onClick={() => setLineas((a) => a.filter((x) => x.uid !== l.uid))} />
                                            </div>
                                            <div className="grid grid-cols-[minmax(0,1fr)_5rem] gap-2">
                                                <Field label="Presentación">
                                                    <Select value={l.id_prod_pres || ''} onChange={(e) => cambiarPresentacion(l.uid, e.target.value)}>
                                                        {l.presentaciones.map((p) => <option key={p.id_prod_pres} value={p.id_prod_pres}>{etiquetaPresentacion(p, l)}</option>)}
                                                    </Select>
                                                </Field>
                                                <Field label="Cantidad">
                                                    <Input inputMode="numeric" className="text-center font-mono" value={l.cantidad} placeholder="1"
                                                        onChange={(e) => { const limpio = e.target.value.replace(/\D/g, ''); cambiar(l.uid, { cantidad: limpio === '' ? '' : parseInt(limpio, 10) }); }}
                                                        onBlur={() => cambiar(l.uid, { cantidad: Math.max(1, Number(l.cantidad) || 1) })} />
                                                </Field>
                                            </div>
                                            <Field label="Lote (opcional)">
                                                <Input className="font-mono uppercase" value={l.lote} placeholder="Se usa el más antiguo" onChange={(e) => cambiar(l.uid, { lote: e.target.value.toUpperCase() })} />
                                            </Field>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </div>

                    <div className="space-y-2.5 border-t border-slate-200 bg-slate-50/80 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                        {faltante && lineas.length > 0 && <p className="text-xs text-amber-700" role="status">{faltante}</p>}
                        <Button type="submit" variant="danger" size="lg" block icon={PackageMinus} disabled={processing || lineas.length === 0}>
                            Dar de baja {lineas.length ? `${lineas.length} ${lineas.length === 1 ? 'producto' : 'productos'}` : ''}
                        </Button>
                    </div>
                </Card>
            </div>

            {vista === 'catalogo' && lineas.length > 0 && (
                <button type="button" onClick={() => setVista('salida')} className="fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-20 flex h-14 items-center justify-between rounded-2xl bg-rose-600 px-5 font-semibold text-white shadow-[var(--shadow-flotante)] lg:hidden">
                    <span>{lineas.length} para dar de baja</span>
                    <ChevronRight className="h-5 w-5" aria-hidden="true" />
                </button>
            )}

            <ConfirmDialog
                open={confirmando}
                onClose={() => setConfirmando(false)}
                onConfirm={confirmar}
                loading={processing}
                title="¿Dar de baja del inventario?"
                confirmLabel="Confirmar baja"
                cancelLabel="Revisar"
            >
                <dl className="mt-1 space-y-1">
                    <div className="flex justify-between gap-6"><dt>Motivo</dt><dd className="font-medium text-slate-900">{data.motivo}</dd></div>
                    <div className="flex justify-between gap-6"><dt>Productos</dt><dd className="font-medium text-slate-900 tabular-nums">{lineas.length}</dd></div>
                </dl>
                <p className="mt-2 flex gap-1.5 text-xs text-amber-800"><AlertTriangle className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />El stock se descuenta de inmediato.</p>
            </ConfirmDialog>

            <Dialog open={Boolean(registrada)} onClose={() => router.visit('/salidas')} size="sm" footer={<Button onClick={() => router.visit('/salidas')} autoFocus>Ver salidas</Button>}>
                {registrada && (
                    <div className="flex flex-col items-center gap-2 px-5 pt-6 pb-5 text-center">
                        <span className="grid h-12 w-12 place-items-center rounded-full bg-emerald-50 text-emerald-600"><CheckCircle2 className="h-6 w-6" aria-hidden="true" /></span>
                        <h2 className="mt-1 text-base font-semibold text-slate-900">Salida registrada</h2>
                        <p className="text-sm text-slate-600">{registrada.motivo} · {registrada.lineas} {registrada.lineas === 1 ? 'producto' : 'productos'}</p>
                        <p className="text-xs text-slate-500 italic">“{registrada.observacion}”</p>
                    </div>
                )}
            </Dialog>
        </AuthenticatedLayout>
    );
}
