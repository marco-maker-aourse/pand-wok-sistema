import { Link } from '@inertiajs/react';
import { AlertTriangle, Bike, LayoutGrid, ListOrdered, Plus, ShoppingBag, UserRound } from 'lucide-react';
import { Button, cx } from '@/Components/ui';

export const TIPOS_VENTA = [
    { clave: 'PARA_LLEVAR', etiqueta: 'Mostrador', icono: ShoppingBag },
    { clave: 'MESA', etiqueta: 'Mesa', icono: LayoutGrid },
    { clave: 'DELIVERY', etiqueta: 'Delivery', icono: Bike },
];

/** Tipo de venta (bloqueado si ya hay cuenta), mesa o cliente y acceso a las cuentas abiertas. */
export default function BarraTipoVenta({ tipo, cuenta, mesa, contacto, cantidadCuentas, onTipo, onMesas, onContacto, onCuentas, onNueva }) {
    const actual = cuenta?.tipo ?? tipo;

    return (
        <div className="mb-3 flex flex-wrap items-center gap-2">
            <div className="grid w-full grid-cols-3 gap-1 rounded-xl bg-slate-200/60 p-1 sm:w-auto" role="radiogroup" aria-label="Tipo de venta">
                {TIPOS_VENTA.map(({ clave, etiqueta, icono: Icono }) => (
                    <button
                        key={clave}
                        type="button"
                        role="radio"
                        aria-checked={actual === clave}
                        disabled={Boolean(cuenta)}
                        onClick={() => onTipo(clave)}
                        className={cx(
                            'flex h-11 items-center justify-center gap-1.5 rounded-lg px-2 text-sm font-medium transition-colors disabled:cursor-not-allowed sm:px-3',
                            actual === clave ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900',
                            cuenta && actual !== clave && 'opacity-50',
                        )}
                    >
                        <Icono className={cx('h-4 w-4', actual === clave && 'text-brand-ink')} aria-hidden="true" />{etiqueta}
                    </button>
                ))}
            </div>

            {!cuenta && tipo === 'MESA' && (
                <Button variant={mesa ? 'secondary' : 'primary'} icon={LayoutGrid} onClick={onMesas}>{mesa ? `Mesa ${mesa.codigo}` : 'Elegir mesa'}</Button>
            )}
            {!cuenta && tipo !== 'MESA' && (
                <Button variant="secondary" icon={UserRound} onClick={onContacto} className="max-w-[14rem]">
                    <span className="truncate">{contacto.nombre_contacto || (tipo === 'DELIVERY' ? 'Datos de entrega' : 'Cliente (opcional)')}</span>
                </Button>
            )}

            <div className="ml-auto flex gap-2">
                {cuenta && <Button variant="secondary" icon={Plus} onClick={onNueva}>Nueva venta</Button>}
                <Button variant="secondary" icon={ListOrdered} onClick={onCuentas} aria-label={`Cuentas abiertas: ${cantidadCuentas}`}>
                    <span className="hidden sm:inline">Cuentas abiertas</span>
                    {cantidadCuentas > 0 && <span className="rounded-full bg-brand-soft px-1.5 font-mono text-xs text-brand-ink tabular-nums">{cantidadCuentas}</span>}
                </Button>
            </div>
        </div>
    );
}

/** Lo que impide cobrar (caja cerrada, serie sin configurar…), con el enlace para resolverlo. */
export function AvisoRequisitosCobro({ requisitos }) {
    if (!requisitos.length) return null;
    return (
        <section className="mb-3 flex gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200" aria-label="Falta para cobrar">
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
            <div className="min-w-0 flex-1">
                <p className="font-semibold">Para cobrar falta:</p>
                <ul className="mt-1.5 space-y-1.5">
                    {requisitos.map((r) => (
                        <li key={r.clave} className="flex flex-wrap items-center justify-between gap-2">
                            <span>{r.mensaje}</span>
                            <Link href={r.enlace} className="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-amber-900 ring-1 ring-amber-300 hover:bg-amber-100">{r.accion}</Link>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
