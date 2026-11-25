import { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { ChevronRight, PackagePlus } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge, Button, Card, Dialog, EmptyState, PageHeader, PaginacionServidor, SearchInput, Table, TBody, Td, Th, THead, Tr, cx } from '@/Components/ui';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const fecha = (iso) => (iso ? new Date(iso).toLocaleDateString('es-PE') : '—');

const FILTROS = [
    { id: 'todos', label: 'Todos' },
    { id: 'factura', label: 'Facturas' },
    { id: 'boleta', label: 'Boletas' },
    { id: 'guia', label: 'Guías' },
];

const proveedorDe = (ing) => ing.proveedor?.nombre_razon_social || ing.proveedor?.razon_social || ing.proveedor?.nombre || 'Proveedor';
const rucDe = (ing) => ing.proveedor?.num_documento || ing.proveedor?.ruc || '';
const tipoDe = (ing) => ing.tipo_comprobante || ing.tipoComprobante?.nombre || 'Factura';
const totalDe = (ing) => Number(ing.total_ingreso || ing.total || 0);
const numeroDe = (ing) => `${ing.serie_comprobante || ''}-${ing.num_comprobante || ''}`;

function DetalleEntrada({ ingreso, onCerrar }) {
    const detalles = ingreso.detalles || [];
    return (
        <Dialog open onClose={onCerrar} size="2xl" title={<span className="font-mono">{numeroDe(ingreso)}</span>}
            description={`${tipoDe(ingreso)} · ${proveedorDe(ingreso)}${rucDe(ingreso) ? ` · RUC ${rucDe(ingreso)}` : ''} · ${fecha(ingreso.fecha_emision)}`}
            footer={<Button onClick={onCerrar}>Cerrar</Button>}>
            {detalles.length === 0 ? (
                <p className="px-5 py-8 text-center text-sm text-slate-500">Esta entrada no tiene detalle cargado.</p>
            ) : (
                <Table caption="Productos de la entrada">
                    <THead><Th>Producto</Th><Th>Presentación</Th><Th align="right">Cantidad</Th><Th align="right">Costo</Th><Th align="right">Subtotal</Th></THead>
                    <TBody>
                        {detalles.map((d, i) => {
                            const cantidad = Number(d.cantidad || 0);
                            const costo = Number(d.precio_costo || d.costo_unitario || 0);
                            return (
                                <Tr key={d.id_detalle_ingreso ?? i}>
                                    <Td className="font-medium text-slate-900">{d.producto?.nombre || 'Producto'}</Td>
                                    <Td className="text-slate-600">{d.presentacion?.presentacion?.nombre || d.presentacion?.nombre || 'Unidad base'}</Td>
                                    <Td align="right" className="font-mono tabular-nums">+{cantidad}</Td>
                                    <Td align="right" className="font-mono tabular-nums">{currency.format(costo)}</Td>
                                    <Td align="right" className="font-mono font-semibold tabular-nums">{currency.format(Number(d.subtotal || cantidad * costo))}</Td>
                                </Tr>
                            );
                        })}
                    </TBody>
                </Table>
            )}
            <div className="flex items-baseline justify-between border-t border-slate-100 px-5 py-3">
                <span className="text-sm text-slate-600">Total</span>
                <span className="font-mono text-xl font-semibold text-slate-900 tabular-nums">{currency.format(totalDe(ingreso))}</span>
            </div>
        </Dialog>
    );
}

