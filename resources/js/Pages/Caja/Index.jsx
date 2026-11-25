import { useEffect, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Clock3, LockKeyhole, MonitorSmartphone, Printer, ReceiptText, Wallet, WalletCards } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import DialogoAperturaCaja from '@/Components/Caja/DialogoAperturaCaja';
import { Badge, Button, Card, CardHeader, EmptyState, Field, Input, PageHeader, Select, Table, TableEmpty, TBody, Td, Th, THead, Tr, confirmar, cx, toast } from '@/Components/ui';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const conSigno = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN', signDisplay: 'always' });
const dateTime = new Intl.DateTimeFormat('es-PE', { dateStyle: 'short', timeStyle: 'short' });
const soles = (valor) => currency.format(Number(valor) || 0);

const NOMBRE_COMPROBANTE = { BOLETA: 'Boletas', FACTURA: 'Facturas', NOTA_VENTA: 'Notas de venta' };

/** Tono de una diferencia de arqueo: 0 cuadra, positiva sobra, negativa falta. */
function tonoDiferencia(diferencia) {
    if (diferencia === 0) return { etiqueta: 'Cuadre exacto', clases: 'bg-emerald-50 text-emerald-800 ring-emerald-200' };
    return diferencia > 0
        ? { etiqueta: 'Sobrante', clases: 'bg-sky-50 text-sky-800 ring-sky-200' }
        : { etiqueta: 'Faltante', clases: 'bg-rose-50 text-rose-800 ring-rose-200' };
}

function Resumen({ resumen }) {
    const celdas = [
        { etiqueta: 'Fondo de apertura', valor: resumen.monto_apertura },
        { etiqueta: 'Ventas en efectivo', valor: resumen.ventas_efectivo },
        { etiqueta: 'Ingresos de caja', valor: resumen.ingresos_caja },
        { etiqueta: 'Egresos de caja', valor: -Number(resumen.egresos_caja || 0) },
    ];
    return (
        <Card className="overflow-hidden">
            <div className="grid lg:grid-cols-[minmax(0,1fr)_18rem]">
                <dl className="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-4">
                    {celdas.map(({ etiqueta, valor }) => (
                        <div key={etiqueta} className="bg-white px-4 py-3.5">
                            <dt className="text-xs font-medium text-slate-500">{etiqueta}</dt>
                            <dd className="mt-1 font-mono text-lg font-semibold text-slate-900 tabular-nums">{soles(valor)}</dd>
                        </div>
                    ))}
                </dl>
                <div className="flex flex-col justify-center bg-brand-soft px-5 py-4 ring-1 ring-brand-border">
                    <p className="text-xs font-medium text-brand-ink">Efectivo esperado en gaveta</p>
                    <p className="mt-1 font-mono text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">{soles(resumen.esperado_gaveta)}</p>
                    <p className="mt-1 text-xs text-slate-600">Cobros digitales netos: <span className="font-mono tabular-nums">{soles(resumen.ventas_digitales_netas)}</span> (no van a la gaveta)</p>
                </div>
            </div>
        </Card>
    );
}

