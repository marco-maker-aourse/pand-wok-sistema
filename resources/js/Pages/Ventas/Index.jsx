import React, { useState, useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, Dialog, PageHeader, confirmar, toast, PaginacionServidor } from '@/Components/ui';
import useImprimirTicket from '@/hooks/useImprimirTicket';
import { Head, Link, router } from '@inertiajs/react';
import DialogoAperturaCaja from '@/Components/Caja/DialogoAperturaCaja';
import ComprobanteTermico from '@/Components/Comprobantes/ComprobanteTermico';
import axios from 'axios';
import {
    FileText,
    Plus,
    Search,
    X,
    Eye,
    Calendar,
    User,
    CheckCircle2,
    DollarSign,
    Layers,
    Loader2,
    Send,
    Download,
    Clock3,
    CircleCheck,
    CircleX,
    TriangleAlert,
    Printer,
} from 'lucide-react';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

const estadosSunat = ['TODOS', 'PENDIENTE', 'ACEPTADO', 'RECHAZADO', 'ERROR'];

const estadoVisual = (estado) => ({
    ACEPTADO: { label: 'Aceptado', className: 'bg-emerald-50 text-emerald-700 border-emerald-200', Icon: CircleCheck },
    RECHAZADO: { label: 'Rechazado', className: 'bg-rose-50 text-rose-700 border-rose-200', Icon: CircleX },
    ERROR: { label: 'Con error', className: 'bg-rose-50 text-rose-700 border-rose-200', Icon: TriangleAlert },
    PENDIENTE: { label: 'Pendiente', className: 'bg-amber-50 text-amber-700 border-amber-200', Icon: Clock3 },
    REGISTRADO: { label: 'Pendiente', className: 'bg-amber-50 text-amber-700 border-amber-200', Icon: Clock3 },
}[estado || 'PENDIENTE']);

