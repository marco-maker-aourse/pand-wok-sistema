import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Building2 } from 'lucide-react';

/**
 * Sucursal de trabajo de la sesión. Solo se puede elegir entre las sucursales
 * que el servidor autoriza; el cambio recarga la pantalla con los datos de la
 * nueva sucursal (ventas, caja, inventario y datos generales).
 */
export default function SelectorSucursal({ sucursal }) {
    const [cambiando, setCambiando] = useState(false);
    const activa = sucursal?.activa;

    if (!activa) return null;

    const clases = 'flex h-10 max-w-[14rem] items-center gap-2 rounded-full bg-white px-3 text-sm text-slate-700 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.06]';

    if (!sucursal.puede_cambiar) {
        return (
            <span className={`hidden sm:flex ${clases}`} title="Sucursal de trabajo">
                <Building2 className="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
                <span className="truncate">{activa.nombre}</span>
            </span>
        );
    }

    const cambiar = (event) => {
        const id = Number(event.target.value);
        if (!id || id === activa.id_sucursal) return;

        router.post('/sucursal-activa', { id_sucursal: id }, {
            preserveScroll: true,
            onStart: () => setCambiando(true),
            onFinish: () => setCambiando(false),
        });
    };

    return (
        <label className={`${clases} focus-within:ring-2 focus-within:ring-brand-ring`}>
            <Building2 className="h-4 w-4 shrink-0 text-slate-400" aria-hidden="true" />
            <span className="sr-only">Sucursal de trabajo</span>
            <select
                value={activa.id_sucursal}
                onChange={cambiar}
                disabled={cambiando}
                className="min-w-0 flex-1 cursor-pointer truncate border-0 bg-transparent p-0 pr-6 text-sm focus:ring-0 disabled:cursor-wait"
            >
                {sucursal.disponibles.map((opcion) => (
                    <option key={opcion.id_sucursal} value={opcion.id_sucursal}>{opcion.nombre}</option>
                ))}
            </select>
        </label>
    );
}
