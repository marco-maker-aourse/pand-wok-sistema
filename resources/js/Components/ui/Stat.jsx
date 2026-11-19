import IconoTile from './IconoTile';
import FormaTubo from './FormaTubo';
import { cx } from './cx';

const VALOR = {
    default: 'text-slate-900',
    brand: 'text-brand-ink',
    success: 'text-emerald-700',
    warning: 'text-amber-700',
    danger: 'text-rose-600',
    info: 'text-sky-700',
};

/**
 * Indicador numérico con ícono tonal. `destacado` usa el tono suave de la marca
 * (primer indicador del tablero); `extra` se muestra junto al valor (p. ej. una variación).
 */
export default function Stat({ label, value, hint, icon, tone = 'default', iconTone, destacado = false, extra, className }) {
    return (
        <div className={cx('elevable relative overflow-hidden rounded-2xl p-5 ring-1', destacado ? 'bg-brand-soft ring-brand-border' : 'bg-white shadow-[var(--shadow-tarjeta)] ring-slate-900/[0.05]', className)}>
            {destacado && (
                <FormaTubo d="M150 40 A70 70 0 1 0 190 150" viewBox="0 0 240 240" ancho={30} className="pointer-events-none absolute -right-8 -bottom-10 w-32 opacity-50" />
            )}
            <div className="relative flex items-start justify-between gap-3">
                <p className="text-sm font-medium text-slate-600">{label}</p>
                {icon && <IconoTile icon={icon} tone={destacado ? 'solido' : iconTone ?? (tone === 'default' ? 'brand' : tone)} size="md" />}
            </div>
            <div className="relative mt-3 flex items-end justify-between gap-3">
                <p className={cx('text-[1.625rem] leading-none font-semibold tracking-[-0.02em] tabular-nums', VALOR[tone])}>{value}</p>
                {extra}
            </div>
            {hint && <p className="relative mt-2 text-xs text-slate-500">{hint}</p>}
        </div>
    );
}
