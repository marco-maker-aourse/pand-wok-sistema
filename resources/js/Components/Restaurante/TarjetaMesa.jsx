import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Clock3, MoreVertical, Users } from 'lucide-react';
import { cx } from '@/Components/ui';
import { ESTADO_MESA } from './estados';
import { soles, tiempoDesde } from './formato';

/**
 * Mesa del plano del salón. Toda la tarjeta es el botón principal (abrir o ver la cuenta);
 * el menú secundario cambia estados manuales sin abrir la cuenta.
 */
export default function TarjetaMesa({ mesa, ahora, onSeleccionar, onCambiarEstado, puedeCambiarEstado }) {
    const estilo = ESTADO_MESA[mesa.estado] ?? ESTADO_MESA.DISPONIBLE;
    const cuenta = mesa.cuenta;
    const deshabilitada = mesa.estado === 'FUERA_SERVICIO';
    const acciones = [
        mesa.estado !== 'RESERVADA' && mesa.estado !== 'OCUPADA' && { estado: 'RESERVADA', etiqueta: 'Reservar' },
        mesa.estado === 'RESERVADA' && { estado: 'DISPONIBLE', etiqueta: 'Liberar reserva' },
        mesa.estado !== 'FUERA_SERVICIO' && mesa.estado !== 'OCUPADA' && { estado: 'FUERA_SERVICIO', etiqueta: 'Sacar de servicio' },
        mesa.estado === 'FUERA_SERVICIO' && { estado: 'DISPONIBLE', etiqueta: 'Volver a servicio' },
    ].filter(Boolean);

    return (
        <div className={cx('relative rounded-2xl ring-1 transition-[box-shadow,transform] duration-150', estilo.tarjeta)}>
            <button
                type="button"
                disabled={deshabilitada}
                onClick={() => onSeleccionar(mesa)}
                className="flex min-h-32 w-full flex-col justify-between gap-3 rounded-2xl p-4 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand active:scale-[0.98] disabled:cursor-not-allowed"
                aria-label={`Mesa ${mesa.codigo}, ${estilo.etiqueta}${cuenta ? `, consumo ${soles(cuenta.total)}` : ''}`}
            >
                <div className="flex items-start justify-between gap-2 pr-6">
                    <span className="text-2xl font-semibold tracking-tight text-slate-900">{mesa.codigo}</span>
                </div>
                {cuenta ? (
                    <div className="space-y-1">
                        <p className="font-mono text-lg font-semibold text-brand-ink tabular-nums">{soles(cuenta.total)}</p>
                        <p className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-slate-600">
                            <span className="inline-flex items-center gap-1"><Clock3 className="h-3.5 w-3.5" aria-hidden="true" />{tiempoDesde(cuenta.abierto_en, ahora)}</span>
                            {cuenta.comensales && <span className="inline-flex items-center gap-1"><Users className="h-3.5 w-3.5" aria-hidden="true" />{cuenta.comensales}</span>}
                            <span>{cuenta.items} ítem{cuenta.items === 1 ? '' : 's'}</span>
                        </p>
                        {cuenta.mozo && <p className="truncate text-xs text-slate-500">{cuenta.mozo}</p>}
                    </div>
                ) : (
                    <p className="flex items-center gap-1.5 text-sm text-slate-600">
                        <span className={cx('h-2 w-2 rounded-full', estilo.punto)} aria-hidden="true" />
                        {estilo.etiqueta}
                        <span className="text-slate-400">· {mesa.capacidad} pers.</span>
                    </p>
                )}
            </button>

            {puedeCambiarEstado && acciones.length > 0 && (
                <Menu as="div" className="absolute top-2.5 right-2">
                    <MenuButton className="grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-white/80 hover:text-slate-900" aria-label={`Opciones de la mesa ${mesa.codigo}`}>
                        <MoreVertical className="h-4 w-4" aria-hidden="true" />
                    </MenuButton>
                    <MenuItems anchor="bottom end" className="z-30 mt-1 w-48 rounded-xl bg-white p-1 shadow-[var(--shadow-flotante)] ring-1 ring-slate-900/5 focus:outline-none">
                        {acciones.map((accion) => (
                            <MenuItem key={accion.estado + accion.etiqueta}>
                                <button type="button" onClick={() => onCambiarEstado(mesa, accion.estado)} className="block w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 data-focus:bg-slate-100">
                                    {accion.etiqueta}
                                </button>
                            </MenuItem>
                        ))}
                    </MenuItems>
                </Menu>
            )}
        </div>
    );
}
