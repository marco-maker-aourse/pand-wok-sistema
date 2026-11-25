import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { ChevronRight, CircleCheck, CircleX, Clock3, Download, FileMinus, MonitorSmartphone, Printer, ReceiptText, Send, TriangleAlert, WalletCards } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge, Button, buttonClasses, Card, Dialog, EmptyState, PageHeader, PaginacionServidor, SearchInput, Table, TBody, Td, Th, THead, Tr, confirmar, cx, toast } from '@/Components/ui';
import DialogoAperturaCaja from '@/Components/Caja/DialogoAperturaCaja';
import ComprobanteTermico from '@/Components/Comprobantes/ComprobanteTermico';
import useImprimirTicket from '@/hooks/useImprimirTicket';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const fechaHora = new Intl.DateTimeFormat('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const TIPOS = ['TODOS', 'BOLETA', 'FACTURA', 'NOTA_VENTA'];
const NOMBRE_TIPO = { BOLETA: 'Boleta', FACTURA: 'Factura', NOTA_VENTA: 'Nota de venta' };
const ESTADOS_SUNAT = ['TODOS', 'PENDIENTE', 'ACEPTADO', 'RECHAZADO', 'ERROR', 'NO_APLICA'];
/** Estados en los que el comprobante todavía puede enviarse a SUNAT. */
const POR_ENVIAR = ['PENDIENTE', 'ERROR', 'REGISTRADO'];
/** Igual que Venta::imprimible(): aceptado por SUNAT o nota de venta (no va a SUNAT). */
const IMPRIMIBLE = ['ACEPTADO', 'NO_APLICA'];

const ESTADO = {
    ACEPTADO: { label: 'Aceptado', tone: 'success', icon: CircleCheck },
    RECHAZADO: { label: 'Rechazado', tone: 'danger', icon: CircleX },
    ERROR: { label: 'Con error', tone: 'danger', icon: TriangleAlert },
    PENDIENTE: { label: 'Pendiente', tone: 'warning', icon: Clock3 },
    REGISTRADO: { label: 'Pendiente', tone: 'warning', icon: Clock3 },
    NO_APLICA: { label: 'No va a SUNAT', tone: 'neutral', icon: FileMinus },
};
const estadoDe = (venta) => ESTADO[venta?.estado_sunat || 'PENDIENTE'] ?? ESTADO.PENDIENTE;
const numero = (venta) => `${venta.serie}-${String(venta.correlativo || 1).padStart(8, '0')}`;
const esNota = (venta) => venta?.tipo_comprobante === 'NOTA_VENTA';

function EstadoSunat({ venta, conMensaje = false }) {
    const estado = estadoDe(venta);
    return (
        <div>
            <Badge tone={estado.tone} icon={estado.icon}>{estado.label}</Badge>
            {conMensaje && venta.mensaje_sunat && !['ACEPTADO', 'NO_APLICA'].includes(venta.estado_sunat) && (
                <p className="mt-1 max-w-[16rem] text-[0.6875rem] leading-snug text-slate-500" title={venta.mensaje_sunat}>
                    {venta.mensaje_sunat.length > 90 ? `${venta.mensaje_sunat.slice(0, 90)}…` : venta.mensaje_sunat}
                </p>
            )}
        </div>
    );
}

function TipoComprobante({ venta }) {
    const tone = venta.tipo_comprobante === 'FACTURA' ? 'brand' : esNota(venta) ? 'warning' : 'neutral';
    return <Badge tone={tone}>{NOMBRE_TIPO[venta.tipo_comprobante] ?? venta.tipo_comprobante}</Badge>;
}

/**
 * Diagnóstico de facturación electrónica: qué falta para que SUNAT acepte (sin mostrar claves).
 * Solo se muestra si hay algo que corregir o comprobantes pendientes.
 */
function DiagnosticoSunat({ diagnostico, pendientes }) {
    if (!diagnostico) return null;
    const fallas = diagnostico.revisiones.filter((r) => !r.ok);
    if (fallas.length === 0 && pendientes === 0) return null;
    const pruebas = diagnostico.entorno === 'beta';
    return (
        <section className={cx('rounded-2xl p-4 ring-1', fallas.length ? 'bg-amber-50 ring-amber-200' : 'bg-white ring-slate-200')} aria-label="Diagnóstico SUNAT">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                    <TriangleAlert className="h-4 w-4 text-amber-600" aria-hidden="true" />
                    {fallas.length ? `SUNAT no podrá aceptar comprobantes: falta ${fallas.length} ${fallas.length === 1 ? 'requisito' : 'requisitos'}` : 'Facturación electrónica configurada'}
                </h2>
                {pendientes > 0 && <Badge tone="warning">{pendientes} pendiente{pendientes === 1 ? '' : 's'} de envío</Badge>}
            </div>
            {pruebas && <p className="mt-1 text-xs text-amber-800">Los comprobantes se envían al entorno de <b>pruebas (beta)</b> de SUNAT: allí «Aceptado» no tiene validez tributaria.</p>}
            {fallas.length > 0 && (
                <ul className="mt-2 grid gap-1.5 sm:grid-cols-2">
                    {fallas.map((r) => (
                        <li key={r.clave} className="rounded-lg bg-white/80 px-3 py-2 text-xs ring-1 ring-amber-200">
                            <span className="block font-semibold text-slate-800">{r.titulo}</span>
                            <span className="text-slate-600">{r.detalle}</span>
                        </li>
                    ))}
                </ul>
            )}
            {fallas.length > 0 && <p className="mt-2 text-xs text-slate-600">Las notas de venta no dependen de SUNAT y se pueden emitir igual.</p>}
        </section>
    );
}

function Segmentos({ etiqueta, opciones, valor, onCambiar, nombre }) {
    return (
        <div className="flex min-w-0 items-center gap-2">
            <span className="hidden shrink-0 text-xs font-medium text-slate-500 lg:inline">{etiqueta}</span>
            <div className="flex gap-1 overflow-x-auto rounded-lg bg-slate-100 p-1 [scrollbar-width:none]" role="radiogroup" aria-label={etiqueta}>
                {opciones.map((opcion) => (
                    <button
                        key={opcion}
                        type="button"
                        role="radio"
                        aria-checked={valor === opcion}
                        onClick={() => onCambiar(opcion)}
                        className={cx('h-8 shrink-0 rounded-md px-3 text-xs font-medium whitespace-nowrap transition-colors', valor === opcion ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900')}
                    >
                        {nombre(opcion)}
                    </button>
                ))}
            </div>
        </div>
    );
}

function DetalleComprobante({ venta, onCerrar, entorno, emitiendo, onEmitir, imprimiendo, onImprimir }) {
    const nota = esNota(venta);
    const archivos = [['xml', 'XML firmado'], ['cdr', 'CDR SUNAT'], ['pdf', 'PDF']].filter(([tipo]) => venta.archivos?.[tipo]);
    const puedeEnviar = !nota && POR_ENVIAR.includes(venta.estado_sunat || 'PENDIENTE');

    return (
        <Dialog
            open
            onClose={onCerrar}
            size="3xl"
            title={<span className="font-mono">{numero(venta)}</span>}
            description={`${nota ? 'Nota de venta · sin valor tributario' : `${NOMBRE_TIPO[venta.tipo_comprobante]} electrónica`} · ${venta.nombre_razon_social || 'Público en general'}`}
            footer={(
                <>
                    {puedeEnviar && (
                        <Button icon={Send} loading={emitiendo} onClick={onEmitir}>
                            {entorno === 'produccion' ? 'Enviar a SUNAT' : 'Enviar a SUNAT (pruebas)'}
                        </Button>
                    )}
                    <Button variant={puedeEnviar ? 'secondary' : 'primary'} onClick={onCerrar}>Cerrar</Button>
                </>
            )}
        >
            <div className="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)] sm:p-6">
                {/* Ticket: los mismos datos, orden y formato que imprime el agente. */}
                <ComprobanteTermico
                    idVenta={venta.id_venta}
                    estadoSunat={venta.estado_sunat || 'PENDIENTE'}
                    mensajeSunat={venta.mensaje_sunat}
                    acciones={IMPRIMIBLE.includes(venta.estado_sunat) && (
                        <Button variant="secondary" icon={Printer} loading={imprimiendo} onClick={() => onImprimir(venta.id_venta)}>Imprimir en la caja</Button>
                    )}
                />

                <div className="space-y-4">
                    <section className="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h3 className="text-sm font-semibold text-slate-900">{nota ? 'Documento interno' : 'Trazabilidad fiscal'}</h3>
                                <p className="mt-0.5 text-xs text-slate-500">{nota ? 'Sin XML, CDR ni envío a SUNAT.' : 'Archivos generados y respuesta de SUNAT.'}</p>
                            </div>
                            <EstadoSunat venta={venta} />
                        </div>

                        {nota ? (
                            <p className="mt-3 text-xs leading-relaxed text-slate-600">
                                La nota de venta respalda la venta ante el cliente y en la caja, pero no tiene valor tributario. Si el cliente pide boleta o factura, debe emitirse un comprobante electrónico.
                            </p>
                        ) : (
                            <>
                                {venta.hash_cpe && (
                                    <p className="mt-3 break-all rounded-lg bg-white px-3 py-2 font-mono text-xs text-slate-500 ring-1 ring-slate-200">
                                        <strong className="font-sans text-slate-700">Hash CPE: </strong>{venta.hash_cpe}
                                    </p>
                                )}
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {archivos.map(([tipo, etiqueta]) => (
                                        <a key={tipo} href={`/ventas/${venta.id_venta}/archivo/${tipo}`} className={buttonClasses({ variant: 'secondary', size: 'sm' })}><Download className="h-3.5 w-3.5" aria-hidden="true" />{etiqueta}</a>
                                    ))}
                                    {venta.estado_sunat === 'ACEPTADO' && !venta.archivos?.pdf && (
                                        <a href={`/ventas/${venta.id_venta}/archivo/pdf`} className={buttonClasses({ variant: 'secondary', size: 'sm' })}><Download className="h-3.5 w-3.5" aria-hidden="true" />Generar PDF</a>
                                    )}
                                    {archivos.length === 0 && venta.estado_sunat !== 'ACEPTADO' && <p className="text-xs text-slate-500">Todavía no hay archivos fiscales.</p>}
                                </div>
                            </>
                        )}
                    </section>

                    <dl className="grid grid-cols-2 gap-3 text-sm">
                        <div className="rounded-xl p-3 ring-1 ring-slate-200">
                            <dt className="text-xs text-slate-500">Emisión</dt>
                            <dd className="mt-0.5 font-medium text-slate-900">{venta.fecha_emision ? fechaHora.format(new Date(venta.fecha_emision)) : '—'}</dd>
                        </div>
                        <div className="rounded-xl p-3 ring-1 ring-slate-200">
                            <dt className="text-xs text-slate-500">Total</dt>
                            <dd className="mt-0.5 font-mono font-semibold text-slate-900 tabular-nums">{currency.format(Number(venta.total_venta || 0))}</dd>
                        </div>
                        <div className="col-span-2 rounded-xl p-3 ring-1 ring-slate-200">
                            <dt className="text-xs text-slate-500">Cliente</dt>
                            <dd className="mt-0.5 font-medium text-slate-900">
                                {venta.nombre_razon_social || 'Público en general'}
                                {venta.num_documento && <span className="ml-2 font-mono text-xs text-slate-500">{venta.num_documento}</span>}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </Dialog>
    );
}

export default function Index({ ventas = [], turnoAbierto = null, estadoSunat = 'TODOS', tipoComprobante = 'TODOS', terminales = [], diagnosticoSunat = null, pendientesSunat = 0 }) {
    const [seleccionada, setSeleccionada] = useState(null);
    const [busqueda, setBusqueda] = useState('');
    const [mostrarAperturaCaja, setMostrarAperturaCaja] = useState(false);
    const [emitiendoId, setEmitiendoId] = useState(null);
    const { imprimir, imprimiendo } = useImprimirTicket();

    const lista = Array.isArray(ventas) ? ventas : ventas?.data || [];
    const filtradas = useMemo(() => {
        const termino = busqueda.trim().toLowerCase();
        if (!termino) return lista;
        return lista.filter((v) => (v.nombre_razon_social || '').toLowerCase().includes(termino)
            || (v.num_documento || '').includes(termino)
            || numero(v).toLowerCase().includes(termino));
    }, [lista, busqueda]);

    /** Tipo y estado se filtran en el servidor: abarcan todo el historial, no solo esta página. */
    const filtrar = (cambios) => {
        const filtros = { tipo: tipoComprobante, estado_sunat: estadoSunat, ...cambios };
        const params = Object.fromEntries(Object.entries(filtros).filter(([, valor]) => valor && valor !== 'TODOS'));
        router.get('/ventas', params, { preserveState: true, replace: true, only: ['ventas', 'estadoSunat', 'tipoComprobante'] });
    };

    const emitir = async () => {
        const venta = seleccionada;
        if (!venta || esNota(venta) || !POR_ENVIAR.includes(venta.estado_sunat || 'PENDIENTE')) return;

        const pruebas = diagnosticoSunat?.entorno !== 'produccion';
        const confirmado = await confirmar({
            tono: 'warning',
            titulo: pruebas ? 'Enviar comprobante a SUNAT (pruebas)' : 'Enviar comprobante a SUNAT',
            mensaje: `Se enviará ${numero(venta)} ${pruebas ? 'al entorno de pruebas (beta) de SUNAT' : 'a SUNAT'}.`,
            confirmar: 'Enviar',
        });
        if (!confirmado) return;

        setEmitiendoId(venta.id_venta);
        try {
            const { data: resultado } = await axios.post('/emitir', { id_venta: venta.id_venta });
            const estado = resultado.estado || 'ERROR';
            setSeleccionada((actual) => (actual?.id_venta === venta.id_venta ? { ...actual, estado_sunat: estado, mensaje_sunat: resultado.mensaje || '' } : actual));
            router.reload({
                only: ['ventas', 'pendientesSunat'],
                onSuccess: (page) => {
                    const filas = Array.isArray(page.props.ventas) ? page.props.ventas : page.props.ventas?.data || [];
                    const actualizada = filas.find((fila) => String(fila.id_venta) === String(venta.id_venta));
                    // El motivo queda guardado en la venta: se muestra el del servidor.
                    if (actualizada) setSeleccionada((actual) => (String(actual?.id_venta) === String(actualizada.id_venta) ? actualizada : actual));
                },
            });
            (resultado.success ? toast.success : toast.warning)(
                resultado.success ? 'Comprobante aceptado' : `Estado: ${estado}`,
                resultado.mensaje || 'SUNAT procesó el comprobante.',
            );
        } catch (error) {
            toast.error('No se pudo enviar el comprobante', error.response?.data?.message || 'Revisa la conexión y la configuración de SUNAT.');
        } finally {
            setEmitiendoId(null);
        }
    };

    const hayFiltros = tipoComprobante !== 'TODOS' || estadoSunat !== 'TODOS' || busqueda;

    return (
        <AuthenticatedLayout header="Comprobantes">
            <Head title="Comprobantes" />

            <div className="space-y-4">
                <PageHeader
                    title="Comprobantes"
                    description="Boletas, facturas y notas de venta de esta sucursal: estado en SUNAT, reimpresión y archivos."
                    icon={ReceiptText}
                    actions={turnoAbierto
                        ? <Button icon={MonitorSmartphone} href="/pos">Ir al punto de venta</Button>
                        : <Button icon={WalletCards} onClick={() => setMostrarAperturaCaja(true)}>Abrir caja</Button>}
                />

                <DiagnosticoSunat diagnostico={diagnosticoSunat} pendientes={pendientesSunat} />

                <Card className="overflow-hidden">
                    <div className="flex flex-col gap-3 border-b border-slate-100 p-3 sm:p-4 xl:flex-row xl:items-center xl:justify-between">
                        <SearchInput value={busqueda} onChange={setBusqueda} placeholder="Cliente, documento o número…" label="Buscar en esta página" className="xl:w-80" />
                        <div className="flex min-w-0 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
                            <Segmentos etiqueta="Tipo" opciones={TIPOS} valor={tipoComprobante} onCambiar={(tipo) => filtrar({ tipo })} nombre={(t) => (t === 'TODOS' ? 'Todos' : NOMBRE_TIPO[t])} />
                            <Segmentos etiqueta="SUNAT" opciones={ESTADOS_SUNAT} valor={estadoSunat} onCambiar={(estado) => filtrar({ estado_sunat: estado })} nombre={(e) => (e === 'TODOS' ? 'Todos' : ESTADO[e].label)} />
                        </div>
                    </div>

                    {filtradas.length === 0 ? (
                        <EmptyState
                            icon={ReceiptText}
                            title={hayFiltros ? 'Ningún comprobante coincide' : 'Aún no hay comprobantes'}
                            description={hayFiltros ? 'Prueba con otro tipo, estado o búsqueda.' : 'Los cobros del punto de venta aparecerán aquí.'}
                        />
                    ) : (
                        <>
                            {/* Celular: lista de tarjetas tocables. */}
                            <ul className="divide-y divide-slate-100 md:hidden">
                                {filtradas.map((v) => (
                                    <li key={v.id_venta}>
                                        <button type="button" onClick={() => setSeleccionada(v)} className="flex w-full items-center gap-3 px-4 py-3 text-left active:bg-slate-50">
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <TipoComprobante venta={v} />
                                                    <span className="font-mono text-xs font-semibold text-slate-900">{numero(v)}</span>
                                                </div>
                                                <p className="truncate text-sm text-slate-700">{v.nombre_razon_social || 'Público en general'}</p>
                                                <div className="flex items-center gap-2 text-xs text-slate-500">
                                                    <EstadoSunat venta={v} />
                                                    {v.fecha_emision && <span>{fechaHora.format(new Date(v.fecha_emision))}</span>}
                                                </div>
                                            </div>
                                            <span className="font-mono text-sm font-semibold text-slate-900 tabular-nums">{currency.format(Number(v.total_venta || 0))}</span>
                                            <ChevronRight className="h-4 w-4 text-slate-400" aria-hidden="true" />
                                        </button>
                                    </li>
                                ))}
                            </ul>

                            {/* Escritorio: tabla densa. */}
                            <Table className="hidden md:block" caption="Comprobantes emitidos">
                                <THead>
                                    <Th>Fecha y hora</Th>
                                    <Th>Comprobante</Th>
                                    <Th>Cliente</Th>
                                    <Th align="right">Total</Th>
                                    <Th>Estado SUNAT</Th>
                                    <Th align="right"><span className="sr-only">Acciones</span></Th>
                                </THead>
                                <TBody>
                                    {filtradas.map((v) => (
                                        <Tr key={v.id_venta} className="cursor-pointer" onClick={() => setSeleccionada(v)}>
                                            <Td className="text-xs whitespace-nowrap text-slate-600 tabular-nums">{v.fecha_emision ? fechaHora.format(new Date(v.fecha_emision)) : '—'}</Td>
                                            <Td className="whitespace-nowrap">
                                                <div className="flex items-center gap-2">
                                                    <TipoComprobante venta={v} />
                                                    <span className="font-mono text-xs font-semibold text-slate-900">{numero(v)}</span>
                                                </div>
                                            </Td>
                                            <Td>
                                                <p className="max-w-xs truncate text-sm font-medium text-slate-800">{v.nombre_razon_social || 'Público en general'}</p>
                                                {v.num_documento && <span className="font-mono text-xs text-slate-500">{v.num_documento}</span>}
                                            </Td>
                                            <Td align="right" className="font-mono font-semibold whitespace-nowrap text-slate-900 tabular-nums">{currency.format(Number(v.total_venta || 0))}</Td>
                                            <Td><EstadoSunat venta={v} conMensaje /></Td>
                                            <Td align="right">
                                                <Button variant="ghost" size="sm" onClick={(e) => { e.stopPropagation(); setSeleccionada(v); }}>Ver</Button>
                                            </Td>
                                        </Tr>
                                    ))}
                                </TBody>
                            </Table>
                        </>
                    )}
                    <PaginacionServidor paginador={ventas} entidad="comprobantes" />
                </Card>
            </div>

            {seleccionada && (
                <DetalleComprobante
                    venta={seleccionada}
                    onCerrar={() => setSeleccionada(null)}
                    entorno={diagnosticoSunat?.entorno}
                    emitiendo={emitiendoId === seleccionada.id_venta}
                    onEmitir={emitir}
                    imprimiendo={imprimiendo}
                    onImprimir={imprimir}
                />
            )}

            <DialogoAperturaCaja isOpen={mostrarAperturaCaja} onClose={() => setMostrarAperturaCaja(false)} terminales={terminales} />
        </AuthenticatedLayout>
    );
}
