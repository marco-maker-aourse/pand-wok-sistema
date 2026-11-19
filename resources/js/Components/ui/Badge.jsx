import { cx } from './cx';

const TONOS = {
    neutral: 'bg-slate-100 text-slate-600 ring-slate-200',
    brand: 'bg-brand-soft text-brand-ink ring-brand-border',
    success: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    warning: 'bg-amber-50 text-amber-800 ring-amber-200',
    danger: 'bg-rose-50 text-rose-700 ring-rose-200',
    info: 'bg-sky-50 text-sky-700 ring-sky-200',
};

const PUNTOS = {
    neutral: 'bg-slate-400',
    brand: 'bg-brand',
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    danger: 'bg-rose-500',
    info: 'bg-sky-500',
};

export default function Badge({ tone = 'neutral', dot = false, icon: Icon, className, children }) {
    return (
        <span className={cx('inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset', TONOS[tone], className)}>
            {dot && <span className={cx('h-1.5 w-1.5 rounded-full', PUNTOS[tone])} aria-hidden="true" />}
            {Icon && <Icon className="h-3 w-3" aria-hidden="true" />}
            {children}
        </span>
    );
}

/** Estado activo/inactivo uniforme para todos los catálogos. */
export function StatusBadge({ active, activeLabel = 'Activo', inactiveLabel = 'Inactivo' }) {
    return (
        <Badge tone={active ? 'success' : 'neutral'} dot>
            {active ? activeLabel : inactiveLabel}
        </Badge>
    );
}
