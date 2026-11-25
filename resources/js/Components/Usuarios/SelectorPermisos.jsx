import { useMemo, useState } from 'react';
import { Check, Minus } from 'lucide-react';
import { SearchInput, cx } from '@/Components/ui';

/** Casilla de tres estados (todos, algunos, ninguno) para marcar un módulo completo. */
function CasillaModulo({ estado, onClick, etiqueta }) {
    return (
        <button type="button" role="checkbox" aria-checked={estado === 'todos' ? 'true' : estado === 'algunos' ? 'mixed' : 'false'} aria-label={etiqueta} onClick={onClick}
            className={cx('grid h-5 w-5 shrink-0 place-items-center rounded-md ring-1 transition-colors', estado === 'ninguno' ? 'bg-white ring-slate-300' : 'bg-brand text-on-brand ring-brand')}>
            {estado === 'todos' && <Check className="h-3.5 w-3.5" aria-hidden="true" />}
            {estado === 'algunos' && <Minus className="h-3.5 w-3.5" aria-hidden="true" />}
        </button>
    );
}

/**
 * Permisos agrupados por módulo, con búsqueda y marcado por módulo. Solo decide qué se
 * envía; la autorización real la aplica el servidor.
 */
export default function SelectorPermisos({ permisos, seleccionados, onCambiar }) {
    const [filtro, setFiltro] = useState('');
    const marcados = useMemo(() => new Set(seleccionados.map(Number)), [seleccionados]);

    const grupos = useMemo(() => {
        const q = filtro.toLowerCase().trim();
        return permisos
            .filter((p) => !q || (p.nombre || '').toLowerCase().includes(q) || (p.modulo || '').toLowerCase().includes(q) || (p.codigo || '').toLowerCase().includes(q))
            .reduce((acc, p) => {
                const modulo = p.modulo || 'General';
                (acc[modulo] ??= []).push(p);
                return acc;
            }, {});
    }, [permisos, filtro]);

    const alternar = (ids, marcar) => {
        const siguiente = new Set(marcados);
        ids.forEach((id) => (marcar ? siguiente.add(Number(id)) : siguiente.delete(Number(id))));
        onCambiar([...siguiente]);
    };

    const todos = permisos.map((p) => p.id_permiso);
    const estadoDe = (ids) => {
        const n = ids.filter((id) => marcados.has(Number(id))).length;
        return n === 0 ? 'ninguno' : n === ids.length ? 'todos' : 'algunos';
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-3">
                <SearchInput value={filtro} onChange={setFiltro} placeholder="Filtrar permisos…" label="Filtrar permisos" className="min-w-0 flex-1 sm:max-w-xs" />
                <div className="flex items-center gap-2 text-sm text-slate-700">
                    <CasillaModulo estado={estadoDe(todos)} etiqueta="Marcar todos los permisos" onClick={() => alternar(todos, estadoDe(todos) !== 'todos')} />
                    <span className="tabular-nums">{marcados.size} de {permisos.length}</span>
                </div>
            </div>

            {Object.keys(grupos).length === 0 ? (
                <p className="py-8 text-center text-sm text-slate-500">Ningún permiso coincide.</p>
            ) : (
                <div className="space-y-3">
                    {Object.entries(grupos).map(([modulo, items]) => {
                        const ids = items.map((p) => p.id_permiso);
                        const estado = estadoDe(ids);
                        return (
                            <section key={modulo} className="rounded-xl ring-1 ring-slate-200">
                                <header className="flex items-center gap-2.5 border-b border-slate-100 bg-slate-50/70 px-3.5 py-2.5">
                                    <CasillaModulo estado={estado} etiqueta={`Marcar todo ${modulo}`} onClick={() => alternar(ids, estado !== 'todos')} />
                                    <h3 className="flex-1 text-sm font-semibold text-slate-800">{modulo.charAt(0).toUpperCase() + modulo.slice(1).toLowerCase()}</h3>
                                    <span className="text-xs text-slate-500 tabular-nums">{ids.filter((id) => marcados.has(Number(id))).length}/{ids.length}</span>
                                </header>
                                <div className="grid gap-1 p-2 sm:grid-cols-2 lg:grid-cols-3">
                                    {items.map((p) => {
                                        const activo = marcados.has(Number(p.id_permiso));
                                        return (
                                            <label key={p.id_permiso} className={cx('flex cursor-pointer items-start gap-2.5 rounded-lg px-2.5 py-2 text-sm transition-colors', activo ? 'bg-brand-soft text-slate-900' : 'text-slate-600 hover:bg-slate-50')}>
                                                <input type="checkbox" className="mt-0.5 h-4 w-4 rounded" checked={activo} onChange={() => alternar([p.id_permiso], !activo)} />
                                                <span className="min-w-0">
                                                    <span className="block leading-snug">{p.nombre}</span>
                                                    {p.codigo && <span className="block font-mono text-[0.6875rem] text-slate-400">{p.codigo}</span>}
                                                </span>
                                            </label>
                                        );
                                    })}
                                </div>
                            </section>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
