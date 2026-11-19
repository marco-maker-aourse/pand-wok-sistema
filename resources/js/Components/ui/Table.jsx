import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, ChevronsUpDown } from 'lucide-react';
import { cx } from './cx';

/** Tabla de datos densa. El contenedor se desplaza horizontalmente en pantallas angostas. */
export function Table({ className, children, caption }) {
    return (
        <div className={cx('overflow-x-auto', className)}>
            <table className="w-full border-collapse text-left text-sm">
                {caption && <caption className="sr-only">{caption}</caption>}
                {children}
            </table>
        </div>
    );
}

export function THead({ children }) {
    return (
        <thead className="sticky top-0 z-[1] border-b border-slate-100 bg-white text-xs font-medium text-slate-500">
            <tr>{children}</tr>
        </thead>
    );
}

const ALINEACION = { left: 'text-left', center: 'text-center', right: 'text-right' };

export function Th({ align = 'left', className, children, ...props }) {
    return <th scope="col" className={cx('px-4 py-2.5 font-medium whitespace-nowrap', ALINEACION[align], className)} {...props}>{children}</th>;
}

export function TBody({ children }) {
    return <tbody className="divide-y divide-slate-100 text-slate-700">{children}</tbody>;
}

export function Tr({ className, children, ...props }) {
    return <tr className={cx('transition-colors hover:bg-brand-soft/40', className)} {...props}>{children}</tr>;
}

export function Td({ align = 'left', className, children, ...props }) {
    return <td className={cx('px-4 py-3 align-middle', ALINEACION[align], className)} {...props}>{children}</td>;
}

export function TableEmpty({ colSpan, title = 'Sin resultados', description }) {
    return (
        <tr>
            <td colSpan={colSpan} className="px-4 py-12 text-center">
                <p className="text-sm font-medium text-slate-700">{title}</p>
                {description && <p className="mt-1 text-xs text-slate-500">{description}</p>}
            </td>
        </tr>
    );
}

/** Encabezado que ordena la columna; anuncia el orden con aria-sort. */
export function ThOrdenable({ columna, orden, onOrdenar, align = 'left', className, children }) {
    const activa = orden?.columna === columna;
    const Icono = !activa ? ChevronsUpDown : orden.direccion === 'asc' ? ArrowUp : ArrowDown;
    return (
        <th scope="col" aria-sort={activa ? (orden.direccion === 'asc' ? 'ascending' : 'descending') : 'none'} className={cx('px-2 py-1.5 font-medium whitespace-nowrap', ALINEACION[align], className)}>
            <button
                type="button"
                onClick={() => onOrdenar(columna)}
                className={cx('inline-flex items-center gap-1 rounded px-2 py-1 transition-colors hover:bg-slate-200/60 hover:text-slate-800', activa && 'text-brand-ink')}
            >
                {children}
                <Icono className={cx('h-3.5 w-3.5', activa ? 'opacity-100' : 'opacity-40')} aria-hidden="true" />
            </button>
        </th>
    );
}

/** Pie de paginación compacto. `paginacion` viene de useTablaLocal. */
export function Paginacion({ paginacion, entidad = 'registros' }) {
    const { pagina, totalPaginas, desde, hasta, total, irA } = paginacion;
    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 px-4 py-2.5 text-xs text-slate-500">
            <span className="tabular-nums">{total === 0 ? `0 ${entidad}` : `${desde}–${hasta} de ${total} ${entidad}`}</span>
            {totalPaginas > 1 && (
                <nav className="flex items-center gap-1" aria-label="Paginación">
                    <button type="button" onClick={() => irA(pagina - 1)} disabled={pagina === 1} aria-label="Página anterior" className="grid h-7 w-7 place-items-center rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40">
                        <ChevronLeft className="h-3.5 w-3.5" aria-hidden="true" />
                    </button>
                    <span className="px-2 tabular-nums" aria-live="polite">Página {pagina} de {totalPaginas}</span>
                    <button type="button" onClick={() => irA(pagina + 1)} disabled={pagina === totalPaginas} aria-label="Página siguiente" className="grid h-7 w-7 place-items-center rounded-md border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 disabled:opacity-40">
                        <ChevronRight className="h-3.5 w-3.5" aria-hidden="true" />
                    </button>
                </nav>
            )}
        </div>
    );
}