/** Historial de entradas de insumos y mercadería (compras a proveedores). */
export default function Index({ ingresos = [] }) {
    const [busqueda, setBusqueda] = useState('');
    const [filtro, setFiltro] = useState('todos');
    const [seleccionado, setSeleccionado] = useState(null);
    const lista = useMemo(() => (Array.isArray(ingresos) ? ingresos : ingresos?.data ?? []), [ingresos]);

    const filtrados = useMemo(() => {
        const q = busqueda.toLowerCase().trim();
        return lista.filter((ing) => {
            if (q && !proveedorDe(ing).toLowerCase().includes(q) && !rucDe(ing).includes(q) && !numeroDe(ing).toLowerCase().includes(q)) return false;
            const tipo = tipoDe(ing).toLowerCase();
            if (filtro === 'factura') return tipo.includes('factura');
            if (filtro === 'boleta') return tipo.includes('boleta');
            if (filtro === 'guia') return tipo.includes('guia') || tipo.includes('remision');
            return true;
        });
    }, [lista, busqueda, filtro]);

    const total = Number(ingresos?.total ?? lista.length);

    return (
        <AuthenticatedLayout header="Entradas">
            <Head title="Entradas de insumos" />
            <div className="space-y-4">
                <PageHeader
                    title="Entradas de insumos"
                    description={`Compras a proveedores que suman stock con su costo y lote. ${total.toLocaleString('es-PE')} registradas.`}
                    icon={PackagePlus}
                    actions={<Button icon={PackagePlus} href="/ingresos/create">Registrar entrada</Button>}
                />

                <Card className="overflow-hidden">
                    <div className="flex flex-col gap-3 border-b border-slate-100 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                        <SearchInput value={busqueda} onChange={setBusqueda} placeholder="Proveedor, RUC o número…" label="Buscar en esta página" className="sm:w-80" />
                        <div className="flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1" role="radiogroup" aria-label="Tipo de comprobante">
                            {FILTROS.map(({ id, label }) => (
                                <button key={id} type="button" role="radio" aria-checked={filtro === id} onClick={() => setFiltro(id)}
                                    className={cx('h-8 shrink-0 rounded-md px-3 text-xs font-medium', filtro === id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900')}>
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {filtrados.length === 0 ? (
                        <EmptyState icon={PackagePlus} title={lista.length ? 'Ninguna entrada coincide' : 'Aún no hay entradas'} description={lista.length ? 'Prueba con otra búsqueda o tipo.' : 'Registra la primera compra de insumos.'}
                            action={!lista.length && <Button icon={PackagePlus} href="/ingresos/create">Registrar entrada</Button>} />
                    ) : (
                        <>
                            <ul className="divide-y divide-slate-100 md:hidden">
                                {filtrados.map((ing) => (
                                    <li key={ing.id_ingreso}>
                                        <button type="button" onClick={() => setSeleccionado(ing)} className="flex w-full items-center gap-3 px-4 py-3 text-left active:bg-slate-50">
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium text-slate-900">{proveedorDe(ing)}</p>
                                                <p className="text-xs text-slate-500"><span className="font-mono">{numeroDe(ing)}</span> · {fecha(ing.fecha_emision)}</p>
                                            </div>
                                            <span className="font-mono text-sm font-semibold tabular-nums">{currency.format(totalDe(ing))}</span>
                                            <ChevronRight className="h-4 w-4 text-slate-400" aria-hidden="true" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                            <Table className="hidden md:block" caption="Entradas de insumos">
                                <THead><Th>Fecha</Th><Th>Comprobante</Th><Th>Proveedor</Th><Th align="right">Total</Th><Th>Estado</Th><Th align="right"><span className="sr-only">Acciones</span></Th></THead>
                                <TBody>
                                    {filtrados.map((ing) => (
                                        <Tr key={ing.id_ingreso} className="cursor-pointer" onClick={() => setSeleccionado(ing)}>
                                            <Td className="whitespace-nowrap tabular-nums">{fecha(ing.fecha_emision)}</Td>
                                            <Td className="whitespace-nowrap"><Badge>{tipoDe(ing)}</Badge> <span className="ml-1 font-mono text-xs font-semibold text-slate-900">{numeroDe(ing)}</span></Td>
                                            <Td>
                                                <p className="max-w-xs truncate font-medium text-slate-800">{proveedorDe(ing)}</p>
                                                {rucDe(ing) && <p className="font-mono text-xs text-slate-500">{rucDe(ing)}</p>}
                                            </Td>
                                            <Td align="right" className="font-mono font-semibold tabular-nums">{currency.format(totalDe(ing))}</Td>
                                            <Td><Badge tone="success" dot>{ing.estado || 'Ingresado'}</Badge></Td>
                                            <Td align="right"><Button variant="ghost" size="sm" onClick={(e) => { e.stopPropagation(); setSeleccionado(ing); }}>Ver</Button></Td>
                                        </Tr>
                                    ))}
                                </TBody>
                            </Table>
                        </>
                    )}
                    <PaginacionServidor paginador={ingresos} entidad="entradas" />
                </Card>
            </div>
            {seleccionado && <DetalleEntrada ingreso={seleccionado} onCerrar={() => setSeleccionado(null)} />}
        </AuthenticatedLayout>
    );
}
