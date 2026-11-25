import { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import { ChevronRight, PackageMinus } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge, Button, Card, Dialog, EmptyState, PageHeader, PaginacionServidor, SearchInput, Table, TBody, Td, Th, THead, Tr, cx } from '@/Components/ui';

const FILTROS = [
    { id: 'todos', label: 'Todos' },
    { id: 'merma', label: 'Merma', coincide: ['merma'] },
    { id: 'vencimiento', label: 'Vencimiento', coincide: ['vencimiento'] },
    { id: 'rotura', label: 'Deterioro', coincide: ['rotura', 'deterioro'] },
    { id: 'ajuste', label: 'Ajuste', coincide: ['ajuste'] },
];

const fechaHora = (iso) => (iso ? new Date(iso).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' }) : '—');
const usuarioDe = (s) => s.usuario?.nombres || s.usuario?.name || '';
const motivoLegible = (m = '') => m.charAt(0) + m.slice(1).toLowerCase();

function tonoMotivo(motivo = '') {
    const m = motivo.toUpperCase();
    if (m.includes('VENCIMIENTO')) return 'danger';
    if (m.includes('MERMA') || m.includes('ROTURA') || m.includes('DETERIORO')) return 'warning';
    if (m.includes('AJUSTE')) return 'brand';
    return 'neutral';
}

function DetalleSalida({ salida, onCerrar }) {
    const detalles = salida.detalles || [];
    return (
        <Dialog open onClose={onCerrar} size="xl" title={`Salida #${salida.id_salida}`}
            description={[motivoLegible(salida.motivo), fechaHora(salida.fecha_salida), usuarioDe(salida)].filter(Boolean).join(' · ')}
            footer={<Button onClick={onCerrar}>Cerrar</Button>}>
            {salida.observacion && <p className="mx-5 mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-700 italic ring-1 ring-slate-200">“{salida.observacion}”</p>}
            {detalles.length === 0 ? (
                <p className="px-5 py-8 text-center text-sm text-slate-500">Esta salida no tiene detalle cargado.</p>
            ) : (
                <Table className="mt-2" caption="Productos dados de baja">
                    <THead><Th>Producto</Th><Th>Presentación</Th><Th>Lote</Th><Th align="right">Cantidad</Th></THead>
                    <TBody>
                        {detalles.map((d, i) => (
                            <Tr key={d.id_detalle_salida ?? i}>
                                <Td className="font-medium text-slate-900">{d.producto?.nombre || d.presentacion?.producto?.nombre || 'Producto'}</Td>
                                <Td className="text-slate-600">{d.presentacion?.presentacion?.nombre || d.presentacion?.nombre || 'Unidad base'}</Td>
                                <Td className="font-mono text-xs">{d.lote || '—'}</Td>
                                <Td align="right" className="font-mono font-semibold text-rose-700 tabular-nums">−{Number(d.cantidad || 0)}</Td>
                            </Tr>
                        ))}
                    </TBody>
                </Table>
            )}
        </Dialog>
    );
}

/** Historial de bajas de inventario: mermas, vencimientos, deterioros y ajustes. */
export default function Index({ salidas = [] }) {
    const [busqueda, setBusqueda] = useState('');
    const [filtro, setFiltro] = useState('todos');
    const [seleccionada, setSeleccionada] = useState(null);
    const lista = useMemo(() => (Array.isArray(salidas) ? salidas : salidas?.data ?? []), [salidas]);

    const filtradas = useMemo(() => {
        const q = busqueda.toLowerCase().trim();
        const criterio = FILTROS.find((f) => f.id === filtro)?.coincide;
        return lista.filter((s) => {
            const motivo = (s.motivo || '').toLowerCase();
            if (q && !motivo.includes(q) && !(s.observacion || '').toLowerCase().includes(q) && !`#${s.id_salida}`.includes(q)) return false;
            return !criterio || criterio.some((palabra) => motivo.includes(palabra));
        });
    }, [lista, busqueda, filtro]);

    return (
        <AuthenticatedLayout header="Salidas y ajustes">
            <Head title="Salidas y ajustes" />
            <div className="space-y-4">
                <PageHeader
                    title="Salidas y ajustes"
                    description="Bajas de inventario con su motivo y justificación. Las mermas de cocina se registran desde Mermas."
                    icon={PackageMinus}
                    actions={<Button icon={PackageMinus} href="/salidas/create">Registrar salida</Button>}
                />

                <Card className="overflow-hidden">
                    <div className="flex flex-col gap-3 border-b border-slate-100 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                        <SearchInput value={busqueda} onChange={setBusqueda} placeholder="Motivo, justificación o número…" label="Buscar en esta página" className="sm:w-80" />
                        <div className="flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1" role="radiogroup" aria-label="Motivo">
                            {FILTROS.map(({ id, label }) => (
                                <button key={id} type="button" role="radio" aria-checked={filtro === id} onClick={() => setFiltro(id)}
                                    className={cx('h-8 shrink-0 rounded-md px-3 text-xs font-medium', filtro === id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900')}>
                                    {label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {filtradas.length === 0 ? (
                        <EmptyState icon={PackageMinus} title={lista.length ? 'Ninguna salida coincide' : 'Aún no hay salidas'} description={lista.length ? 'Prueba con otra búsqueda o motivo.' : 'Las bajas de inventario aparecerán aquí.'} />
                    ) : (
                        <>
                            <ul className="divide-y divide-slate-100 md:hidden">
                                {filtradas.map((s) => (
                                    <li key={s.id_salida}>
                                        <button type="button" onClick={() => setSeleccionada(s)} className="flex w-full items-center gap-3 px-4 py-3 text-left active:bg-slate-50">
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <div className="flex items-center gap-2"><Badge tone={tonoMotivo(s.motivo)}>{motivoLegible(s.motivo)}</Badge><span className="text-xs text-slate-500">#{s.id_salida}</span></div>
                                                <p className="truncate text-sm text-slate-700">{s.observacion || 'Sin justificación'}</p>
                                                <p className="text-xs text-slate-500">{fechaHora(s.fecha_salida)}</p>
                                            </div>
                                            <ChevronRight className="h-4 w-4 text-slate-400" aria-hidden="true" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                            <Table className="hidden md:block" caption="Salidas de inventario">
                                <THead><Th>Fecha</Th><Th>N.º</Th><Th>Motivo</Th><Th>Justificación</Th><Th>Registró</Th><Th align="right"><span className="sr-only">Acciones</span></Th></THead>
                                <TBody>
                                    {filtradas.map((s) => (
                                        <Tr key={s.id_salida} className="cursor-pointer" onClick={() => setSeleccionada(s)}>
                                            <Td className="whitespace-nowrap tabular-nums">{fechaHora(s.fecha_salida)}</Td>
                                            <Td className="font-mono text-xs">#{s.id_salida}</Td>
                                            <Td><Badge tone={tonoMotivo(s.motivo)}>{motivoLegible(s.motivo)}</Badge></Td>
                                            <Td><p className="max-w-sm truncate text-slate-700">{s.observacion || '—'}</p></Td>
                                            <Td className="text-slate-600">{usuarioDe(s) || '—'}</Td>
                                            <Td align="right"><Button variant="ghost" size="sm" onClick={(e) => { e.stopPropagation(); setSeleccionada(s); }}>Ver</Button></Td>
                                        </Tr>
                                    ))}
                                </TBody>
                            </Table>
                        </>
                    )}
                    <PaginacionServidor paginador={salidas} entidad="salidas" />
                </Card>
            </div>
            {seleccionada && <DetalleSalida salida={seleccionada} onCerrar={() => setSeleccionada(null)} />}
        </AuthenticatedLayout>
    );
}
