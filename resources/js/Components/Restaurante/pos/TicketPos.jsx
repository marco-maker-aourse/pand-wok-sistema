import { Link } from '@inertiajs/react';
import { Bike, LayoutGrid, Minus, Plus, ReceiptText, Save, Send, ShoppingBag, Split, Trash2, UtensilsCrossed, X } from 'lucide-react';
import { Button, IconButton, cx } from '@/Components/ui';
import LineaCuenta from '../LineaCuenta';
import { soles } from '../formato';

const ICONO_TIPO = { MESA: LayoutGrid, PARA_LLEVAR: ShoppingBag, DELIVERY: Bike };

/** Mismo redondeo que el servidor: subtotal = round(cantidad × precio, 2), en céntimos. */
export const centimos = (cantidad, precio) => Math.round(Math.round(cantidad * 1000) * Math.round(precio * 10000) / 100000);

function LineaBorrador({ linea, onCambiar }) {
    return (
        <li className="flex items-center gap-3 px-4 py-2.5">
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium text-slate-900">{linea.nombre}</p>
                {linea.detalle.length > 0 && <p className="text-xs text-slate-600">{linea.detalle.join(' · ')}</p>}
                {linea.observacion && <p className="text-xs text-amber-700 italic">“{linea.observacion}”</p>}
                <p className="font-mono text-xs text-slate-500 tabular-nums">{soles(centimos(linea.cantidad, linea.precio_estimado) / 100)}</p>
            </div>
            <div className="flex items-center rounded-xl bg-white ring-1 ring-slate-200">
                <IconButton icon={linea.cantidad === 1 ? X : Minus} label={linea.cantidad === 1 ? `Quitar ${linea.nombre}` : 'Uno menos'} onClick={() => onCambiar(linea.clave, -1)} className="h-11 w-11" />
                <span className="w-7 text-center font-mono text-sm font-semibold tabular-nums" aria-live="polite">{linea.cantidad}</span>
                <IconButton icon={Plus} label="Uno más" onClick={() => onCambiar(linea.clave, 1)} className="h-11 w-11" />
            </div>
        </li>
    );
}

/**
 * Ticket del POS: lo ya registrado en la cuenta, lo nuevo aún sin guardar y las acciones.
 * Solo presenta; el estado y las llamadas al servidor viven en la página.
 */
export default function TicketPos({
    titulo, tipo, cuenta, vigentes, borrador, porCobrar, ocupado, puede, requisitosCobro,
    onCambiarBorrador, onDescartar, onGuardar, onEnviar, onCobrar, onCantidadCuenta, onQuitarCuenta, hayPendientes,
}) {
    const IconoTipo = ICONO_TIPO[cuenta?.tipo ?? tipo] ?? ShoppingBag;
    const vacio = vigentes.length === 0 && borrador.length === 0;
    const bloqueoCobro = requisitosCobro.length > 0;
    // Quien no cobra (mozo) tiene como acción principal enviar a cocina.
    const enviarEsPrincipal = puede.enviar && !puede.cobrar;

    return (
        <>
            <header className="flex items-center gap-3 border-b border-slate-100 px-4 py-3">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand-ink">
                    <IconoTipo className="h-5 w-5" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="truncate text-base font-semibold text-slate-900">{titulo}</h2>
                    <p className="truncate text-xs text-slate-500">
                        {cuenta ? [`Cuenta #${cuenta.numero}`, cuenta.mozo].filter(Boolean).join(' · ') : 'Pedido nuevo'}
                    </p>
                </div>
            </header>

            <div className="flex-1 overflow-y-auto overscroll-contain">
                {vigentes.length > 0 && (
                    <ul className="divide-y divide-slate-100" aria-label="Registrado en la cuenta">
                        {vigentes.map((item) => (
                            <LineaCuenta key={item.id_detalle_pedido} item={item} editable ocupado={ocupado} onCantidad={onCantidadCuenta} onQuitar={onQuitarCuenta} />
                        ))}
                    </ul>
                )}
                {borrador.length > 0 && (
                    <section aria-label="Sin guardar" className={cx('bg-amber-50/60', vigentes.length > 0 && 'border-t border-amber-200')}>
                        <p className="flex items-center justify-between px-4 pt-3 text-xs font-semibold tracking-wide text-amber-800 uppercase">
                            Sin guardar
                            <button type="button" onClick={onDescartar} disabled={ocupado} className="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium tracking-normal normal-case text-amber-800 hover:bg-amber-100 disabled:opacity-50">
                                <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />Descartar
                            </button>
                        </p>
                        <ul className="divide-y divide-amber-100">
                            {borrador.map((linea) => <LineaBorrador key={linea.clave} linea={linea} onCambiar={onCambiarBorrador} />)}
                        </ul>
                    </section>
                )}
                {vacio && (
                    <div className="flex flex-col items-center px-6 py-14 text-center">
                        <span className="grid h-12 w-12 place-items-center rounded-2xl bg-slate-100 text-slate-400">
                            <UtensilsCrossed className="h-6 w-6" aria-hidden="true" />
                        </span>
                        <p className="mt-3 text-sm font-medium text-slate-700">El pedido está vacío</p>
                        <p className="mt-1 text-sm text-slate-500">Toca los platos y bebidas de la carta para agregarlos.</p>
                    </div>
                )}
            </div>

            <footer className="space-y-3 border-t border-slate-200 bg-slate-50/80 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <div className="flex items-baseline justify-between">
                    <span className="text-sm text-slate-600">Por cobrar</span>
                    <span className="font-mono text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">{soles(porCobrar)}</span>
                </div>

                <div className="grid grid-cols-2 gap-2">
                    {enviarEsPrincipal ? (
                        <>
                            <Button variant="secondary" size="lg" icon={Save} onClick={onGuardar} disabled={ocupado || !borrador.length} block>Guardar</Button>
                            <Button size="lg" icon={Send} onClick={onEnviar} disabled={ocupado || !hayPendientes} loading={ocupado} block>A cocina</Button>
                        </>
                    ) : (
                        <>
                            <Button variant="secondary" size="lg" icon={Save} onClick={onGuardar} disabled={ocupado || !borrador.length} block className={cx(!puede.cobrar && 'col-span-2')}>
                                Guardar
                            </Button>
                            {puede.cobrar && (
                                <Button size="lg" icon={ReceiptText} onClick={onCobrar} disabled={ocupado || porCobrar <= 0 || bloqueoCobro} block
                                    title={bloqueoCobro ? `Falta: ${requisitosCobro.map((r) => r.mensaje).join(' ')}` : undefined}>
                                    Cobrar
                                </Button>
                            )}
                            {puede.enviar && hayPendientes && (
                                <Button variant="secondary" icon={Send} onClick={onEnviar} disabled={ocupado} block className="col-span-2">
                                    Enviar a cocina
                                </Button>
                            )}
                        </>
                    )}
                </div>

                {cuenta && (
                    <Link href={`/pedidos/${cuenta.id_pedido}/cobrar`} className="flex items-center justify-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-800">
                        <Split className="h-3.5 w-3.5" aria-hidden="true" />Dividir cuenta
                    </Link>
                )}
            </footer>
        </>
    );
}
