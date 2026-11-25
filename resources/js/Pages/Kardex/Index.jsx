import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { ArrowDownLeft, ArrowUpRight, Boxes, ChefHat, ChevronRight, CupSoda, Eye, Package, PackageX, Tag, Wheat } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge, Button, Card, Dialog, EmptyState, Input, PageHeader, SearchInput, Table, TBody, Td, Th, THead, Tr, cx, toast } from '@/Components/ui';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const fechaHora = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' });

/*
 * Referencia visual de "quedan pocos" en unidad base. No es un mínimo configurado por
 * producto (el backend no lo tiene todavía); solo ayuda a ver qué revisar.
 */
const POCAS_UNIDADES = 5;

const TIPOS = [
    { clave: 'todos', etiqueta: 'Todos', icono: Boxes },
    { clave: 'INSUMO', etiqueta: 'Insumos', icono: Wheat },
    { clave: 'DIRECTO', etiqueta: 'Bebidas y venta directa', etiquetaCorta: 'Venta directa', icono: CupSoda },
    { clave: 'PREPARADO', etiqueta: 'Platos preparados', etiquetaCorta: 'Preparados', icono: ChefHat },
];
const FILTROS_STOCK = [
    { clave: 'todos', etiqueta: 'Todo' },
    { clave: 'con', etiqueta: 'Con stock' },
    { clave: 'pocos', etiqueta: `Quedan ${POCAS_UNIDADES} o menos` },
    { clave: 'agotado', etiqueta: 'Agotados' },
];

const cantidad = (valor) => {
    const numero = Number(valor || 0);
    return Number.isInteger(numero) ? String(numero) : numero.toFixed(3).replace(/0+$/, '').replace(/\.$/, '');
};
const unidad = (producto) => producto?.unidad_base?.nombre || 'unidades';
const stockDe = (producto) => Number(producto.stock_actual ?? 0);
const controla = (producto) => producto.controla_stock !== false;

/** Situación del stock, sin depender solo del color: cada estado tiene texto propio. */
function situacion(producto) {
    if (producto.tipo_producto === 'PREPARADO') return { clave: 'receta', etiqueta: 'Por receta', tone: 'neutral' };
    if (!controla(producto)) return { clave: 'variantes', etiqueta: 'En variantes', tone: 'info' };
    const stock = stockDe(producto);
    if (stock <= 0) return { clave: 'agotado', etiqueta: 'Agotado', tone: 'danger' };
    if (stock <= POCAS_UNIDADES) return { clave: 'pocos', etiqueta: 'Quedan pocos', tone: 'warning' };
    return { clave: 'con', etiqueta: 'Con stock', tone: 'success' };
}

/** "2 Caja x25 + 3 unidades": el saldo expresado en la presentación de compra más grande. */
function equivalencia(producto) {
    const mayor = (producto.presentaciones || []).reduce((max, p) => (Number(p.factor_conversion) > Number(max?.factor_conversion ?? 1) ? p : max), null);
    const stock = stockDe(producto);
    if (!mayor || stock <= 0) return null;
    const factor = Number(mayor.factor_conversion);
    const enteras = Math.floor(stock / factor);
    const resto = Number((stock - enteras * factor).toFixed(3));
    if (enteras === 0) return null;
    return `≈ ${enteras} ${mayor.nombre}${resto > 0 ? ` + ${cantidad(resto)} ${unidad(producto)}` : ''}`;
}

function Saldo({ producto }) {
    if (producto.tipo_producto === 'PREPARADO') {
        return <p className="text-xs text-slate-500">Descuenta insumos por receta</p>;
    }
    if (!controla(producto)) {
        return <p className="text-xs text-slate-600">Stock en: <span className="font-medium">{producto.stock_en.join(', ')}</span></p>;
    }
    const eq = equivalencia(producto);
    return (
        <div>
            <p className="font-mono text-sm font-semibold text-slate-900 tabular-nums">{cantidad(stockDe(producto))} <span className="font-sans text-xs font-normal text-slate-500">{unidad(producto)}</span></p>
            {eq && <p className="text-xs text-slate-500">{eq}</p>}
        </div>
    );
}