function VentasDelTurno({ resumen }) {
    const metodos = Object.entries(resumen.ventas_por_metodo ?? {});
    const comprobantes = Object.entries(resumen.ventas_por_comprobante ?? {});
    return (
        <Card>
            <CardHeader title="Ventas del turno" description="Por método de pago y por tipo de comprobante" icon={ReceiptText} />
            {metodos.length === 0 ? (
                <p className="px-5 py-8 text-center text-sm text-slate-500">Todavía no hay cobros en este turno.</p>
            ) : (
                <div className="grid gap-5 p-5 md:grid-cols-2">
                    <section>
                        <h3 className="mb-2 text-xs font-medium text-slate-500">Por método de pago</h3>
                        <ul className="divide-y divide-slate-100 rounded-xl ring-1 ring-slate-200">
                            {metodos.map(([metodo, total]) => (
                                <li key={metodo} className="flex items-center justify-between gap-3 px-3.5 py-2.5 text-sm">
                                    <span className="min-w-0">
                                        <span className="font-medium text-slate-800">{metodo}</span>
                                        {total.tipo !== 'EFECTIVO' && <span className="block text-xs text-slate-500">Neto <span className="font-mono tabular-nums">{soles(total.neto)}</span></span>}
                                    </span>
                                    <span className="font-mono font-semibold text-slate-900 tabular-nums">{soles(total.bruto)}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                    <section>
                        <h3 className="mb-2 text-xs font-medium text-slate-500">Por comprobante</h3>
                        <ul className="divide-y divide-slate-100 rounded-xl ring-1 ring-slate-200">
                            {comprobantes.map(([tipo, { cantidad, total }]) => (
                                <li key={tipo} className="flex items-center justify-between gap-3 px-3.5 py-2.5 text-sm">
                                    <span className="min-w-0">
                                        <span className="font-medium text-slate-800">{NOMBRE_COMPROBANTE[tipo] ?? tipo}</span>
                                        <span className="ml-2 text-xs text-slate-500 tabular-nums">{cantidad}</span>
                                        {tipo === 'NOTA_VENTA' && <span className="block text-xs text-slate-500">Sin valor tributario · no van a SUNAT</span>}
                                    </span>
                                    <span className="font-mono font-semibold text-slate-900 tabular-nums">{soles(total)}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            )}
        </Card>
    );
}

function AsignarTerminal({ turno, terminales }) {
    const form = useForm({ id_terminal: '', pin_cajero: '' });
    const enviar = (event) => {
        event.preventDefault();
        form.post(`/caja/${turno.id_turno}/terminal`);
    };
    return (
        <section className="rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-200">
            <div className="flex items-start gap-3">
                <Printer className="mt-0.5 h-5 w-5 shrink-0 text-amber-700" aria-hidden="true" />
                <div className="min-w-0 flex-1">
                    <h2 className="text-sm font-semibold text-slate-900">Asigna la caja física a este turno</h2>
                    <p className="mt-0.5 max-w-2xl text-xs text-slate-600">Habilita la impresión automática de los próximos comprobantes. No modifica ventas anteriores.</p>
                    <form onSubmit={enviar} className="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_9rem_auto] sm:items-end">
                        <Field label="Terminal" error={form.errors.id_terminal}>
                            <Select required value={form.data.id_terminal} onChange={(e) => form.setData('id_terminal', e.target.value)}>
                                <option value="">Selecciona la terminal</option>
                                {terminales.map((item) => <option key={item.id_terminal} value={item.id_terminal}>{item.nombre} · {item.impresora?.nombre ?? 'Impresora configurada'}</option>)}
                            </Select>
                        </Field>
                        <Field label="PIN de caja" error={form.errors.pin_cajero}>
                            <Input required type="password" inputMode="numeric" maxLength={6} autoComplete="off" className="font-mono" value={form.data.pin_cajero} onChange={(e) => form.setData('pin_cajero', e.target.value.replace(/\D/g, ''))} />
                        </Field>
                        <Button type="submit" loading={form.processing} disabled={!terminales.length}>Asignar</Button>
                    </form>
                    {!terminales.length && <p className="mt-2 text-xs text-amber-800">No hay terminales activas en esta sucursal. Créalas en Impresoras y terminales.</p>}
                </div>
            </div>
        </section>
    );
}

function Movimiento({ turno }) {
    const form = useForm({ id_turno: turno.id_turno, tipo_movimiento: 'INGRESO', monto: '', motivo: '' });
    const enviar = (event) => {
        event.preventDefault();
        form.post('/caja/movimientos', { preserveScroll: true, onSuccess: () => form.reset('monto', 'motivo') });
    };
    const opciones = [
        { valor: 'INGRESO', etiqueta: 'Ingreso', icono: ArrowDownLeft },
        { valor: 'EGRESO', etiqueta: 'Egreso', icono: ArrowUpRight },
    ];
    return (
        <Card as="form" onSubmit={enviar}>
            <CardHeader title="Registrar movimiento" description="Ingresos y egresos de efectivo cambian lo esperado en gaveta" icon={Wallet} />
            <div className="space-y-4 p-5">
                <div className="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1" role="radiogroup" aria-label="Tipo de movimiento">
                    {opciones.map(({ valor, etiqueta, icono: Icono }) => (
                        <button key={valor} type="button" role="radio" aria-checked={form.data.tipo_movimiento === valor} onClick={() => form.setData('tipo_movimiento', valor)}
                            className={cx('flex h-10 items-center justify-center gap-1.5 rounded-lg text-sm font-medium transition-colors', form.data.tipo_movimiento === valor ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900')}>
                            <Icono className="h-4 w-4" aria-hidden="true" />{etiqueta}
                        </button>
                    ))}
                </div>
                {form.errors.tipo_movimiento && <p className="text-xs font-medium text-rose-600" role="alert">{form.errors.tipo_movimiento}</p>}
                <div className="grid gap-4 sm:grid-cols-[10rem_minmax(0,1fr)]">
                    <Field label="Monto (S/)" error={form.errors.monto} required>
                        <Input type="number" inputMode="decimal" min="0.10" step="0.01" className="font-mono" value={form.data.monto} onChange={(e) => form.setData('monto', e.target.value)} />
                    </Field>
                    <Field label="Motivo" error={form.errors.motivo} required>
                        <Input type="text" maxLength={255} value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} placeholder="Ej.: compra de gas, sencillo para vuelto" />
                    </Field>
                </div>
                <Button type="submit" loading={form.processing} block className="sm:w-auto">Registrar {form.data.tipo_movimiento === 'EGRESO' ? 'egreso' : 'ingreso'}</Button>
            </div>
        </Card>
    );
}

function Cierre({ turno, resumen }) {
    const form = useForm({ monto_cierre_declarado: '', pin_cajero: '' });
    const contado = form.data.monto_cierre_declarado === '' ? null : Number(form.data.monto_cierre_declarado);
    const diferencia = contado === null ? null : Number((contado - Number(resumen.esperado_gaveta)).toFixed(2));
    const tono = diferencia === null ? null : tonoDiferencia(diferencia);

    const enviar = async (event) => {
        event.preventDefault();
        const confirmado = await confirmar({
            titulo: '¿Cerrar el turno de caja?',
            confirmar: 'Cerrar turno',
            tono: 'warning',
            mensaje: (
                <dl className="mt-1 space-y-1 tabular-nums">
                    <div className="flex justify-between gap-6"><dt>Efectivo esperado</dt><dd className="font-mono font-medium text-slate-900">{soles(resumen.esperado_gaveta)}</dd></div>
                    <div className="flex justify-between gap-6"><dt>Efectivo contado</dt><dd className="font-mono font-medium text-slate-900">{contado === null ? '—' : soles(contado)}</dd></div>
                    <div className="flex justify-between gap-6"><dt>Diferencia</dt><dd className="font-mono font-medium text-slate-900">{diferencia === null ? '—' : conSigno.format(diferencia)}</dd></div>
                </dl>
            ),
        });
        if (!confirmado) return;

        form.post(`/caja/${turno.id_turno}/cerrar`, {
            onSuccess: () => {
                toast.success('Caja cerrada', 'El turno se cerró correctamente.');
                window.setTimeout(() => window.location.reload(), 2000);
            },
        });
    };

    return (
        <Card as="form" onSubmit={enviar}>
            <CardHeader title="Cierre y arqueo" description="Cuenta el efectivo físico de la gaveta" icon={LockKeyhole} />
            <div className="space-y-4 p-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Efectivo contado (S/)" error={form.errors.monto_cierre_declarado} required>
                        <Input type="number" inputMode="decimal" min="0" step="0.01" className="font-mono" value={form.data.monto_cierre_declarado} onChange={(e) => form.setData('monto_cierre_declarado', e.target.value)} />
                    </Field>
                    <Field label="PIN de caja" hint="Vuelve a validar al cajero." error={form.errors.pin_cajero}>
                        <Input type="password" inputMode="numeric" autoComplete="off" maxLength={6} className="font-mono" placeholder="4 a 6 dígitos" value={form.data.pin_cajero} onChange={(e) => form.setData('pin_cajero', e.target.value.replace(/\D/g, ''))} />
                    </Field>
                </div>
                {tono && (
                    <div className={cx('flex items-center justify-between gap-3 rounded-xl px-4 py-3 ring-1', tono.clases)} role="status">
                        <div>
                            <p className="text-sm font-semibold">{tono.etiqueta}</p>
                            {diferencia !== 0 && <p className="text-xs">La diferencia queda registrada al cerrar el turno.</p>}
                        </div>
                        <p className="font-mono text-lg font-semibold tabular-nums">{soles(Math.abs(diferencia))}</p>
                    </div>
                )}
                <Button type="submit" variant="secondary" icon={LockKeyhole} loading={form.processing} block className="sm:w-auto">Cerrar turno</Button>
            </div>
        </Card>
    );
}

function Historial({ turnos }) {
    return (
        <Card className="overflow-hidden">
            <CardHeader title="Turnos recientes" icon={Clock3} />
            <Table caption="Turnos recientes de caja">
                <THead>
                    <Th>Turno</Th><Th>Apertura</Th><Th>Cierre</Th><Th align="right">Fondo</Th><Th align="right">Declarado</Th><Th align="right">Diferencia</Th><Th>Estado</Th>
                </THead>
                <TBody>
                    {turnos.map((item) => {
                        const diferencia = item.diferencia_cuadre === null ? null : Number(item.diferencia_cuadre);
                        return (
                            <Tr key={item.id_turno}>
                                <Td className="font-mono font-semibold">#{item.id_turno}</Td>
                                <Td className="whitespace-nowrap tabular-nums">{dateTime.format(new Date(item.fecha_apertura))}</Td>
                                <Td className="whitespace-nowrap tabular-nums">{item.fecha_cierre ? dateTime.format(new Date(item.fecha_cierre)) : '—'}</Td>
                                <Td align="right" className="font-mono tabular-nums">{soles(item.monto_apertura)}</Td>
                                <Td align="right" className="font-mono tabular-nums">{item.monto_cierre_declarado === null ? '—' : soles(item.monto_cierre_declarado)}</Td>
                                <Td align="right" className={cx('font-mono font-medium tabular-nums', diferencia === null ? 'text-slate-400' : diferencia === 0 ? 'text-emerald-700' : diferencia < 0 ? 'text-rose-700' : 'text-sky-700')}>
                                    {diferencia === null ? '—' : conSigno.format(diferencia)}
                                </Td>
                                <Td><Badge tone={item.estado === 'ABIERTO' ? 'success' : 'neutral'} dot={item.estado === 'ABIERTO'}>{item.estado === 'ABIERTO' ? 'Abierto' : 'Cerrado'}</Badge></Td>
                            </Tr>
                        );
                    })}
                    {!turnos.length && <TableEmpty colSpan={7} title="No hay turnos registrados" />}
                </TBody>
            </Table>
        </Card>
    );
}

export default function CajaIndex({ turno, resumen, turnos = [], terminales = [] }) {
    const { flash = {} } = usePage().props;
    const [mostrarApertura, setMostrarApertura] = useState(false);

    useEffect(() => { if (flash.success) toast.success(flash.success); }, [flash.success]);

    return (
        <AppLayout title="Caja">
            <Head title="Caja" />
            <div className="space-y-4 sm:space-y-6">
                <PageHeader
                    title="Caja"
                    description={turno ? `Turno #${turno.id_turno} abierto el ${dateTime.format(new Date(turno.fecha_apertura))}` : 'Apertura, movimientos y arqueo del turno.'}
                    icon={Wallet}
                    actions={turno
                        ? <><Badge tone="success" dot>Caja abierta</Badge><Button icon={MonitorSmartphone} href="/pos">Ir al punto de venta</Button></>
                        : <Button icon={WalletCards} onClick={() => setMostrarApertura(true)}>Abrir caja</Button>}
                />

                {!turno || !resumen ? (
                    <Card>
                        <EmptyState
                            icon={Wallet}
                            title="No hay una caja abierta"
                            description="Registra el fondo inicial para empezar el turno. Las ventas, ingresos y egresos quedan vinculados a él."
                            action={<Button icon={WalletCards} onClick={() => setMostrarApertura(true)}>Abrir turno de caja</Button>}
                        />
                    </Card>
                ) : (
                    <>
                        {!turno.id_terminal && <AsignarTerminal turno={turno} terminales={terminales} />}
                        <Resumen resumen={resumen} />
                        <VentasDelTurno resumen={resumen} />
                        <div className="grid gap-4 sm:gap-6 xl:grid-cols-2">
                            <Movimiento turno={turno} />
                            <Cierre turno={turno} resumen={resumen} />
                        </div>
                    </>
                )}

                <Historial turnos={turnos} />
            </div>

            <DialogoAperturaCaja isOpen={mostrarApertura} onClose={() => setMostrarApertura(false)} redirectTo="/caja" terminales={terminales} />
        </AppLayout>
    );
}
