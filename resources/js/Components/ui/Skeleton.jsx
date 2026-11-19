import { cx } from './cx';

/** Bloque de carga con la forma aproximada del contenido, para evitar saltos de diseño. */
export default function Skeleton({ className }) {
    return <span className={cx('block animate-pulse rounded-md bg-slate-200/80', className)} aria-hidden="true" />;
}

/** Filas de tabla en carga. */
export function SkeletonFilas({ filas = 5, columnas = 4 }) {
    return (
        <div className="divide-y divide-slate-100" role="status" aria-label="Cargando">
            {Array.from({ length: filas }, (_, i) => (
                <div key={i} className="flex items-center gap-4 px-4 py-3">
                    {Array.from({ length: columnas }, (_, j) => <Skeleton key={j} className={cx('h-3.5', j === 0 ? 'w-1/3' : 'flex-1')} />)}
                </div>
            ))}
        </div>
    );
}

/** Atajo de teclado visible. */
export function Kbd({ children, className }) {
    return <kbd className={cx('rounded border border-slate-300 bg-white px-1.5 py-0.5 font-sans text-[0.6875rem] font-medium text-slate-600 shadow-[0_1px_0_rgb(203_213_225)]', className)}>{children}</kbd>;
}
