import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, ChevronRight, PackagePlus } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, Card, Dialog, EmptyState, cx } from '@/Components/ui';
import CatalogoIngreso from '@/Components/Ingresos/CatalogoIngreso';
import LineaIngreso from '@/Components/Ingresos/LineaIngreso';
import ProveedorComprobante from '@/Components/Ingresos/ProveedorComprobante';
import useIngresoCompra from '@/Components/Ingresos/useIngresoCompra';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

/** Entrada de insumos y mercadería: elegir productos, proveedor, comprobante y costos. */
export default function Create({ productos = [], proveedores = [], tipos_comprobante: tiposComprobante = [], productoInicialId = 0 }) {
    const [registrado, setRegistrado] = useState(null);
    const [vista, setVista] = useState('catalogo');
    const compra = useIngresoCompra({ productos, proveedores, tiposComprobante, productoInicialId, onRegistrado: setRegistrado });
    const enCompra = useMemo(() => compra.carrito.reduce((mapa, l) => ({ ...mapa, [l.id_producto]: (mapa[l.id_producto] ?? 0) + 1 }), {}), [compra.carrito]);
    const panel = 'flex h-[calc(100dvh-12.5rem)] min-h-[26rem] flex-col overflow-hidden lg:h-[calc(100dvh-9rem)]';

    return (
        <AuthenticatedLayout header="Nueva entrada" sinBarraInferior>
            <Head title="Nueva entrada de insumos" />

            <div className="mb-3 flex items-center gap-2">
                <Button variant="ghost" icon={ArrowLeft} href="/ingresos">Entradas</Button>
                <div className="ml-auto grid grid-cols-2 gap-1 rounded-xl bg-slate-200/60 p-1 lg:hidden" role="tablist" aria-label="Vista">
                    {[['catalogo', 'Productos'], ['compra', compra.carrito.length ? `Compra (${compra.carrito.length})` : 'Compra']].map(([clave, etiqueta]) => (
                        <button key={clave} type="button" role="tab" aria-selected={vista === clave} onClick={() => setVista(clave)}
                            className={cx('h-9 rounded-lg px-3 text-sm font-medium', vista === clave ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600')}>
                            {etiqueta}
                        </button>
                    ))}
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_32rem]">
                <Card className={cx(panel, vista !== 'catalogo' && 'hidden lg:flex')}>
                    <CatalogoIngreso productos={productos} enCompra={enCompra} onAgregar={compra.agregar} />
                </Card>

                <Card className={cx(panel, vista !== 'compra' && 'hidden lg:flex')}>
                    <div className="border-b border-slate-100 bg-slate-50/60 p-4">
                        <ProveedorComprobante proveedor={compra.proveedor} data={compra.data} setData={compra.setData} tiposComprobante={tiposComprobante} onTipo={compra.cambiarTipoComprobante} />
                    </div>

                    <div className="flex-1 overflow-y-auto overscroll-contain p-3">
                        {compra.carrito.length === 0 ? (
                            <EmptyState icon={PackagePlus} title="La compra está vacía" description="Elige los insumos o productos que llegaron para indicar cantidad y costo." />
                        ) : (
                            <ul className="space-y-2.5">
                                {compra.carrito.map((item) => (
                                    <LineaIngreso key={item.uid} item={item} onPresentacion={compra.cambiarPresentacion} onCampo={compra.actualizar} onCantidadLista={compra.normalizarCantidad} onQuitar={compra.quitar} />
                                ))}
                            </ul>
                        )}
                    </div>

                    <form onSubmit={(e) => { e.preventDefault(); compra.registrar(); }} className="space-y-2.5 border-t border-slate-200 bg-slate-50/80 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                        <div className="flex items-baseline justify-between">
                            <span className="text-sm text-slate-600">Total de la compra</span>
                            <span className="font-mono text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">{currency.format(compra.total)}</span>
                        </div>
                        {compra.faltante && compra.carrito.length > 0 && <p className="text-xs text-amber-700" role="status">{compra.faltante}</p>}
                        <Button type="submit" size="lg" block icon={CheckCircle2} loading={compra.form.processing} disabled={compra.carrito.length === 0}>
                            Registrar entrada
                        </Button>
                    </form>
                </Card>
            </div>

            {vista === 'catalogo' && compra.carrito.length > 0 && (
                <button type="button" onClick={() => setVista('compra')} className="volumen-marca fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-20 flex h-14 items-center justify-between rounded-2xl px-5 font-semibold text-on-brand shadow-[var(--shadow-flotante)] lg:hidden">
                    <span>{compra.carrito.length} en la compra</span>
                    <span className="flex items-center gap-1 font-mono tabular-nums">{currency.format(compra.total)}<ChevronRight className="h-5 w-5" aria-hidden="true" /></span>
                </button>
            )}

            <Dialog open={Boolean(registrado)} onClose={() => router.visit('/ingresos')} size="sm"
                footer={<Button onClick={() => router.visit('/ingresos')} autoFocus>Ver entradas</Button>}>
                {registrado && (
                    <div className="flex flex-col items-center gap-2 px-5 pt-6 pb-5 text-center">
                        <span className="grid h-12 w-12 place-items-center rounded-full bg-emerald-50 text-emerald-600"><CheckCircle2 className="h-6 w-6" aria-hidden="true" /></span>
                        <h2 className="mt-1 text-base font-semibold text-slate-900">Entrada registrada</h2>
                        <p className="font-mono text-sm text-slate-700">{registrado.tipo} {registrado.serie}-{registrado.numero}</p>
                        <p className="text-sm text-slate-500">{registrado.proveedor}</p>
                        <dl className="mt-2 w-full space-y-1 rounded-xl bg-slate-50 p-3 text-sm ring-1 ring-slate-200">
                            <div className="flex justify-between"><dt className="text-slate-500">Productos</dt><dd className="font-medium tabular-nums">{registrado.articulos}</dd></div>
                            <div className="flex justify-between"><dt className="text-slate-500">Total</dt><dd className="font-mono font-semibold tabular-nums">{currency.format(registrado.total)}</dd></div>
                        </dl>
                    </div>
                )}
            </Dialog>
        </AuthenticatedLayout>
    );
}
