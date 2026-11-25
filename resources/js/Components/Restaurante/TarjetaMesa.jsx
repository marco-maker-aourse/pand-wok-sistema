import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Ban, CalendarClock, CircleCheck, Clock3, MoreHorizontal, Utensils } from 'lucide-react';
import { cx } from '@/Components/ui';
import { soles, tiempoDesde } from './formato';

/** Cada estado se reconoce por ícono y texto, no solo por color. */
const ESTILO = {
    DISPONIBLE: { etiqueta: 'Libre', icono: CircleCheck, mesa: 'bg-white ring-emerald-300', silla: 'bg-emerald-200', texto: 'text-emerald-700' },
    OCUPADA: { etiqueta: 'Ocupada', icono: Utensils, mesa: 'bg-brand-soft ring-brand', silla: 'bg-brand-border', texto: 'text-brand-ink' },
    RESERVADA: { etiqueta: 'Reservada', icono: CalendarClock, mesa: 'bg-amber-50 ring-amber-300', silla: 'bg-amber-200', texto: 'text-amber-800' },
    FUERA_SERVICIO: { etiqueta: 'Fuera de servicio', icono: Ban, mesa: 'bg-slate-50 ring-slate-200 opacity-70', silla: 'bg-slate-200', texto: 'text-slate-500' },
};

const MAX_SILLAS = 8;
const LADOS = ['arriba', 'abajo', 'izquierda', 'derecha'];

/** Reparte las sillas alrededor de la mesa: arriba, abajo, izquierda, derecha y vuelta. */
function repartirSillas(cantidad) {
    const lados = { arriba: [], abajo: [], izquierda: [], derecha: [] };
    for (let i = 0; i < cantidad; i += 1) lados[LADOS[i % 4]].push(i);
    return lados;
}

function Sillas({ indices, ocupadas, claseOcupada, horizontal }) {
    if (!indices.length) return <span />;
    return (
        <span className={cx('flex justify-center gap-1.5', horizontal ? 'flex-row' : 'flex-col')} aria-hidden="true">
            {indices.map((i) => (
                <span key={i} className={cx('rounded-full transition-colors', horizontal ? 'h-2 w-5' : 'h-5 w-2', i < ocupadas ? claseOcupada : 'bg-slate-200')} />
            ))}
        </span>
    );
}

/**
 * Mesa vista desde arriba, con sus sillas según la capacidad. En una mesa ocupada se llenan
 * tantas sillas como comensales tenga la cuenta. Toda la tarjeta abre la mesa; el menú "⋯"
 * cambia estados manuales sin abrir la cuenta.
 */
