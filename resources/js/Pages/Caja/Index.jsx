import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import DialogoAperturaCaja from '@/Components/Caja/DialogoAperturaCaja';
import { Badge, Button, PageHeader, Stat, confirmar, toast } from '@/Components/ui';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    Banknote,
    ClipboardCheck,
    Clock3,
    LockKeyhole,
    Plus,
    WalletCards,
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const dateTime = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' });

function Amount({ value }) {
    return <span className="font-mono font-bold">{currency.format(Number(value) || 0)}</span>;
}

function ErrorMessage({ message }) {
    return message ? <p className="mt-1 text-xs font-semibold text-rose-600">{message}</p> : null;
}

export default function CajaIndex({ turno, resumen, turnos = [], terminales = [] }) {
    const { flash = {} } = usePage().props;
    const [mostrarAperturaCaja, setMostrarAperturaCaja] = useState(false);
    const movement = useForm({ id_turno: turno?.id_turno ?? '', tipo_movimiento: 'INGRESO', monto: '', motivo: '' });
    const close = useForm({ monto_cierre_declarado: '', pin_cajero: '' });
    const terminal = useForm({ id_terminal: '', pin_cajero: '' });
    const montoContado = close.data.monto_cierre_declarado === '' ? null : Number(close.data.monto_cierre_declarado);
    const diferenciaPreliminar = montoContado === null || !resumen
        ? null
        : Number((montoContado - Number(resumen.esperado_gaveta)).toFixed(2));

    const submitMovement = (event) => {
        event.preventDefault();
        movement.post('/caja/movimientos', {
            onSuccess: () => movement.reset('monto', 'motivo'),
        });
    };

    const submitClose = async (event) => {
        event.preventDefault();
        if (!resumen) return;

        const diferencia = montoContado === null
            ? null
            : Number((montoContado - Number(resumen.esperado_gaveta)).toFixed(2));

        const formatoMoneda = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
        const formatoDiferencia = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN', signDisplay: 'always' });

        const esperadoFormateado = formatoMoneda.format(Number(resumen.esperado_gaveta) || 0);
        const contadoFormateado = montoContado !== null ? formatoMoneda.format(montoContado) : '—';
        const diferenciaFormateada = diferencia !== null ? formatoDiferencia.format(diferencia) : '—';

        let colorDiferencia = 'text-emerald-800';
        if (diferencia !== null) {
            colorDiferencia = diferencia === 0 ? 'text-emerald-800' : diferencia > 0 ? 'text-sky-800' : 'text-rose-800';
        }

        const confirmado = await confirmar({
            titulo: '¿Confirmar cierre de caja?',
            confirmar: 'Cerrar turno',
            mensaje: (
                <dl className="mt-1 space-y-1 tabular-nums">
                    <div className="flex justify-between gap-6"><dt>Efectivo esperado</dt><dd className="font-mono font-medium text-slate-900">{esperadoFormateado}</dd></div>
                    <div className="flex justify-between gap-6"><dt>Efectivo contado</dt><dd className="font-mono font-medium text-slate-900">{contadoFormateado}</dd></div>
                    <div className="flex justify-between gap-6"><dt>Diferencia</dt><dd className={`font-mono font-medium ${colorDiferencia}`}>{diferenciaFormateada}</dd></div>
                </dl>
            ),
        });

        if (!confirmado) return;

        close.post(`/caja/${turno.id_turno}/cerrar`, {
            onSuccess: () => {
                toast.success('Caja cerrada', 'El turno se cerró correctamente.');
                window.setTimeout(() => window.location.reload(), 2000);
            },
        });
    };

    const asignarTerminal = (event) => {
        event.preventDefault();
        if (!turno?.id_turno) return;

        terminal.post(`/caja/${turno.id_turno}/terminal`);
    };

    return (
        <AppLayout title="Control de caja">
            <Head title="Control de caja" />
            <div className="space-y-6">
                {flash.success && (
                    <div role="status" className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        {flash.success}
                    </div>
                )}

                <PageHeader
                    breadcrumbs={[{ label: 'Operaciones' }, { label: 'Caja' }]}
                    title="Control de caja"
                    description="Apertura, movimientos y arqueo del turno."
                    actions={
                        turno ? (
                            <Badge tone="success" dot>Turno abierto #{turno.id_turno}</Badge>
                        ) : (
                            <Button icon={Plus} onClick={() => setMostrarAperturaCaja(true)}>Abrir caja</Button>
                        )
                    }
                />

                {!turno ? (
                    <section className="max-w-2xl space-y-4 rounded-2xl bg-white p-5 sm:p-7 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                        <div>
                            <h3 className="text-base font-semibold text-slate-900">No hay una caja abierta</h3>
                            <p className="mt-1 text-sm text-slate-500">Registra el fondo inicial para iniciar un turno. Todas las ventas, ingresos y egresos quedarán vinculados a este turno.</p>
                        </div>
                        <Button icon={WalletCards} onClick={() => setMostrarAperturaCaja(true)}>Abrir turno de caja</Button>
                    </section>
                ) : resumen ? (
                    <>
                        {!turno.id_terminal && (
                            <section className="rounded-xl border border-amber-200 bg-amber-50/60 p-5 sm:p-6">
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                    <div>
                                        <p className="text-xs font-semibold text-amber-800">Terminal pendiente</p>
                                        <h3 className="mt-1 text-base font-semibold text-slate-900">Asigna la caja física a este turno</h3>
                                        <p className="mt-1 max-w-2xl text-xs text-slate-600">La terminal se mantiene para todos los turnos de la misma caja. Esta asignación solo habilita la impresión automática de las próximas aceptaciones SUNAT; no modifica ventas anteriores.</p>
                                    </div>
                                    <form onSubmit={asignarTerminal} className="grid w-full gap-2 sm:max-w-xl sm:grid-cols-[1fr_130px_auto]">
                                        <select required value={terminal.data.id_terminal} onChange={(event) => terminal.setData('id_terminal', event.target.value)} className="h-10 rounded-xl border border-amber-200 bg-white px-3 text-xs font-bold text-slate-800">
                                            <option value="">Selecciona la terminal</option>
                                            {terminales.map((item) => <option key={item.id_terminal} value={item.id_terminal}>{item.nombre} · {item.impresora?.nombre ?? 'Impresora configurada'}</option>)}
                                        </select>
                                        <input required type="password" inputMode="numeric" maxLength="6" autoComplete="off" placeholder="PIN de caja" value={terminal.data.pin_cajero} onChange={(event) => terminal.setData('pin_cajero', event.target.value.replace(/\D/g, ''))} className="h-10 rounded-xl border border-amber-200 bg-white px-3 font-mono text-xs" />
                                        <button disabled={terminal.processing || !terminales.length} className="h-10 rounded-xl bg-amber-700 px-4 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">Asignar terminal</button>
                                        {(terminal.errors.id_terminal || terminal.errors.pin_cajero) && <p className="sm:col-span-3 text-xs font-semibold text-rose-700">{terminal.errors.id_terminal || terminal.errors.pin_cajero}</p>}
                                    </form>
                                </div>
                            </section>
                        )}
                        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                            <Stat destacado className="sm:col-span-2 xl:col-span-1 xl:order-last" label="Efectivo esperado" value={currency.format(Number(resumen.esperado_gaveta) || 0)} hint="Lo que debe haber en gaveta" icon={ClipboardCheck} />
                            <Stat label="Fondo de apertura" value={currency.format(Number(resumen.monto_apertura) || 0)} icon={Banknote} />
                            <Stat label="Ventas en efectivo" value={currency.format(Number(resumen.ventas_efectivo) || 0)} icon={ArrowDownCircle} iconTone="success" />
                            <Stat label="Cobros digitales netos" value={currency.format(Number(resumen.ventas_digitales_netas) || 0)} hint="No suman a la gaveta" icon={WalletCards} iconTone="info" />
                            <Stat label="Ingresos de caja" value={currency.format(Number(resumen.ingresos_caja) || 0)} hint={`Egresos: ${currency.format(Number(resumen.egresos_caja) || 0)}`} icon={ArrowUpCircle} />
                        </section>

                        <section className="flex flex-col justify-between gap-4 rounded-2xl bg-white p-5 sm:flex-row sm:items-center sm:px-6 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                            <div>
                                <p className="text-xs font-semibold text-slate-400">Turno actual</p>
                                <p className="mt-1 text-sm font-bold text-slate-900">Abierto {dateTime.format(new Date(turno.fecha_apertura))}</p>
                                <p className="mt-1 text-xs text-slate-500">Los cobros digitales se informan en el resumen, pero no forman parte del efectivo de gaveta.</p>
                            </div>
                            <Button href="/ventas/create" icon={Plus}>Registrar venta</Button>
                        </section>

                        <section className="grid gap-6 xl:grid-cols-2">
                            <form onSubmit={submitMovement} className="space-y-4 rounded-2xl bg-white p-5 sm:p-6 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                                <div>
                                    <h3 className="text-base font-semibold text-slate-900">Registrar movimiento</h3>
                                    <p className="mt-1 text-xs text-slate-500">Los ingresos y egresos afectan el efectivo esperado en gaveta.</p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className="text-xs font-bold text-slate-600">
                                        Tipo
                                        <select value={movement.data.tipo_movimiento} onChange={(event) => movement.setData('tipo_movimiento', event.target.value)} className="mt-1.5 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm">
                                            <option value="INGRESO">Ingreso</option>
                                            <option value="EGRESO">Egreso</option>
                                        </select>
                                        <ErrorMessage message={movement.errors.tipo_movimiento} />
                                    </label>
                                    <label className="text-xs font-bold text-slate-600">
                                        Monto (S/)
                                        <input type="number" min="0.10" step="0.01" required value={movement.data.monto} onChange={(event) => movement.setData('monto', event.target.value)} className="mt-1.5 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm" />
                                        <ErrorMessage message={movement.errors.monto} />
                                    </label>
                                </div>
                                <label className="block text-xs font-bold text-slate-600">
                                    Motivo
                                    <input type="text" maxLength="255" required value={movement.data.motivo} onChange={(event) => movement.setData('motivo', event.target.value)} className="mt-1.5 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm" placeholder="Indique el motivo del movimiento" />
                                    <ErrorMessage message={movement.errors.motivo} />
                                </label>
                                <button disabled={movement.processing} className="volumen-marca text-on-brand h-10 rounded-xl px-4 text-xs font-semibold disabled:opacity-50 hover:brightness-105">
                                    Registrar movimiento
                                </button>
                            </form>

                            <form onSubmit={submitClose} className="space-y-4 rounded-xl border border-amber-200 bg-amber-50/60 p-5 sm:p-6">
                                <div>
                                    <h3 className="flex items-center gap-2 text-base font-semibold text-slate-900"><LockKeyhole className="h-4 w-4" /> Cierre y arqueo</h3>
                                    <p className="mt-1 text-xs text-slate-600">Cuenta el efectivo físico e ingresa el monto total encontrado en gaveta.</p>
                                </div>
                                <div className="rounded-lg bg-white p-4">
                                    <p className="text-xs font-bold text-slate-500">Efectivo esperado</p>
                                    <p className="mt-1 text-2xl font-semibold text-slate-900"><Amount value={resumen.esperado_gaveta} /></p>
                                </div>
                                <label className="block text-xs font-bold text-slate-600">
                                    Efectivo contado al cierre (S/)
                                    <input type="number" min="0" step="0.01" required value={close.data.monto_cierre_declarado} onChange={(event) => close.setData('monto_cierre_declarado', event.target.value)} className="mt-1.5 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm" />
                                    <ErrorMessage message={close.errors.monto_cierre_declarado} />
                                </label>
                                <label className="block text-xs font-bold text-slate-600">
                                    PIN de caja
                                    <input type="password" inputMode="numeric" autoComplete="off" maxLength="6" value={close.data.pin_cajero} onChange={(event) => close.setData('pin_cajero', event.target.value.replace(/\D/g, ''))} className="mt-1.5 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 font-mono text-sm" placeholder="4 a 6 dígitos" />
                                    <p className="mt-1 text-xs font-medium text-slate-500">Revalidación del cajero para cerrar el turno.</p>
                                    <ErrorMessage message={close.errors.pin_cajero} />
                                </label>
                                {diferenciaPreliminar !== null && (
                                    <div className={`rounded-lg border p-4 ${diferenciaPreliminar === 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : diferenciaPreliminar > 0 ? 'border-sky-200 bg-sky-50 text-sky-800' : 'border-rose-200 bg-rose-50 text-rose-800'}`}>
                                        <p className="text-xs font-semibold">{diferenciaPreliminar === 0 ? 'Cuadre exacto' : diferenciaPreliminar > 0 ? 'Sobrante preliminar' : 'Faltante preliminar'}</p>
                                        <p className="mt-1 text-lg font-semibold"><Amount value={Math.abs(diferenciaPreliminar)} /></p>
                                        {diferenciaPreliminar !== 0 && <p className="mt-1 text-xs font-medium">El sistema conservará la diferencia al cerrar el turno.</p>}
                                    </div>
                                )}
                                <button disabled={close.processing} className="h-10 rounded-xl bg-amber-700 px-4 text-xs font-semibold text-white disabled:opacity-50">
                                    Cerrar turno
                                </button>
                            </form>
                        </section>

                        <section className="rounded-2xl bg-white p-5 sm:p-6 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                            <h3 className="mb-4 text-base font-semibold text-slate-900">Ventas cobradas por método</h3>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {Object.entries(resumen.ventas_por_metodo ?? {}).map(([metodo, total]) => (
                                    <div key={metodo} className="rounded-lg bg-slate-50 p-4">
                                        <p className="text-xs font-bold text-slate-500">{metodo} <span className="ml-1 text-xs uppercase">{total.tipo}</span></p>
                                        <p className="mt-1 text-sm text-slate-900"><Amount value={total.bruto} /></p>
                                        {total.tipo !== 'EFECTIVO' && <p className="mt-1 text-xs font-medium text-slate-500">Neto: <Amount value={total.neto} /></p>}
                                    </div>
                                ))}
                                {!Object.keys(resumen.ventas_por_metodo ?? {}).length && <p className="text-sm text-slate-500">Todavía no hay pagos registrados en este turno.</p>}
                            </div>
                        </section>
                    </>
                ) : (
                    <section className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                        <Banknote className="mx-auto h-10 w-10 text-slate-300" />
                        <h3 className="mt-3 font-semibold text-slate-800">No hay una caja abierta</h3>
                        <p className="mt-1 text-sm text-slate-500">Abre un turno para empezar a registrar ventas y movimientos.</p>
                    </section>
                )}

                <section className="overflow-hidden rounded-2xl bg-white shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="flex items-center gap-2 border-b border-slate-100 px-5 py-4">
                        <Clock3 className="h-4 w-4 text-slate-500" />
                        <h3 className="text-sm font-semibold text-slate-900">Turnos recientes</h3>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[700px] text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500">
                                <tr><th className="px-4 py-2.5 font-medium">Turno</th><th className="px-4 py-2.5 font-medium">Apertura</th><th className="px-4 py-2.5 font-medium">Cierre</th><th className="px-4 py-2.5 font-medium">Monto inicial</th><th className="px-4 py-2.5 font-medium">Declarado</th><th className="px-4 py-2.5 font-medium">Diferencia</th><th className="px-4 py-2.5 font-medium">Estado</th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {turnos.map((item) => (
                                    <tr key={item.id_turno}>
                                        <td className="px-4 py-2.5 font-bold">#{item.id_turno}</td>
                                        <td className="px-4 py-2.5">{dateTime.format(new Date(item.fecha_apertura))}</td>
                                        <td className="px-4 py-2.5">{item.fecha_cierre ? dateTime.format(new Date(item.fecha_cierre)) : '—'}</td>
                                        <td className="px-4 py-2.5"><Amount value={item.monto_apertura} /></td>
                                        <td className="px-4 py-2.5">{item.monto_cierre_declarado === null ? '—' : <Amount value={item.monto_cierre_declarado} />}</td>
                                        <td className={`px-5 py-3 ${Number(item.diferencia_cuadre) === 0 ? 'text-emerald-700' : Number(item.diferencia_cuadre) < 0 ? 'text-rose-700' : 'text-sky-700'}`}>{item.diferencia_cuadre === null ? '—' : <Amount value={item.diferencia_cuadre} />}</td>
                                        <td className="px-4 py-2.5"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${item.estado === 'ABIERTO' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{item.estado}</span></td>
                                    </tr>
                                ))}
                                {!turnos.length && <tr><td colSpan="7" className="px-5 py-8 text-center text-sm text-slate-500">No hay turnos registrados.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>
                <DialogoAperturaCaja
                    isOpen={mostrarAperturaCaja}
                    onClose={() => setMostrarAperturaCaja(false)}
                    redirectTo="/caja"
                    terminales={terminales}
                />
            </div>
        </AppLayout>
    );
}
