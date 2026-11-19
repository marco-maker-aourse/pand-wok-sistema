import IconoTile from './IconoTile';
import { cx } from './cx';

/** Superficie: blanca, radio amplio, contorno muy sutil y sombra mínima (estilo del tablero). */
export function Card({ as: Tag = 'section', className, children, ...props }) {
    return (
        <Tag className={cx('rounded-2xl bg-white shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]', className)} {...props}>
            {children}
        </Tag>
    );
}

export function CardHeader({ title, description, actions, icon: Icon, className }) {
    return (
        <header className={cx('flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4', className)}>
            <div className="flex min-w-0 items-center gap-2.5">
                {Icon && <IconoTile icon={Icon} size="sm" />}
                <div className="min-w-0">
                    <h2 className="truncate text-[0.9375rem] font-semibold text-slate-900">{title}</h2>
                    {description && <p className="mt-0.5 text-xs text-slate-500">{description}</p>}
                </div>
            </div>
            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </header>
    );
}

export function CardBody({ className, children }) {
    return <div className={cx('p-5', className)}>{children}</div>;
}

export function CardFooter({ className, children }) {
    return (
        <footer className={cx('flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-3 text-xs text-slate-500', className)}>
            {children}
        </footer>
    );
}
