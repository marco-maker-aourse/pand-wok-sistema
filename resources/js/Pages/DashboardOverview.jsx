import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Coins,
    Loader2,
    PackagePlus,
    Plus,
    ShoppingCart,
    Wallet,
} from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge, Button, Card, CardHeader, PageHeader } from '@/Components/ui';
import Modal from '@/Components/Modal';
import DialogoAperturaCaja from '@/Components/Caja/DialogoAperturaCaja';
import { AlertasInventario, UltimasVentas } from '@/Components/Tablero/ActividadComercial';
import CobrosPorMetodo from '@/Components/Tablero/CobrosPorMetodo';
import AsistenteOperativo from '@/Components/Tablero/AsistenteOperativo';
import GraficoVentas from '@/Components/Tablero/GraficoVentas';
import IndicadoresOperativos from '@/Components/Tablero/IndicadoresOperativos';

const moneda = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const hora = new Intl.DateTimeFormat('es-PE', { hour: '2-digit', minute: '2-digit' });

const etiquetasPeriodo = {
    hoy: 'Hoy',
    semana: 'Últimos 7 días',
    '2_semanas': 'Últimos 14 días',
    mes: 'Último mes',
    '3_meses': 'Últimos 3 meses',
    personalizado: 'Rango personalizado',
};