export default function TarjetaMesa({ mesa, ahora, onSeleccionar, onCambiarEstado, puedeCambiarEstado }) {
    const estilo = ESTILO[mesa.estado] ?? ESTILO.DISPONIBLE;
    const Icono = estilo.icono;
    const cuenta = mesa.cuenta;
    const deshabilitada = mesa.estado === 'FUERA_SERVICIO';
    const sillas = Math.min(Math.max(Number(mesa.capacidad) || 0, 0), MAX_SILLAS);
    const ocupadas = cuenta ? Math.min(Number(cuenta.comensales) || sillas, sillas) : 0;
    const lados = repartirSillas(sillas);
    const acciones = [
        mesa.estado !== 'RESERVADA' && mesa.estado !== 'OCUPADA' && { estado: 'RESERVADA', etiqueta: 'Reservar' },
        mesa.estado === 'RESERVADA' && { estado: 'DISPONIBLE', etiqueta: 'Liberar reserva' },
        mesa.estado !== 'FUERA_SERVICIO' && mesa.estado !== 'OCUPADA' && { estado: 'FUERA_SERVICIO', etiqueta: 'Sacar de servicio' },
        mesa.estado === 'FUERA_SERVICIO' && { estado: 'DISPONIBLE', etiqueta: 'Volver a servicio' },
    ].filter(Boolean);

    const descripcion = [
        `Mesa ${mesa.codigo}`, estilo.etiqueta, `${mesa.capacidad} personas`,
        cuenta && `consumo ${soles(cuenta.total)}`, cuenta && `abierta hace ${tiempoDesde(cuenta.abierto_en, ahora)}`,
    ].filter(Boolean).join(', ');

    return (
        <div className="group relative">
            <button
                type="button"
                disabled={deshabilitada}
                onClick={() => onSeleccionar(mesa)}
                aria-label={descripcion}
                className="flex w-full flex-col items-stretch gap-1.5 rounded-2xl p-2 text-left transition-transform focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand enabled:active:scale-[0.97] disabled:cursor-not-allowed"
            >
                <Sillas indices={lados.arriba} ocupadas={ocupadas} claseOcupada={estilo.silla} horizontal />
                <span className="flex items-stretch gap-1.5">
                    <Sillas indices={lados.izquierda} ocupadas={ocupadas} claseOcupada={estilo.silla} />
                    <span className={cx('flex min-h-28 flex-1 flex-col justify-between rounded-xl p-3 ring-1 transition-shadow group-hover:shadow-[var(--shadow-elevado)]', estilo.mesa)}>
                        <span className="flex items-start justify-between gap-1 pr-6">
                            <span className="text-2xl leading-none font-semibold tracking-tight text-slate-900">{mesa.codigo}</span>
                        </span>
                        {cuenta ? (
                            <span className="space-y-0.5">
                                <span className="block font-mono text-base font-semibold text-slate-900 tabular-nums">{soles(cuenta.total)}</span>
                                <span className="flex items-center gap-1 text-xs text-slate-600 tabular-nums">
                                    <Clock3 className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />{tiempoDesde(cuenta.abierto_en, ahora)}
                                    <span className="text-slate-400">· {cuenta.items} ítem{cuenta.items === 1 ? '' : 's'}</span>
                                </span>
                                {cuenta.mozo && <span className="block truncate text-xs text-slate-500">{cuenta.mozo}</span>}
                            </span>
                        ) : (
                            <span className={cx('flex items-center gap-1.5 text-xs font-medium', estilo.texto)}>
                                <Icono className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />{estilo.etiqueta}
                                <span className="font-normal text-slate-400">· {mesa.capacidad} pers.</span>
                            </span>
                        )}
                    </span>
                    <Sillas indices={lados.derecha} ocupadas={ocupadas} claseOcupada={estilo.silla} />
                </span>
                <Sillas indices={lados.abajo} ocupadas={ocupadas} claseOcupada={estilo.silla} horizontal />
            </button>

            {puedeCambiarEstado && acciones.length > 0 && (
                <Menu as="div" className="absolute top-5 right-5">
                    <MenuButton className="grid h-8 w-8 place-items-center rounded-lg text-slate-500 hover:bg-white hover:text-slate-900" aria-label={`Opciones de la mesa ${mesa.codigo}`}>
                        <MoreHorizontal className="h-4 w-4" aria-hidden="true" />
                    </MenuButton>
                    <MenuItems anchor="bottom end" className="z-30 mt-1 w-48 rounded-xl bg-white p-1 shadow-[var(--shadow-flotante)] ring-1 ring-slate-900/5 focus:outline-none">
                        {acciones.map((accion) => {
                            const IconoAccion = ESTILO[accion.estado]?.icono;
                            return (
                                <MenuItem key={accion.estado + accion.etiqueta}>
                                    <button type="button" onClick={() => onCambiarEstado(mesa, accion.estado)} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-700 data-focus:bg-slate-100">
                                        {IconoAccion && <IconoAccion className="h-4 w-4 text-slate-400" aria-hidden="true" />}{accion.etiqueta}
                                    </button>
                                </MenuItem>
                            );
                        })}
                    </MenuItems>
                </Menu>
            )}
        </div>
    );
}

export { ESTILO as ESTILO_MESA };
