import { cx } from './cx';

const TONOS = {
    brand: 'volumen-suave text-brand-ink ring-brand-border',
    solido: 'volumen-marca text-on-brand ring-white/20',
    success: 'bg-emerald-50 text-emerald-600 ring-emerald-100',
    warning: 'bg-amber-50 text-amber-600 ring-amber-100',
    danger: 'bg-rose-50 text-rose-600 ring-rose-100',
    info: 'bg-sky-50 text-sky-600 ring-sky-100',
    violet: 'bg-violet-50 text-violet-600 ring-violet-100',
    neutral: 'bg-slate-100 text-slate-500 ring-slate-200',
};

const TAMANOS = {
    sm: ['h-7 w-7 rounded-md', 'h-3.5 w-3.5'],
    md: ['h-9 w-9 rounded-lg', 'h-4 w-4'],
    lg: ['h-11 w-11 rounded-xl', 'h-5 w-5'],
};

/** Ícono sobre fondo tonal: ancla visual de encabezados, indicadores y estados. */
export default function IconoTile({ icon: Icon, tone = 'brand', size = 'md', className }) {
    const [caja, icono] = TAMANOS[size];
    return (
        <span className={cx('inline-grid shrink-0 place-items-center ring-1 ring-inset', caja, TONOS[tone], className)} aria-hidden="true">
            <Icon className={icono} />
        </span>
    );
}