export default function DashboardOverview({
    auth = {},
    metrics = {},
    sucursalActual = 'Sede principal',
    periodoActual = 'hoy',
    filtrosFecha = {},
    puedeVerReportesRestaurante = false,
    terminales = [],
}) {
    const [mostrarApertura, setMostrarApertura] = useState(false);
    const [mostrarCierre, setMostrarCierre] = useState(false);
    const [montoCierre, setMontoCierre] = useState('');
    const [errorCierre, setErrorCierre] = useState(null);
    const [procesandoCierre, setProcesandoCierre] = useState(false);
    const [mostrarFechas, setMostrarFechas] = useState(periodoActual === 'personalizado');
    const [desde, setDesde] = useState(filtrosFecha.desde ?? '');
    const [hasta, setHasta] = useState(filtrosFecha.hasta ?? '');

    useEffect(() => {
        setDesde(filtrosFecha.desde ?? '');
        setHasta(filtrosFecha.hasta ?? '');
        setMostrarFechas(periodoActual === 'personalizado');
    }, [filtrosFecha.desde, filtrosFecha.hasta, periodoActual]);

    const resumen = metrics.summary ?? {};
    const turno = metrics.cashShift ?? null;
    const etiquetaPeriodo = etiquetasPeriodo[periodoActual] ?? etiquetasPeriodo.hoy;
    const datosGrafico = Array.isArray(metrics.hourlySales) ? metrics.hourlySales : [];

    const cambiarPeriodo = (periodo) => {
        setMostrarFechas(false);
        router.get('/', { periodo }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const aplicarFechas = (event) => {
        event.preventDefault();
        if (!desde || !hasta) return;
        router.get('/', { periodo: 'personalizado', desde, hasta }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const cerrarTurno = () => {
        const idTurno = turno?.id_turno ?? turno?.id;
        if (!idTurno || !montoCierre || procesandoCierre) return;
        setProcesandoCierre(true);
        setErrorCierre(null);
        router.post(`/caja/${idTurno}/cerrar`, { monto_cierre_declarado: Number(montoCierre) }, {
            preserveScroll: true,
            onSuccess: () => { setMostrarCierre(false); setMontoCierre(''); },
            onError: (errores) => setErrorCierre(Object.values(errores)[0] || 'No se pudo cerrar el turno.'),
            onFinish: () => setProcesandoCierre(false),
        });
    };

    return (
        <AuthenticatedLayout header="Tablero principal">
            <Head title="Tablero principal" />
            <section className="mb-6 flex flex-col gap-4">
                <PageHeader
                    title="Resumen de operaciones"
                    description="Ventas, compras, inventario y caja en una sola lectura."
                    meta={
                        <span className="inline-flex items-center gap-1.5">
                            <Building2 className="h-3.5 w-3.5" aria-hidden="true" />
                            {sucursalActual}
                            <span className="text-slate-300" aria-hidden="true">·</span>
                            <Calendar className="h-3.5 w-3.5" aria-hidden="true" />
                            {etiquetaPeriodo}
                        </span>
                    }
                    actions={
                        <>
                            <SelectorPeriodo periodoActual={periodoActual} onCambiar={cambiarPeriodo} onFechas={() => setMostrarFechas((actual) => !actual)} />
                            <Button href="/ventas/create" icon={ShoppingCart}>Punto de venta</Button>
                            {puedeVerReportesRestaurante && <Button href="/reportes/restaurante" variant="secondary">Reportes del restaurante</Button>}
                            <Button href="/ingresos" variant="secondary" icon={PackagePlus}>Entrada de stock</Button>
                        </>
                    }
                />
                {mostrarFechas && <FiltroFechas desde={desde} hasta={hasta} setDesde={setDesde} setHasta={setHasta} onAplicar={aplicarFechas} onPeriodo={cambiarPeriodo} />}
            </section>

            <div className="space-y-5">
                <IndicadoresOperativos resumen={resumen} etiquetaPeriodo={etiquetaPeriodo} insights={metrics.insights} />
                <div className="grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <GraficoVentas className="xl:col-span-2" datos={datosGrafico} esDiario={Boolean(metrics.esDiario)} etiquetaPeriodo={etiquetaPeriodo} />
                    <CobrosPorMetodo pagos={metrics.paymentMethods ?? []} />
                </div>
                <div className="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
                    <UltimasVentas ventas={metrics.recentSales ?? []} pendientesSunat={Number(metrics.insights?.pendingSunatCount) || 0} />
                    <AlertasInventario alertas={metrics.stockAlerts ?? []} />
                    <PanelCaja turno={turno} usuario={auth.user} lotes={metrics.proximos_vencer ?? []} onAbrir={() => setMostrarApertura(true)} onCerrar={() => { setErrorCierre(null); setMontoCierre(''); setMostrarCierre(true); }} />
                </div>
            </div>
            <AsistenteOperativo />
            <DialogoAperturaCaja isOpen={mostrarApertura} onClose={() => setMostrarApertura(false)} redirectTo="/" terminales={terminales} />
            <ModalCaja abierta={Boolean(turno && mostrarCierre)} titulo="Cierre y arqueo de turno" descripcion="Ingresa el efectivo físico contado en la gaveta del terminal." etiqueta="Efectivo contado en gaveta (S/)" monto={montoCierre} setMonto={setMontoCierre} error={errorCierre} procesando={procesandoCierre} onCerrar={() => setMostrarCierre(false)} onConfirmar={cerrarTurno} textoConfirmar="Cerrar turno" icono={<Wallet className="w-6 h-6" />} />
        </AuthenticatedLayout>
    );
}

function SelectorPeriodo({ periodoActual, onCambiar, onFechas }) {
    const opciones = [['hoy', 'Hoy'], ['semana', '7 días'], ['2_semanas', '14 días'], ['mes', '30 días'], ['3_meses', '3 meses']];
    return <div className="flex flex-wrap items-center gap-0.5 rounded-lg border border-slate-200 bg-white p-0.5" role="group" aria-label="Período">{opciones.map(([clave, texto]) => <button key={clave} type="button" onClick={() => onCambiar(clave)} aria-pressed={periodoActual === clave} className={`px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors ${periodoActual === clave ? 'bg-brand text-on-brand' : 'text-slate-600 hover:bg-brand-soft hover:text-brand-ink'}`}>{texto}</button>)}<button type="button" onClick={onFechas} aria-expanded={periodoActual === 'personalizado'} className={`inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors ${periodoActual === 'personalizado' ? 'bg-brand text-on-brand' : 'text-slate-600 hover:bg-brand-soft hover:text-brand-ink'}`}><Calendar className="w-3.5 h-3.5" />Fechas</button></div>;
}

function FiltroFechas({ desde, hasta, setDesde, setHasta, onAplicar, onPeriodo }) {
    return <form onSubmit={onAplicar} className="rounded-2xl bg-white p-4 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p className="text-xs font-semibold text-slate-900">Consulta por fechas</p>
                <p className="mt-0.5 text-xs font-medium text-slate-400">Elige un inicio y un fin; el gráfico se mostrará por día.</p>
            </div>
            <div className="flex flex-wrap gap-2">
                {[['hoy', 'Hoy'], ['semana', 'Últimos 7 días'], ['mes', 'Últimos 30 días']].map(([periodo, etiqueta]) => <button key={periodo} type="button" onClick={() => onPeriodo(periodo)} className="h-8 rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50">{etiqueta}</button>)}
            </div>
        </div>
        <div className="mt-3 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-3">
            <label className="text-xs font-semibold text-slate-500">Desde<input type="date" value={desde} onChange={(event) => setDesde(event.target.value)} className="mt-1 block h-10 rounded-xl border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 focus:border-slate-400" required /></label>
            <label className="text-xs font-semibold text-slate-500">Hasta<input type="date" value={hasta} onChange={(event) => setHasta(event.target.value)} className="mt-1 block h-10 rounded-xl border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 focus:border-slate-400" required /></label>
            <button type="submit" className="volumen-marca text-on-brand inline-flex h-10 items-center gap-1.5 rounded-xl px-4 text-xs font-semibold transition hover:brightness-105"><Calendar className="h-3.5 w-3.5" />Aplicar fechas</button>
            <p className="pb-2 text-xs text-slate-400">Máximo 366 días por consulta.</p>
        </div>
    </form>;
}

function PanelCaja({ turno, usuario, lotes, onAbrir, onCerrar }) {
    const montoInicial = Number(turno?.openingAmount ?? turno?.monto_apertura) || 0;
    const fechaApertura = turno?.openedAt ?? turno?.fecha_apertura;

    return (
        <Card className="flex flex-col">
            <CardHeader icon={Wallet} title="Turno de caja" description="Estado del turno actual" actions={turno ? <Badge tone="success" dot>Abierta</Badge> : <Badge>Cerrada</Badge>} />
            <div className="flex flex-1 flex-col p-5">
                {turno ? (
                    <dl className="space-y-3 text-sm">
                        <div className="flex items-center justify-between rounded-xl bg-brand-soft px-4 py-3">
                            <dt className="text-brand-ink">Monto inicial</dt>
                            <dd className="text-lg font-semibold text-slate-900 tabular-nums">{moneda.format(montoInicial)}</dd>
                        </div>
                        <div className="flex justify-between"><dt className="text-slate-500">Responsable</dt><dd className="font-medium text-slate-900">{usuario?.nombres || usuario?.name || 'Usuario activo'}</dd></div>
                        <div className="flex justify-between"><dt className="text-slate-500">Apertura</dt><dd className="flex items-center gap-1 font-medium text-slate-900 tabular-nums"><Clock className="h-3.5 w-3.5 text-slate-400" aria-hidden="true" />{fechaApertura ? hora.format(new Date(fechaApertura)) : '--:--'}</dd></div>
                    </dl>
                ) : (
                    <p className="text-sm text-slate-500">Abre un turno para autorizar cobros y emitir comprobantes desde el punto de venta.</p>
                )}
                <div className="mt-auto pt-5">
                    {turno ? <Button variant="secondary" block onClick={onCerrar}>Ver o cerrar turno</Button> : <Button block icon={Plus} onClick={onAbrir}>Abrir turno de caja</Button>}
                </div>
                {lotes.length > 0 && (
                    <div className="mt-5 border-t border-slate-100 pt-4">
                        <p className="mb-2.5 flex items-center gap-1.5 text-sm font-medium text-slate-900"><AlertTriangle className="h-4 w-4 text-amber-600" aria-hidden="true" />Lotes por vencer</p>
                        <ul className="space-y-2">
                            {lotes.slice(0, 3).map((lote) => (
                                <li key={`${lote.codigo_lote}-${lote.id_producto}`} className="flex items-center justify-between gap-2 text-sm">
                                    <span className="min-w-0">
                                        <span className="block truncate text-slate-900">{lote.nombre_producto}</span>
                                        <span className="block font-mono text-xs text-slate-500">{lote.codigo_lote}</span>
                                    </span>
                                    <Badge tone={lote.dias_restantes <= 7 ? 'danger' : 'warning'}>{lote.dias_restantes} días</Badge>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </Card>
    );
}

function ModalCaja({ abierta, titulo, descripcion, etiqueta, monto, setMonto, error, procesando, onCerrar, onConfirmar, textoConfirmar, icono }) {
    return <Modal show={abierta} onClose={onCerrar} maxWidth="sm"><div className="p-6"><div className="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center mb-4">{icono}</div><h3 className="text-base font-semibold text-slate-900 tracking-tight mb-1">{titulo}</h3><p className="text-xs text-slate-500 font-medium mb-5">{descripcion}</p><div className="mb-4"><label className="block text-xs font-semibold text-slate-600 mb-1.5">{etiqueta} *</label><input type="number" step="0.01" min="0" required value={monto} onChange={(event) => setMonto(event.target.value)} className="w-full h-11 px-3.5 font-mono font-bold text-sm text-slate-900 bg-white border border-slate-200 rounded-xl transition" autoFocus />{error && <p className="text-rose-600 text-xs font-bold mt-1 flex items-center gap-1"><AlertCircle className="w-3.5 h-3.5" />{error}</p>}</div><div className="flex gap-2"><button type="button" onClick={onCerrar} className="flex-1 h-10 rounded-xl border border-slate-200 text-slate-600 font-semibold text-xs hover:bg-slate-50 transition">Cancelar</button><button type="button" disabled={procesando || !monto} onClick={onConfirmar} className="volumen-marca text-on-brand flex-1 h-10 rounded-xl font-semibold text-xs transition disabled:opacity-50 flex items-center justify-center gap-2 hover:brightness-105">{procesando ? <Loader2 className="w-4 h-4 animate-spin" /> : textoConfirmar}</button></div></div></Modal>;
}
