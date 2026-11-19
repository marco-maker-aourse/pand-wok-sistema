import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Dialog as HDialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react';
import { CornerDownLeft, Search } from 'lucide-react';
import { Kbd, cx } from '@/Components/ui';

const normalizar = (texto) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/**
 * Acceso rápido a los módulos permitidos para el rol (Ctrl+K / ⌘K).
 * Solo navega: los permisos reales siguen validándose en el servidor.
 */
export default function PaletaComandos({ modulos, abierta, onCerrar }) {
    const [consulta, setConsulta] = useState('');
    const [activo, setActivo] = useState(0);
    const listaRef = useRef(null);

    const opciones = useMemo(() => {
        const todas = modulos.flatMap((grupo) => grupo.items.map((item) => ({ ...item, grupo: grupo.label })));
        const q = normalizar(consulta.trim());
        return q ? todas.filter((o) => normalizar(`${o.label} ${o.grupo}`).includes(q)) : todas;
    }, [modulos, consulta]);

    useEffect(() => {
        if (abierta) {
            setConsulta('');
            setActivo(0);
        }
    }, [abierta]);

    useEffect(() => setActivo(0), [consulta]);

    useEffect(() => {
        listaRef.current?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
    }, [activo]);

    const ir = (opcion) => {
        if (!opcion) return;
        onCerrar();
        router.visit(opcion.href);
    };

    const alTeclear = (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActivo((i) => Math.min(i + 1, opciones.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActivo((i) => Math.max(i - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            ir(opciones[activo]);
        }
    };

    return (
        <HDialog open={abierta} onClose={onCerrar} className="relative z-[60]">
            <DialogBackdrop transition className="fixed inset-0 bg-slate-900/40 transition-opacity duration-150 data-closed:opacity-0" />
            <div className="fixed inset-0 flex items-start justify-center p-4 pt-[12vh]">
                <DialogPanel transition className="w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl transition duration-150 data-closed:scale-[0.98] data-closed:opacity-0">
                    <DialogTitle className="sr-only">Ir a un módulo</DialogTitle>
                    <div className="flex items-center gap-2 border-b border-slate-200 px-4">
                        <Search className="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                        <input
                            autoFocus
                            value={consulta}
                            onChange={(e) => setConsulta(e.target.value)}
                            onKeyDown={alTeclear}
                            placeholder="Ir a… (ventas, caja, productos)"
                            role="combobox"
                            aria-expanded="true"
                            aria-controls="paleta-opciones"
                            aria-activedescendant={opciones[activo] ? `paleta-${opciones[activo].href}` : undefined}
                            className="!min-h-0 h-12 w-full !border-0 px-0 text-sm !shadow-none"
                        />
                        <Kbd>Esc</Kbd>
                    </div>
                    <ul id="paleta-opciones" ref={listaRef} role="listbox" className="max-h-80 overflow-y-auto p-2">
                        {opciones.length === 0 && <li className="px-3 py-6 text-center text-sm text-slate-500">Sin módulos que coincidan.</li>}
                        {opciones.map((opcion, indice) => {
                            const Icon = opcion.icon;
                            const seleccionado = indice === activo;
                            return (
                                <li
                                    key={opcion.href}
                                    id={`paleta-${opcion.href}`}
                                    role="option"
                                    aria-selected={seleccionado}
                                    onMouseMove={() => setActivo(indice)}
                                    onClick={() => ir(opcion)}
                                    className={cx('flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm', seleccionado ? 'bg-brand text-on-brand' : 'text-slate-700')}
                                >
                                    <span className={cx('grid h-7 w-7 place-items-center rounded-md', seleccionado ? 'bg-white/20' : 'bg-brand-soft text-brand-ink')}>
                                        {Icon && <Icon className="h-4 w-4" aria-hidden="true" />}
                                    </span>
                                    <span className="flex-1 truncate font-medium">{opcion.label}</span>
                                    <span className={cx('text-xs', seleccionado ? 'opacity-80' : 'text-slate-400')}>{opcion.grupo}</span>
                                    {seleccionado && <CornerDownLeft className="h-3.5 w-3.5 opacity-80" aria-hidden="true" />}
                                </li>
                            );
                        })}
                    </ul>
                </DialogPanel>
            </div>
        </HDialog>
    );
}
