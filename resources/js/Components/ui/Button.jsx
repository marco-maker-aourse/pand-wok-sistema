import { forwardRef } from 'react';
import { Link } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { cx } from './cx';

const VARIANTES = {
    primary: 'volumen-marca text-on-brand hover:brightness-105 active:brightness-95 disabled:bg-none disabled:bg-slate-300 disabled:text-slate-500 disabled:shadow-none',
    secondary: 'border border-slate-200 bg-white text-slate-700 shadow-[inset_0_-1px_0_rgb(15_23_42/0.04),0_1px_2px_rgb(15_23_42/0.06)] hover:bg-slate-50 hover:text-slate-900 disabled:text-slate-400',
    ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 disabled:text-slate-400',
    danger: 'bg-rose-600 text-white hover:bg-rose-700 disabled:bg-rose-300',
    'danger-ghost': 'text-rose-600 hover:bg-rose-50 disabled:text-rose-300',
};

const TAMANOS = {
    sm: 'h-8 gap-1.5 px-2.5 text-xs',
    md: 'h-9 gap-2 px-3.5 text-sm',
    lg: 'h-11 gap-2 px-5 text-sm',
};

export function buttonClasses({ variant = 'primary', size = 'md', block = false, className = '' } = {}) {
    return cx(
        'inline-flex shrink-0 items-center justify-center rounded-lg font-semibold whitespace-nowrap transition-[filter,background-color,transform] active:translate-y-px',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed',
        VARIANTES[variant],
        TAMANOS[size],
        block && 'w-full',
        className,
    );
}

/**
 * Botón base del sistema. `icon` se muestra a la izquierda y se reemplaza por
 * un indicador mientras `loading` es verdadero. Con `href` se renderiza como Link.
 */
const Button = forwardRef(function Button(
    { variant, size, block, loading = false, icon: Icon, href, className, children, disabled, type = 'button', ...props },
    ref,
) {
    const classes = buttonClasses({ variant, size, block, className });
    const iconSize = size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4';
    const contenido = (
        <>
            {loading ? <Loader2 className={cx(iconSize, 'animate-spin')} aria-hidden="true" /> : Icon && <Icon className={iconSize} aria-hidden="true" />}
            {children}
        </>
    );

    if (href) {
        return <Link ref={ref} href={href} className={classes} {...props}>{contenido}</Link>;
    }

    return (
        <button ref={ref} type={type} className={classes} disabled={disabled || loading} aria-busy={loading || undefined} {...props}>
            {contenido}
        </button>
    );
});

export default Button;
