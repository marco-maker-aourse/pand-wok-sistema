import { Link } from '@inertiajs/react';
import { Bike, CheckCircle2, ChevronRight, LayoutGrid, ListOrdered, Printer, ShoppingBag } from 'lucide-react';
import { Badge, Button, Dialog, EmptyState, Field, Input, Textarea } from '@/Components/ui';
import { soles, tituloCuenta } from '../formato';

const ICONO_TIPO = { MESA: LayoutGrid, PARA_LLEVAR: ShoppingBag, DELIVERY: Bike };

export function DialogoCuentasAbiertas({ abierto, onCerrar, cuentas, onElegir }) {
    return (
        <Dialog open={abierto} onClose={onCerrar} title="Cuentas abiertas" description={cuentas.length ? `${cuentas.length} en esta sucursal` : undefined} size="md">
            {cuentas.length === 0 ? (
                <EmptyState icon={ListOrdered} title="No hay cuentas abiertas" description="Las mesas, pedidos para llevar y deliverys sin cobrar aparecerán aquí." />
            ) : (
                <ul className="divide-y divide-slate-100">
                    {cuentas.map((c) => {
                        const Icono = ICONO_TIPO[c.tipo] ?? ShoppingBag;
                        return (
                            <li key={c.id_pedido}>
                                <button type="button" onClick={() => onElegir(c)} className="flex min-h-14 w-full items-center gap-3 px-5 py-3 text-left hover:bg-slate-50 active:bg-slate-100">
                                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600"><Icono className="h-4 w-4" aria-hidden="true" /></span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium text-slate-900">{tituloCuenta(c)}</span>
                                        <span className="block text-xs text-slate-500">#{c.numero} · {c.items} ítems{c.mozo ? ` · ${c.mozo}` : ''}</span>
                                    </span>
                                    <span className="font-mono text-sm font-semibold tabular-nums">{soles(c.total)}</span>
                                    <ChevronRight className="h-4 w-4 text-slate-400" aria-hidden="true" />
                                </button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Dialog>
    );
}

export function DialogoContacto({ abierto, onCerrar, tipo, contacto, onCambiar }) {
    const delivery = tipo === 'DELIVERY';
    return (
        <Dialog open={abierto} onClose={onCerrar} title={delivery ? 'Datos de entrega' : 'Cliente'} description={delivery ? undefined : 'Opcional: para llamar al cliente cuando su pedido esté listo.'} size="sm"
            footer={<Button onClick={onCerrar} block className="sm:w-auto">Listo</Button>}>
            <form className="space-y-4 p-5" onSubmit={(e) => { e.preventDefault(); onCerrar(); }}>
                <Field label="Nombre"><Input value={contacto.nombre_contacto} maxLength={120} autoComplete="off" onChange={(e) => onCambiar({ ...contacto, nombre_contacto: e.target.value })} autoFocus /></Field>
                <Field label="Teléfono"><Input value={contacto.telefono_contacto} maxLength={20} inputMode="tel" autoComplete="off" onChange={(e) => onCambiar({ ...contacto, telefono_contacto: e.target.value })} /></Field>
                {delivery && (
                    <Field label="Dirección de entrega" required><Textarea rows={2} maxLength={255} value={contacto.direccion_entrega} onChange={(e) => onCambiar({ ...contacto, direccion_entrega: e.target.value })} /></Field>
                )}
                <button type="submit" hidden aria-hidden="true" tabIndex={-1} />
            </form>
        </Dialog>
    );
}

export function DialogoVentaRegistrada({ venta, onNueva, onImprimir, imprimiendo }) {
    return (
        <Dialog open={Boolean(venta)} onClose={onNueva} size="sm"
            footer={(
                <>
                    <Button variant="secondary" icon={Printer} loading={imprimiendo} onClick={() => onImprimir(venta.id_venta)}>Imprimir ticket</Button>
                    <Button onClick={onNueva} autoFocus>Nueva venta</Button>
                </>
            )}>
            {venta && (
                <div className="flex flex-col items-center gap-2 px-5 pt-6 pb-5 text-center">
                    <span className="grid h-12 w-12 place-items-center rounded-full bg-emerald-50 text-emerald-600 ring-4 ring-emerald-50/60">
                        <CheckCircle2 className="h-6 w-6" aria-hidden="true" />
                    </span>
                    <h2 className="mt-1 text-base font-semibold text-slate-900">Venta registrada</h2>
                    <Badge tone="success">{venta.comprobante}</Badge>
                    <p className="font-mono text-3xl font-semibold tracking-tight tabular-nums">{soles(venta.total)}</p>
                    {venta.vuelto > 0 && (
                        <p className="rounded-xl bg-amber-50 px-4 py-2 text-lg text-amber-900 ring-1 ring-amber-200">
                            Vuelto: <span className="font-mono font-semibold tabular-nums">{soles(venta.vuelto)}</span>
                        </p>
                    )}
                    <p className="text-xs text-slate-500">
                        Cuenta #{venta.cuenta}. {venta.nota_venta ? 'Nota de venta sin valor tributario: no se envía a SUNAT.' : 'El comprobante se envía a SUNAT en segundo plano.'}
                    </p>
                    <Link href="/ventas" className="text-xs font-semibold text-brand-ink underline">Ver comprobantes y su estado en SUNAT</Link>
                </div>
            )}
        </Dialog>
    );
}