export default function Index({ auth, ventas = [], turnoAbierto = null, datosEmisor = null, estadoSunat = 'TODOS', terminales = [] }) {
    const [selectedVenta, setSelectedVenta] = useState(null);
    const [busqueda, setBusqueda] = useState('');
    const [filtroTipo, setFiltroTipo] = useState('TODOS');
    const [filtroEstado, setFiltroEstado] = useState(estadoSunat);
    const [mostrarAperturaCaja, setMostrarAperturaCaja] = useState(false);
    const [emitiendoVentaId, setEmitiendoVentaId] = useState(null);

    const listaVentas = Array.isArray(ventas) ? ventas : ventas?.data || [];

    const ventasFiltradas = useMemo(() => {
        return listaVentas.filter((v) => {
            const matchesText =
                (v.nombre_razon_social || '').toLowerCase().includes(busqueda.toLowerCase()) ||
                (v.num_documento || '').includes(busqueda) ||
                `${v.serie}-${v.correlativo}`.toLowerCase().includes(busqueda.toLowerCase());

            const matchesTipo =
                filtroTipo === 'TODOS' || (v.tipo_comprobante || '').toUpperCase() === filtroTipo;

            const matchesEstado = filtroEstado === 'TODOS' || (v.estado_sunat || 'PENDIENTE') === filtroEstado;

            return matchesText && matchesTipo && matchesEstado;
        });
    }, [listaVentas, busqueda, filtroTipo, filtroEstado]);

    const cambiarFiltroEstado = (estado) => {
        setFiltroEstado(estado);
        router.get('/ventas', estado === 'TODOS' ? {} : { estado_sunat: estado }, {
            preserveState: true,
            replace: true,
            only: ['ventas', 'estadoSunat'],
        });
    };

    const openDetails = (venta) => setSelectedVenta(venta);
    const closeDetails = () => setSelectedVenta(null);

    const emitirEnBeta = async () => {
        const estadoActual = selectedVenta?.estado_sunat || 'PENDIENTE';
        if (!selectedVenta || !['PENDIENTE', 'ERROR', 'REGISTRADO'].includes(estadoActual)) {
            return;
        }

        const confirmado = await confirmar({
            tono: 'warning',
            titulo: 'Enviar comprobante a SUNAT Beta',
            mensaje: `Se enviará ${selectedVenta.serie}-${String(selectedVenta.correlativo).padStart(8, '0')} al entorno de pruebas de SUNAT.`,
            confirmar: 'Enviar a Beta',
        });

        if (!confirmado) return;

        setEmitiendoVentaId(selectedVenta.id_venta);
        try {
            const { data: result } = await axios.post('/emitir', { id_venta: selectedVenta.id_venta });
            const estado = result.estado || 'ERROR';

            setSelectedVenta((current) => current?.id_venta === selectedVenta.id_venta
                ? { ...current, estado_sunat: estado, mensaje_sunat: result.mensaje || '' }
                : current);

            router.reload({
                only: ['ventas'],
                onSuccess: (page) => {
                    const rows = Array.isArray(page.props.ventas)
                        ? page.props.ventas
                        : page.props.ventas?.data || [];
                    const updated = rows.find((venta) => String(venta.id_venta) === String(selectedVenta.id_venta));
                    // El motivo de SUNAT no se persiste en la venta: se conserva el de esta respuesta.
                    if (updated) setSelectedVenta((current) => String(current?.id_venta) === String(updated.id_venta) ? { ...updated, mensaje_sunat: current.mensaje_sunat } : current);
                },
            });

            (result.success ? toast.success : toast.warning)(
                result.success ? 'Comprobante aceptado' : `Estado: ${estado}`,
                result.mensaje || 'SUNAT procesó el comprobante.',
            );
        } catch (error) {
            toast.error('No se pudo enviar el comprobante', error.response?.data?.message || 'Revisa la conexión y la configuración de SUNAT.');
        } finally {
            setEmitiendoVentaId(null);
        }
    };

    const { imprimir: imprimirTicket, imprimiendo: imprimiendoTicket } = useImprimirTicket();

    return (
        <AuthenticatedLayout header="Ventas">
            <Head title="Historial de comprobantes" />

            <div className="space-y-4">
                <PageHeader
                    breadcrumbs={[{ label: 'Operaciones' }, { label: 'Ventas' }]}
                    title="Ventas"
                    description="Auditoría, consulta e impresión de comprobantes electrónicos emitidos."
                    actions={
                        <>
                        {turnoAbierto ? (
                            <Link
                                href="/ventas/create"
                                className="volumen-marca text-on-brand h-11 px-5 rounded-lg font-semibold text-xs transition flex items-center gap-2 cursor-pointer hover:brightness-105"
                            >
                                <Plus className="w-4 h-4 stroke-[3]" />
                                <span>Ir al punto de venta</span>
                            </Link>
                        ) : (
                            <button
                                type="button"
                                onClick={() => setMostrarAperturaCaja(true)}
                                className="volumen-marca text-on-brand h-11 px-5 rounded-lg font-semibold text-xs transition flex items-center gap-2 cursor-pointer hover:brightness-105"
                            >
                                <Plus className="w-4 h-4 stroke-[3]" />
                                <span>Abrir caja POS</span>
                            </button>
                        )}
                        </>
                    }
                />

                {/* Filtros y Buscador */}
                <div className="bg-white p-4 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-3 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="relative w-full sm:w-80">
                        <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-3 pointer-events-none" />
                        <input
                            type="text"
                            placeholder="Buscar por cliente, documento o serie..."
                            className="w-full pl-10 pr-3.5 py-2 text-sm bg-white border border-slate-200 rounded-lg transition"
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                        />
                    </div>

                    <div className="flex items-center gap-1.5 w-full sm:w-auto bg-slate-100/80 p-1 rounded-lg">
                        {['TODOS', 'BOLETA', 'FACTURA'].map((tipo) => (
                            <button
                                key={tipo}
                                type="button"
                                onClick={() => setFiltroTipo(tipo)}
                                className={`px-3 py-1.5 rounded-xl text-xs font-bold transition ${
                                    filtroTipo === tipo
                                        ? 'bg-white text-slate-900'
                                        : 'text-slate-500 hover:text-slate-800'
                                }`}
                            >
                                {tipo}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 px-1">
                    <span className="text-xs font-semibold text-slate-400 mr-1">Estado SUNAT</span>
                    {estadosSunat.map((estado) => (
                        <button
                            key={estado}
                            type="button"
                            onClick={() => cambiarFiltroEstado(estado)}
                            className={`rounded-xl border px-3 py-1.5 text-xs font-semibold transition ${
                                filtroEstado === estado
                                    ? 'border-slate-800 bg-slate-900 text-white'
                                    : 'border-slate-200 bg-white text-slate-500 hover:border-slate-400 hover:text-slate-800'
                            }`}
                        >
                            {estado === 'TODOS' ? 'Todos' : estado}
                        </button>
                    ))}
                </div>

                {/* Tabla de Comprobantes */}
                <div className="bg-white rounded-2xl overflow-hidden shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    {/* Celular: una tarjeta por comprobante, se abre con un toque. */}
                    <ul className="divide-y divide-slate-100 sm:hidden">
                        {ventasFiltradas.length > 0 ? ventasFiltradas.map((v) => {
                            const estado = estadoVisual(v.estado_sunat);
                            const EstadoIcon = estado.Icon;
                            return (
                                <li key={v.id_venta}>
                                    <button type="button" onClick={() => openDetails(v)} className="flex w-full items-center gap-3 px-4 py-3 text-left active:bg-slate-50">
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-mono text-sm font-semibold text-slate-900">{v.serie}-{String(v.correlativo || 1).padStart(8, '0')}</span>
                                            <span className="block truncate text-xs text-slate-500">{v.nombre_razon_social || 'PÚBLICO EN GENERAL'}</span>
                                            <span className="block text-xs text-slate-400">{v.fecha_emision ? new Date(v.fecha_emision).toLocaleString('es-PE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : 'Reciente'}</span>
                                        </span>
                                        <span className="flex shrink-0 flex-col items-end gap-1">
                                            <span className="font-mono text-sm font-semibold text-slate-900">{currency.format(Number(v.total_venta || 0))}</span>
                                            <span className={`inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs font-semibold ${estado.className}`}><EstadoIcon className="h-3 w-3" />{estado.label}</span>
                                        </span>
                                    </button>
                                </li>
                            );
                        }) : (
                            <li className="px-4 py-12 text-center text-sm text-slate-500">No se encontraron comprobantes emitidos.</li>
                        )}
                    </ul>
                    <div className="hidden overflow-x-auto sm:block">
                        <table className="w-full text-left whitespace-nowrap">
                            <thead className="bg-slate-50/70 border-b border-slate-100">
                                <tr>
                                    <th className="px-4 py-2.5 text-xs font-semibold text-slate-400">
                                        Fecha / Hora
                                    </th>
                                    <th className="px-4 py-2.5 text-xs font-semibold text-slate-400">
                                        Comprobante
                                    </th>
                                    <th className="px-4 py-2.5 text-xs font-semibold text-slate-400">
                                        Cliente
                                    </th>
                                    <th className="px-4 py-2.5 text-xs font-semibold text-slate-400 text-right">
                                        Total Cobrado
                                    </th>
                                    <th className="px-4 py-2.5 text-xs font-semibold text-slate-400 text-center">
                                        Estado SUNAT
                                    </th>
                                    <th className="px-4 py-2.5 text-xs font-semibold text-slate-400 text-center">
                                        Acciones
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-semibold text-slate-700">
                                {ventasFiltradas.length > 0 ? (
                                    ventasFiltradas.map((v) => {
                                        const esBoleta = v.tipo_comprobante === 'BOLETA';
                                        const estado = estadoVisual(v.estado_sunat);
                                        const EstadoIcon = estado.Icon;
                                        return (
                                            <tr key={v.id_venta} className="hover:bg-brand-soft/40 transition-colors">
                                                <td className="px-4 py-2.5 text-xs font-bold text-slate-700">
                                                    {v.fecha_emision
                                                        ? new Date(v.fecha_emision).toLocaleString('es-PE', {
                                                              day: '2-digit',
                                                              month: '2-digit',
                                                              year: 'numeric',
                                                              hour: '2-digit',
                                                              minute: '2-digit'
                                                          })
                                                        : 'Reciente'}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className={`px-2 py-0.5 rounded-md text-xs font-semibold ${
                                                                esBoleta
                                                                    ? 'bg-slate-100 text-slate-700 border border-slate-200'
                                                                    : 'bg-brand-soft text-brand-ink border border-brand-border'
                                                            }`}
                                                        >
                                                            {v.tipo_comprobante}
                                                        </span>
                                                        <span className="font-mono font-semibold text-slate-900 text-xs">
                                                            {v.serie}-{String(v.correlativo || 1).padStart(8, '0')}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <p className="font-bold text-xs text-slate-800 truncate max-w-xs">
                                                        {v.nombre_razon_social || 'PÚBLICO EN GENERAL'}
                                                    </p>
                                                    <span className="text-xs text-slate-400 font-mono">
                                                        {v.num_documento || '00000000'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2.5 text-right font-semibold text-slate-900 text-sm font-mono">
                                                    {currency.format(Number(v.total_venta || 0))}
                                                </td>
                                                <td className="px-4 py-2.5 text-center">
                                                    <span className={`inline-flex items-center gap-1 rounded-lg border px-2 py-1 text-xs font-semibold ${estado.className}`}>
                                                        <EstadoIcon className="h-3 w-3" />
                                                        {estado.label}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2.5 text-center">
                                                    <button
                                                        type="button"
                                                        onClick={() => openDetails(v)}
                                                        className="h-8 px-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 font-bold text-xs inline-flex items-center gap-1.5 transition cursor-pointer"
                                                        title="Ver comprobante completo"
                                                    >
                                                        <Eye className="w-3.5 h-3.5" />
                                                        <span>Ver</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan="6" className="py-16 text-center text-slate-400">
                                            <p className="font-bold text-xs text-slate-500">
                                                No se encontraron comprobantes emitidos
                                            </p>
                                            <p className="text-xs text-slate-400 mt-0.5">
                                                Ingresa a "Abrir Caja POS" para emitir el primer comprobante.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <PaginacionServidor paginador={ventas} entidad="comprobantes" />
                </div>
                <DialogoAperturaCaja
                    isOpen={mostrarAperturaCaja}
                    onClose={() => setMostrarAperturaCaja(false)}
                    terminales={terminales}
                />
            </div>

            {/* MODAL PROPIO: DETALLE DE COMPROBANTE EMITIDO */}
            <Dialog open={Boolean(selectedVenta)} onClose={closeDetails} size="3xl">
                {(selectedVenta) && (
                <div className="p-5 sm:p-7">
                        <div className="flex items-start justify-between pb-4 border-b border-slate-100 mb-5">
                            <div>
                                <div className="flex items-center gap-2 mb-1.5">
                                    <span className="px-2.5 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-800">
                                        {selectedVenta.tipo_comprobante} ELECTRÓNICA
                                    </span>
                                    <span className={`px-2 py-0.5 rounded-md text-xs font-semibold ${
                                        selectedVenta.estado_sunat === 'ACEPTADO'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : selectedVenta.estado_sunat === 'RECHAZADO'
                                                ? 'bg-rose-50 text-rose-700'
                                                : 'bg-amber-50 text-amber-700'
                                    }`}>
                                        {selectedVenta.estado_sunat || 'REGISTRADO'}
                                    </span>
                                </div>
                                <h3 className="text-xl font-semibold font-mono text-slate-900 tracking-tight">
                                    {selectedVenta.serie}-{String(selectedVenta.correlativo || 1).padStart(8, '0')}
                                </h3>
                                <p className="text-xs text-slate-500 font-medium mt-0.5">
                                    Cliente: <strong className="text-slate-800">{selectedVenta.nombre_razon_social}</strong> ({selectedVenta.num_documento})
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={closeDetails}
                                className="w-8 h-8 rounded-xl text-slate-400 hover:bg-slate-100 flex items-center justify-center cursor-pointer"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
                            {/* Ticket: los mismos datos, orden y formato que imprime el agente. */}
                            <ComprobanteTermico
                                idVenta={selectedVenta.id_venta}
                                estadoSunat={selectedVenta.estado_sunat || 'PENDIENTE'}
                                mensajeSunat={selectedVenta.mensaje_sunat}
                                acciones={selectedVenta.estado_sunat === 'ACEPTADO' && (
                                    <Button variant="secondary" icon={Printer} loading={imprimiendoTicket} onClick={() => imprimirTicket(selectedVenta.id_venta)}>
                                        Imprimir en la caja
                                    </Button>
                                )}
                            />

                            <div>
                                <section className="rounded-lg border border-slate-200 bg-slate-50 p-3.5">
                                    <div className="mb-2 flex items-center justify-between gap-3">
                                        <div>
                                            <p className="text-xs font-semibold text-slate-500">Trazabilidad fiscal</p>
                                            <p className="mt-0.5 text-xs font-semibold text-slate-600">Archivos generados y respuesta de SUNAT.</p>
                                        </div>
                                        {(() => {
                                            const estado = estadoVisual(selectedVenta.estado_sunat);
                                            const EstadoIcon = estado.Icon;
                                            return <span className={`inline-flex items-center gap-1 rounded-lg border px-2 py-1 text-xs font-semibold uppercase ${estado.className}`}><EstadoIcon className="h-3 w-3" />{estado.label}</span>;
                                        })()}
                                    </div>
                                    {selectedVenta.hash_cpe && (
                                        <p className="mb-3 break-all rounded-xl border border-slate-200 bg-white px-3 py-2 font-mono text-xs text-slate-500">
                                            <strong className="font-sans text-slate-700">Hash CPE: </strong>{selectedVenta.hash_cpe}
                                        </p>
                                    )}
                                    <div className="flex flex-wrap gap-2">
                                        {[
                                            ['xml', 'XML firmado'],
                                            ['cdr', 'CDR SUNAT'],
                                            ['pdf', 'PDF'],
                                        ].filter(([type]) => selectedVenta.archivos?.[type]).map(([type, label]) => (
                                            <a
                                                key={type}
                                                href={`/ventas/${selectedVenta.id_venta}/archivo/${type}`}
                                                className="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-100 hover:border-brand-border hover:bg-brand-soft/40"
                                            >
                                                <Download className="h-3.5 w-3.5" /> {label}
                                            </a>
                                        ))}
                                        {!selectedVenta.archivos?.xml && !selectedVenta.archivos?.cdr && !selectedVenta.archivos?.pdf && (
                                            <p className="text-xs font-semibold text-slate-400">No hay archivos fiscales disponibles todavía.</p>
                                        )}
                                        {selectedVenta.estado_sunat === 'ACEPTADO' && !selectedVenta.archivos?.pdf && (
                                            <a
                                                href={`/ventas/${selectedVenta.id_venta}/archivo/pdf`}
                                                className="inline-flex h-9 items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-100 hover:border-brand-border hover:bg-brand-soft/40"
                                            >
                                                <Download className="h-3.5 w-3.5" /> Generar PDF
                                            </a>
                                        )}
                                    </div>
                                </section>
                            </div>
                        </div>

                        <div className="mt-5 flex justify-end gap-2 border-t border-slate-100 pt-4">
                            {['PENDIENTE', 'ERROR', 'REGISTRADO'].includes(selectedVenta.estado_sunat || 'PENDIENTE') && (
                                <button
                                    type="button"
                                    onClick={emitirEnBeta}
                                    disabled={emitiendoVentaId === selectedVenta.id_venta}
                                    className="volumen-marca h-10 px-4 rounded-xl text-on-brand font-bold text-xs flex items-center justify-center gap-1.5 transition disabled:opacity-60 cursor-pointer hover:brightness-105"
                                >
                                    {emitiendoVentaId === selectedVenta.id_venta
                                        ? <Loader2 className="w-4 h-4 animate-spin" />
                                        : <Send className="w-4 h-4" />}
                                    <span>{emitiendoVentaId === selectedVenta.id_venta ? 'Enviando…' : 'Enviar a SUNAT Beta'}</span>
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={closeDetails}
                                className="volumen-marca h-10 px-5 rounded-xl text-on-brand font-bold text-xs transition cursor-pointer hover:brightness-105"
                            >
                                Cerrar
                            </button>
                        </div>
                </div>
                )}
            </Dialog>
        </AuthenticatedLayout>
    );
}
