import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import IconoTile from './IconoTile';
import { cx } from './cx';
import { iconoDeRuta } from '@/modules';

/**
 * Encabezado de página: migas, ícono del módulo, título, descripción y acciones.
 * `breadcrumbs` acepta [{ label, href? }]. Si no se pasa `icon`, se usa el del módulo
 * en el menú según la URL actual; `icon={false}` lo oculta.
 */
export default function PageHeader({ title, description, breadcrumbs = [], actions, meta, icon, className }) {
    const { url } = usePage();
    const icono = icon === false ? null : icon ?? iconoDeRuta(url);
    return (
        <div className={cx('flex flex-wrap items-end justify-between gap-x-6 gap-y-3', className)}>
            <div className="flex min-w-0 items-center gap-3.5">
                {icono && <IconoTile icon={icono} tone="solido" size="lg" className="shadow-sm" />}
                <div className="min-w-0">
                    {breadcrumbs.length > 0 && (
                        <nav aria-label="Ruta de navegación" className="mb-0.5">
                            <ol className="flex flex-wrap items-center gap-1 text-xs text-slate-500">
                                {breadcrumbs.map((crumb, index) => (
                                    <li key={crumb.label} className="flex items-center gap-1">
                                        {index > 0 && <ChevronRight className="h-3 w-3 text-slate-400" aria-hidden="true" />}
                                        {crumb.href ? (
                                            <Link href={crumb.href} className="hover:text-slate-900 hover:underline">{crumb.label}</Link>
                                        ) : (
                                            <span aria-current={index === breadcrumbs.length - 1 ? 'page' : undefined}>{crumb.label}</span>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        </nav>
                    )}
                    <h1 className="text-xl font-semibold tracking-tight text-slate-900">{title}</h1>
                    {description && <p className="mt-0.5 max-w-3xl text-sm text-slate-500">{description}</p>}
                </div>
            </div>
            {(actions || meta) && (
                <div className="flex flex-wrap items-center gap-2">
                    {meta && <span className="text-xs text-slate-500">{meta}</span>}
                    {actions}
                </div>
            )}
        </div>
    );
}
