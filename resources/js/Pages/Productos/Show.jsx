import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, PageHeader } from '@/Components/ui';
import {
    ArrowLeft,
    Box,
    Boxes,
    CalendarClock,
    CircleDollarSign,
    Package,
    ReceiptText,
    Tags,
} from 'lucide-react';

const money = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
});

const decimal = new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 4,
});

function DataItem({ label, children }) {
    return (
        <div className="rounded-lg border border-slate-200/80 bg-slate-50/60 px-4 py-3">
            <p className="text-xs font-semibold text-slate-400">{label}</p>
            <div className="mt-1 text-sm font-bold text-slate-800">{children || '—'}</div>
        </div>
    );
}

export default function Show({ auth, producto, existencia }) {
    const tieneEntrada = Boolean(existencia);
    const esServicio = Boolean(producto.es_servicio);

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={`Producto: ${producto.nombre}`} />

            <div className="mx-auto w-full max-w-6xl space-y-5">
                <PageHeader
                    breadcrumbs={[{ label: 'Gestión' }, { label: 'Productos', href: '/productos' }, { label: 'Ficha' }]}
                    title="Ficha del producto"
                    description="Configuración, precio y disponibilidad actual."
                    actions={
                        <>
                            <Button href="/productos" variant="secondary" icon={ArrowLeft}>Volver</Button>
                            {!esServicio && !tieneEntrada && producto.presentaciones?.length > 0 && (
                                <Button href={`/ingresos/create?producto=${producto.id_producto}`} icon={Package}>Registrar entrada</Button>
                            )}
                        </>
                    }
                />

                <section className="overflow-hidden rounded-2xl bg-white shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="flex flex-col gap-5 border-b border-slate-100 bg-brand-soft/50 p-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="volumen-marca flex h-14 w-14 items-center justify-center rounded-2xl text-on-brand">
                                <Package className="h-7 w-7" />
                            </div>
                            <div>
                                <h2 className="text-xl font-semibold text-slate-900">{producto.nombre}</h2>
                                <p className="mt-1 font-mono text-xs font-semibold text-slate-500">
                                    {producto.codigo_barras || producto.codigo_sunat || 'Sin código asignado'}
                                </p>
                            </div>
                        </div>
                        <div className={`inline-flex w-fit items-center rounded-full border px-3 py-1 text-xs font-bold ${producto.estado ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500'}`}>
                            {producto.estado ? 'Activo' : 'Inactivo'}
                        </div>
                    </div>

                    <div className="grid gap-3 p-6 sm:grid-cols-2 lg:grid-cols-3">
                        <DataItem label="Categoría"><span className="inline-flex items-center gap-1.5"><Tags className="h-3.5 w-3.5 text-brand-ink" />{producto.categoria?.nombre || 'General'}</span></DataItem>
                        <DataItem label="Marca">{producto.marca?.nombre || 'Sin marca'}</DataItem>
                        <DataItem label="Unidad base"><span className="inline-flex items-center gap-1.5"><Box className="h-3.5 w-3.5 text-brand-ink" />{producto.unidad_base?.nombre || '—'}</span></DataItem>
                        <DataItem label="Afectación tributaria">{producto.afecta_igv ? 'Afecto a IGV' : 'Inafecto a IGV'}</DataItem>
                        <DataItem label="Tipo">{esServicio ? 'Servicio' : 'Producto inventariable'}</DataItem>
                    </div>
                </section>

                {!esServicio && (
                    <section className="grid gap-5 lg:grid-cols-3">
                        <div className="rounded-2xl bg-white p-6 lg:col-span-1 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                            <div className="flex items-center gap-2 text-slate-800"><Boxes className="h-5 w-5 text-brand-ink" /><h2 className="font-semibold">Existencia por sucursal</h2></div>
                            {tieneEntrada ? (
                                <>
                                    <p className="mt-6 text-3xl font-semibold text-slate-900">{decimal.format(Number(existencia.stock_actual || 0))}</p>
                                    <p className="mt-1 text-sm text-slate-500">{producto.unidad_base?.nombre || 'unidades'} disponibles</p>
                                    <div className="mt-5 space-y-2 border-t border-slate-100 pt-4 text-sm">
                                        <p className="flex justify-between text-slate-500"><span>Costo promedio</span><strong className="text-slate-800">{money.format(Number(existencia.precio_costo || 0))}</strong></p>
                                        <p className="flex items-center justify-between text-slate-500"><span>Actualizado</span><strong className="inline-flex items-center gap-1 text-xs text-slate-800"><CalendarClock className="h-3.5 w-3.5" />{existencia.ultima_actualizacion ? new Date(existencia.ultima_actualizacion).toLocaleDateString('es-PE') : '—'}</strong></p>
                                    </div>
                                </>
                            ) : (
                                <div className="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                    Este producto aún no cuenta con una entrada registrada en tu sucursal.
                                </div>
                            )}
                        </div>

                        <div className="rounded-2xl bg-white p-6 lg:col-span-2 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                            <div className="flex items-center gap-2 text-slate-800"><CircleDollarSign className="h-5 w-5 text-brand-ink" /><h2 className="font-semibold">Presentaciones y precios sugeridos</h2></div>
                            {producto.presentaciones?.length ? (
                                <div className="mt-5 overflow-x-auto">
                                    <table className="w-full min-w-[520px] text-left text-sm">
                                        <thead className="border-b border-slate-100 text-xs font-semibold text-slate-400"><tr><th className="pb-3 font-medium">Presentación</th><th className="pb-3 text-right font-medium">Equivalencia</th><th className="pb-3 text-right font-medium">Precio de venta</th></tr></thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {producto.presentaciones.map((item) => (
                                                <tr key={item.id_precio}>
                                                    <td className="py-2.5 font-bold text-slate-800">{item.presentacion?.nombre || 'Presentación'}</td>
                                                    <td className="py-2.5 text-right text-slate-600">{decimal.format(Number(item.factor_conversion || 1))} {producto.unidad_base?.nombre || 'unidad(es)'}</td>
                                                    <td className="py-2.5 text-right font-semibold text-slate-900">{money.format(Number(item.precio_venta || 0))}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <div className="mt-5 rounded-lg border border-dashed border-slate-200 p-5 text-sm text-slate-500">Aún no se configuraron presentaciones ni precios sugeridos.</div>
                            )}
                        </div>
                    </section>
                )}

                {esServicio && (
                    <section className="rounded-xl border border-brand-border bg-brand-soft p-6 text-sm text-brand-ink">
                        <div className="flex items-center gap-2 font-semibold"><ReceiptText className="h-5 w-5" />Este ítem es un servicio</div>
                        <p className="mt-2 text-brand-ink">No requiere entradas, stock ni movimientos de Kardex.</p>
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
