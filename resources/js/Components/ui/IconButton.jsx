import { forwardRef } from 'react';
import { cx } from './cx';

const TONOS = {
    neutral: 'text-slate-500 hover:bg-slate-100 hover:text-slate-900',
    brand: 'text-slate-500 hover:bg-brand-soft hover:text-brand-ink',
    danger: 'text-slate-500 hover:bg-rose-50 hover:text-rose-600',
};

/** Botón solo con ícono. `label` es obligatorio: se usa como nombre accesible y tooltip. */
const IconButton = forwardRef(function IconButton({ icon: Icon, label, tone = 'neutral', size = 'md', className, type = 'button', ...props }, ref) {
    return (
        <button
            ref={ref}
            type={type}
            aria-label={label}
            title={label}
            className={cx(
                'inline-grid shrink-0 place-items-center rounded-lg transition-colors disabled:cursor-not-allowed disabled:opacity-40',
                size === 'sm' ? 'h-7 w-7' : 'h-8 w-8',
                TONOS[tone],
                className,
            )}
            {...props}
        >
            <Icon className={size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4'} aria-hidden="true" />
        </button>
    );
});

export default IconButton;
