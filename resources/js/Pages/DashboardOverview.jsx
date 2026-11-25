import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Calendar, ChartColumn, Clock, MonitorSmartphone, PackagePlus, Plus, Wallet } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge, Button, Card, CardHeader, Field, Input, PageHeader } from '@/Components/ui';
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
                            <Calendar className="ml-2 h-3.5 w-3.5" aria-hidden="true" />
                            {etiquetaPeriodo}
                        </span>
                    }
                    actions={
                        <>
                            <SelectorPeriodo periodoActual={periodoActual} onCambiar={cambiarPeriodo} onFechas={() => setMostrarFechas((actual) => !actual)} />
                            <Button href="/pos" icon={MonitorSmartphone}>Punto de venta</Button>
                            {puedeVerReportesRestaurante && <Button href="/reportes/restaurante" variant="secondary" icon={ChartColumn}>Reportes</Button>}
                            <Button href="/ingresos/create" variant="secondary" icon={PackagePlus}>Registrar entrada</Button>
                        </>
                    }
                />
                {mostrarFechas && <FiltroFechas desde={desde} hasta={hasta} setDesde={setDesde} setHasta={setHasta} onAplicar={aplicarFechas} onPeriodo={cambiarPeriodo} />}
            </section>

            {/* El espacio inferior evita que el botón del asistente tape la última fila. */}
            <div className="space-y-5 pb-20">
                <IndicadoresOperativos resumen={resumen} etiquetaPeriodo={etiquetaPeriodo} insights={metrics.insights} />
                <div className="grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <GraficoVentas className="xl:col-span-2" datos={datosGrafico} esDiario={Boolean(metrics.esDiario)} etiquetaPeriodo={etiquetaPeriodo} />
                    <CobrosPorMetodo pagos={metrics.paymentMethods ?? []} />
                </div>
                <div className="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
                    <UltimasVentas ventas={metrics.recentSales ?? []} pendientesSunat={Number(metrics.insights?.pendingSunatCount) || 0} />
                    <AlertasInventario alertas={metrics.stockAlerts ?? []} />
                    <PanelCaja turno={turno} usuario={auth.user} onAbrir={() => setMostrarApertura(true)} />
                </div>
            </div>
            <AsistenteOperativo />
            <DialogoAperturaCaja isOpen={mostrarApertura} onClose={() => setMostrarApertura(false)} redirectTo="/" terminales={terminales} />
        </AuthenticatedLayout>
    );
}

function SelectorPeriodo({ periodoActual, onCambiar, onFechas }) {
    const opciones = [['hoy', 'Hoy'], ['semana', '7 días'], ['2_semanas', '14 días'], ['mes', '30 días'], ['3_meses', '3 meses']];
    return <div className="flex flex-wrap items-center gap-0.5 rounded-lg border border-slate-200 bg-white p-0.5" role="group" aria-label="Período">{opciones.map(([clave, texto]) => <button key={clave} type="button" onClick={() => onCambiar(clave)} aria-pressed={periodoActual === clave} className={`px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors ${periodoActual === clave ? 'bg-brand text-on-brand' : 'text-slate-600 hover:bg-brand-soft hover:text-brand-ink'}`}>{texto}</button>)}<button type="button" onClick={onFechas} aria-expanded={periodoActual === 'personalizado'} className={`inline-flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors ${periodoActual === 'personalizado' ? 'bg-brand text-on-brand' : 'text-slate-600 hover:bg-brand-soft hover:text-brand-ink'}`}><Calendar className="w-3.5 h-3.5" />Fechas</button></div>;
}

function FiltroFechas({ desde, hasta, setDesde, setHasta, onAplicar, onPeriodo }) {
    return (
        <Card as="form" onSubmit={onAplicar} className="p-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
                <div className="grid flex-1 grid-cols-2 gap-3 sm:max-w-md">
                    <Field label="Desde"><Input type="date" required value={desde} max={hasta || undefined} onChange={(e) => setDesde(e.target.value)} /></Field>
                    <Field label="Hasta"><Input type="date" required value={hasta} min={desde || undefined} onChange={(e) => setHasta(e.target.value)} /></Field>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button type="submit" icon={Calendar} disabled={!desde || !hasta}>Aplicar fechas</Button>
                    {[['hoy', 'Hoy'], ['semana', '7 días'], ['mes', '30 días']].map(([periodo, etiqueta]) => (
                        <Button key={periodo} variant="ghost" size="sm" onClick={() => onPeriodo(periodo)}>{etiqueta}</Button>
                    ))}
                </div>
            </div>
            <p className="mt-2 text-xs text-slate-500">Hasta 366 días por consulta; el gráfico se muestra por día.</p>
        </Card>
    );
}

function PanelCaja({ turno, usuario, onAbrir }) {
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
                    {/* El cierre pide PIN y arqueo: se hace en Caja, con el efectivo esperado a la vista. */}
                    {turno ? <Button variant="secondary" block icon={Wallet} href="/caja">Ir a caja para arqueo y cierre</Button> : <Button block icon={Plus} onClick={onAbrir}>Abrir turno de caja</Button>}
                </div>
            </div>
        </Card>
    );
}