function DetalleExistencia({ producto, onCerrar, onEditarPrecios }) {
    const lotes = producto.lotes || [];
    const movimientos = producto.movimientos || [];
    const estado = situacion(producto);
    return (
        <Dialog open onClose={onCerrar} size="xl" title={producto.nombre} description={producto.codigo_barras ? `Código ${producto.codigo_barras}` : undefined}
            footer={<><Button variant="secondary" icon={Tag} onClick={() => onEditarPrecios(producto)}>Precios</Button><Button onClick={onCerrar}>Cerrar</Button></>}>
            <div className="space-y-5 p-5">
                {controla(producto) ? (
                    <dl className="grid grid-cols-3 gap-px overflow-hidden rounded-xl bg-slate-200 ring-1 ring-slate-200">
                        {[
                            ['Stock en esta sucursal', `${cantidad(stockDe(producto))} ${unidad(producto)}`],
                            ['Último costo', currency.format(Number(producto.precio_costo ?? 0))],
                            ['Valorizado', currency.format(stockDe(producto) * Number(producto.precio_costo ?? 0))],
                        ].map(([etiqueta, valor]) => (
                            <div key={etiqueta} className="bg-white px-3 py-2.5">
                                <dt className="text-xs text-slate-500">{etiqueta}</dt>
                                <dd className="mt-0.5 font-mono text-sm font-semibold text-slate-900 tabular-nums">{valor}</dd>
                            </div>
                        ))}
                    </dl>
                ) : (
                    <p className="flex items-start gap-2 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-200">
                        <Badge tone={estado.tone}>{estado.etiqueta}</Badge>
                        <span>{producto.tipo_producto === 'PREPARADO'
                            ? 'Este plato no lleva stock propio: al venderse descuenta los insumos de su receta.'
                            : `Cada presentación descuenta de su variante física: ${producto.stock_en.join(', ')}. Revisa ahí el saldo.`}</span>
                    </p>
                )}

                {lotes.length > 0 && (
                    <section>
                        <h3 className="mb-2 text-xs font-medium text-slate-500">Lotes con saldo (se consumen primero los que vencen antes)</h3>
                        <ul className="divide-y divide-slate-100 rounded-xl ring-1 ring-slate-200">
                            {lotes.map((lote) => (
                                <li key={lote.id_lote ?? lote.codigo_lote} className="flex items-center justify-between gap-3 px-3.5 py-2 text-sm">
                                    <span className="font-mono text-xs font-semibold text-slate-800">{lote.codigo_lote || 'Sin código'}</span>
                                    <span className="text-xs text-slate-500">{lote.fecha_vencimiento ? `Vence ${new Date(lote.fecha_vencimiento).toLocaleDateString('es-PE')}` : 'No perecible'}</span>
                                    <span className="font-mono font-semibold text-slate-900 tabular-nums">{cantidad(lote.stock ?? lote.stock_actual)} {unidad(producto)}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {controla(producto) && (
                    <section>
                        <h3 className="mb-2 flex items-center justify-between text-xs font-medium text-slate-500">
                            Últimos movimientos
                            <span className="tabular-nums">{movimientos.length}</span>
                        </h3>
                        {movimientos.length === 0 ? (
                            <p className="rounded-xl px-4 py-6 text-center text-sm text-slate-500 ring-1 ring-slate-200">Aún no hay movimientos en esta sucursal.</p>
                        ) : (
                            <ul className="max-h-64 divide-y divide-slate-100 overflow-y-auto rounded-xl ring-1 ring-slate-200">
                                {movimientos.map((m) => {
                                    const entrada = Number(m.cantidad_entrada) > 0;
                                    const Icono = entrada ? ArrowDownLeft : ArrowUpRight;
                                    return (
                                        <li key={m.id_kardex} className="flex items-center gap-3 px-3.5 py-2 text-sm">
                                            <span className={cx('grid h-7 w-7 shrink-0 place-items-center rounded-lg', entrada ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700')}>
                                                <Icono className="h-4 w-4" aria-hidden="true" />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block font-medium text-slate-800">{entrada ? 'Entrada' : 'Salida'}</span>
                                                <span className="block text-xs text-slate-500 tabular-nums">{m.ultima_actualizacion ? fechaHora.format(new Date(m.ultima_actualizacion)) : '—'}</span>
                                            </span>
                                            <span className={cx('font-mono font-semibold tabular-nums', entrada ? 'text-emerald-700' : 'text-rose-700')}>
                                                {entrada ? '+' : '−'}{cantidad(entrada ? m.cantidad_entrada : m.cantidad_salida)}
                                            </span>
                                            <span className="w-20 text-right font-mono text-xs text-slate-500 tabular-nums">saldo {cantidad(m.stock_actual)}</span>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </section>
                )}
            </div>
        </Dialog>
    );
}

function EditarPrecios({ producto, onCerrar }) {
    const [precios, setPrecios] = useState(() => (producto.presentaciones || []).map((p) => ({
        id_presentacion: p.id_presentacion,
        nombre: p.presentacion?.nombre || p.nombre || 'Unidad',
        factor_conversion: p.factor_conversion || 1,
        precio_venta: String(Number(p.precio_venta || 0)),
    })));
    const [guardando, setGuardando] = useState(false);

    const guardar = async (event) => {
        event.preventDefault();
        setGuardando(true);
        try {
            // Cada precio mantiene su presentación y factor; el controlador usa updateOrCreate,
            // así este flujo no altera el inventario.
            await Promise.all(precios.map((precio) => axios.post(
                `/productos/${producto.id_producto}/presentaciones`,
                { id_presentacion: precio.id_presentacion, factor_conversion: precio.factor_conversion, precio_venta: Number(precio.precio_venta) || 0 },
                { headers: { Accept: 'application/json' } },
            )));
            toast.success('Precios actualizados', producto.nombre);
            onCerrar();
            router.reload({ only: ['productos'], preserveScroll: true });
        } catch (error) {
            toast.error('No se pudieron guardar los precios', error.response?.data?.message || 'Revisa los montos e inténtalo de nuevo.');
        } finally {
            setGuardando(false);
        }
    };

    return (
        <Dialog open onClose={guardando ? () => {} : onCerrar} size="md" title="Precios de venta" description={producto.nombre}
            footer={<><Button variant="secondary" onClick={onCerrar} disabled={guardando}>Cancelar</Button><Button type="submit" form="form-precios" loading={guardando}>Guardar precios</Button></>}>
            <form id="form-precios" onSubmit={guardar} className="space-y-2 p-5">
                {precios.length === 0 && <p className="text-sm text-slate-500">Este producto no tiene presentaciones activas.</p>}
                {precios.map((item, indice) => (
                    <label key={item.id_presentacion ?? indice} className="flex items-center justify-between gap-3 rounded-xl px-3.5 py-2.5 ring-1 ring-slate-200">
                        <span className="min-w-0">
                            <span className="block text-sm font-medium text-slate-800">{item.nombre}</span>
                            <span className="text-xs text-slate-500">{Number(item.factor_conversion) > 1 ? `${cantidad(item.factor_conversion)} ${unidad(producto)}` : `1 ${unidad(producto)}`}</span>
                        </span>
                        <span className="relative w-32">
                            <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-xs text-slate-400">S/</span>
                            <Input type="number" inputMode="decimal" min="0" step="0.01" required className="pl-8 text-right font-mono" value={item.precio_venta}
                                onWheel={(e) => e.target.blur()}
                                onChange={(e) => setPrecios((actual) => actual.map((p, i) => (i === indice ? { ...p, precio_venta: e.target.value } : p)))} />
                        </span>
                    </label>
                ))}
                <p className="pt-1 text-xs text-slate-500">Los precios de la carta por sucursal se editan en Carta.</p>
            </form>
        </Dialog>
    );
}

export default function Index({ productos = [] }) {
    const lista = useMemo(() => (Array.isArray(productos) ? productos : productos?.data ?? []), [productos]);
    const [busqueda, setBusqueda] = useState('');
    const [tipo, setTipo] = useState('INSUMO');
    const [filtroStock, setFiltroStock] = useState('todos');
    const [detalle, setDetalle] = useState(null);
    const [precios, setPrecios] = useState(null);

    const conteoTipo = useMemo(() => lista.reduce((mapa, p) => ({ ...mapa, [p.tipo_producto]: (mapa[p.tipo_producto] ?? 0) + 1 }), {}), [lista]);
    const metricas = useMemo(() => {
        const conControl = lista.filter(controla);
        return {
            controlados: conControl.length,
            agotados: conControl.filter((p) => stockDe(p) <= 0).length,
            pocos: conControl.filter((p) => stockDe(p) > 0 && stockDe(p) <= POCAS_UNIDADES).length,
            valorizado: conControl.reduce((suma, p) => suma + Math.max(0, stockDe(p)) * Number(p.precio_costo || 0), 0),
        };
    }, [lista]);

    const filtrados = useMemo(() => {
        const q = busqueda.trim().toLowerCase();
        return lista.filter((p) => {
            if (tipo !== 'todos' && p.tipo_producto !== tipo) return false;
            if (q && !(p.nombre || '').toLowerCase().includes(q) && !(p.codigo_barras || '').toLowerCase().includes(q)) return false;
            if (filtroStock !== 'todos') return controla(p) && situacion(p).clave === filtroStock;
            return true;
        });
    }, [lista, tipo, busqueda, filtroStock]);

    const abrirPrecios = (producto) => { setDetalle(null); setPrecios(producto); };

    return (
        <AuthenticatedLayout header="Existencias">
            <Head title="Existencias" />

            <div className="space-y-4">
                <PageHeader
                    title="Existencias"
                    description="Saldo de insumos y bebidas en esta sucursal, en su unidad de inventario, con lotes y últimos movimientos."
                    icon={Boxes}
                    actions={(
                        <>
                            <Button variant="secondary" href="/salidas">Salidas y ajustes</Button>
                            <Button href="/ingresos">Registrar entrada</Button>
                        </>
                    )}
                />

                <dl className="grid grid-cols-2 gap-px overflow-hidden rounded-2xl bg-slate-200/70 ring-1 ring-slate-200/70 sm:grid-cols-4" aria-label="Resumen de existencias">
                    {[
                        { etiqueta: 'Con control de stock', valor: metricas.controlados, icono: Package },
                        { etiqueta: 'Agotados', valor: metricas.agotados, icono: PackageX, tono: metricas.agotados ? 'text-rose-700' : '' },
                        { etiqueta: `Quedan ${POCAS_UNIDADES} o menos`, valor: metricas.pocos, tono: metricas.pocos ? 'text-amber-700' : '' },
                        { etiqueta: 'Valorizado (último costo)', valor: currency.format(metricas.valorizado) },
                    ].map(({ etiqueta, valor, icono: Icono, tono }) => (
                        <div key={etiqueta} className="bg-white px-4 py-3">
                            <dt className="flex items-center gap-1.5 truncate text-xs font-medium text-slate-500">{Icono && <Icono className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />}{etiqueta}</dt>
                            <dd className={cx('mt-0.5 text-xl font-semibold tracking-tight text-slate-900 tabular-nums', tono)}>{valor}</dd>
                        </div>
                    ))}
                </dl>

                <Card className="overflow-hidden">
                    <div className="space-y-3 border-b border-slate-100 p-3 sm:p-4">
                        <div className="-mx-1 flex gap-1 overflow-x-auto px-1 [scrollbar-width:none]" role="tablist" aria-label="Tipo de producto">
                            {TIPOS.map(({ clave, etiqueta, etiquetaCorta, icono: Icono }) => {
                                const total = clave === 'todos' ? lista.length : conteoTipo[clave] ?? 0;
                                return (
                                    <button key={clave} type="button" role="tab" aria-selected={tipo === clave} onClick={() => setTipo(clave)}
                                        className={cx('inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg px-3 text-sm font-medium transition-colors', tipo === clave ? 'bg-brand-soft text-brand-ink' : 'text-slate-600 hover:bg-slate-100')}>
                                        <Icono className="h-4 w-4" aria-hidden="true" />
                                        <span className="hidden sm:inline">{etiqueta}</span><span className="sm:hidden">{etiquetaCorta ?? etiqueta}</span>
                                        <span className="font-mono text-xs tabular-nums opacity-70">{total}</span>
                                    </button>
                                );
                            })}
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <SearchInput value={busqueda} onChange={setBusqueda} placeholder="Nombre o código…" label="Buscar producto" className="sm:w-72" />
                            {tipo !== 'PREPARADO' && (
                                <div className="flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1 [scrollbar-width:none]" role="radiogroup" aria-label="Situación del stock">
                                    {FILTROS_STOCK.map(({ clave, etiqueta }) => (
                                        <button key={clave} type="button" role="radio" aria-checked={filtroStock === clave} onClick={() => setFiltroStock(clave)}
                                            className={cx('h-8 shrink-0 rounded-md px-3 text-xs font-medium whitespace-nowrap transition-colors', filtroStock === clave ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900')}>
                                            {etiqueta}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {filtrados.length === 0 ? (
                        <EmptyState icon={Boxes} title="No hay productos que coincidan" description={lista.length ? 'Prueba con otro tipo, situación o búsqueda.' : 'Registra una entrada de insumos para empezar a llevar stock.'} />
                    ) : (
                        <>
                            <ul className="divide-y divide-slate-100 md:hidden">
                                {filtrados.map((p) => {
                                    const estado = situacion(p);
                                    return (
                                        <li key={p.id_producto}>
                                            <button type="button" onClick={() => setDetalle(p)} className="flex w-full items-center gap-3 px-4 py-3 text-left active:bg-slate-50">
                                                <div className="min-w-0 flex-1 space-y-1">
                                                    <p className="truncate text-sm font-medium text-slate-900">{p.nombre}</p>
                                                    <Saldo producto={p} />
                                                </div>
                                                <Badge tone={estado.tone}>{estado.etiqueta}</Badge>
                                                <ChevronRight className="h-4 w-4 text-slate-400" aria-hidden="true" />
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>

                            <Table className="hidden md:block" caption="Existencias por producto">
                                <THead>
                                    <Th>Producto</Th>
                                    <Th>Saldo</Th>
                                    <Th>Situación</Th>
                                    <Th>Lotes</Th>
                                    <Th align="right"><span className="sr-only">Acciones</span></Th>
                                </THead>
                                <TBody>
                                    {filtrados.map((p) => {
                                        const estado = situacion(p);
                                        const lotes = p.lotes || [];
                                        return (
                                            <Tr key={p.id_producto}>
                                                <Td>
                                                    <p className="font-medium text-slate-900">{p.nombre}</p>
                                                    {p.codigo_barras && <p className="font-mono text-xs text-slate-500">{p.codigo_barras}</p>}
                                                </Td>
                                                <Td><Saldo producto={p} /></Td>
                                                <Td><Badge tone={estado.tone}>{estado.etiqueta}</Badge></Td>
                                                <Td className="text-xs text-slate-600">
                                                    {lotes.length === 0 ? <span className="text-slate-400">—</span> : (
                                                        <span>{lotes.length} {lotes.length === 1 ? 'lote' : 'lotes'}{lotes[0]?.fecha_vencimiento && <span className="block text-slate-500">próximo vence {new Date(lotes[0].fecha_vencimiento).toLocaleDateString('es-PE')}</span>}</span>
                                                    )}
                                                </Td>
                                                <Td align="right">
                                                    <div className="flex justify-end gap-1">
                                                        <Button variant="ghost" size="sm" icon={Tag} onClick={() => abrirPrecios(p)}>Precios</Button>
                                                        <Button variant="ghost" size="sm" icon={Eye} onClick={() => setDetalle(p)}>Detalle</Button>
                                                    </div>
                                                </Td>
                                            </Tr>
                                        );
                                    })}
                                </TBody>
                            </Table>
                        </>
                    )}
                </Card>
            </div>

            {detalle && <DetalleExistencia producto={detalle} onCerrar={() => setDetalle(null)} onEditarPrecios={abrirPrecios} />}
            {precios && <EditarPrecios producto={precios} onCerrar={() => setPrecios(null)} />}
        </AuthenticatedLayout>
    );
}
