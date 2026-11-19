import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { Building2, ChevronDown, CircleDot, PanelLeftClose, PanelLeftOpen, X } from 'lucide-react';
import { cx } from '@/Components/ui';
import { esRutaActiva } from './filtrarNavegacionPorRol';

function LogoEmpresa({ logoUrl, nombre }) {
    const [fallo, setFallo] = useState(false);
    const src = logoUrl && (logoUrl.startsWith('http') || logoUrl.startsWith('/storage') ? logoUrl : `/storage/${logoUrl}`);

    return (
        <span className="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-xl border border-slate-200 bg-white">
            {src && !fallo ? (
                <img src={src} alt={`Logo de ${nombre}`} className="h-full w-full object-contain" onError={() => setFallo(true)} />
            ) : (
                <span className="grid h-full w-full place-items-center bg-brand text-on-brand">
                    <Building2 className="h-4 w-4" aria-hidden="true" />
                </span>
            )}
        </span>
    );
}

function ItemNavegacion({ item, activo, compacto, onNavigate }) {
    const Icon = item.icon || CircleDot;

    return (
        <Link
            href={item.href}
            onClick={onNavigate}
            aria-current={activo ? 'page' : undefined}
            title={compacto ? item.label : undefined}
            className={cx(
                'group relative flex h-10 items-center gap-3 rounded-xl px-3 text-sm transition-colors duration-150',
                compacto && 'justify-center px-0',
                activo ? 'volumen-marca font-medium text-on-brand' : 'text-slate-600 hover:bg-brand-soft hover:text-brand-ink',
            )}
        >
            <Icon className={cx('h-[1.05rem] w-[1.05rem] shrink-0 transition-colors', activo ? 'text-on-brand' : 'text-slate-500 group-hover:text-brand-ink')} aria-hidden="true" />
            {compacto ? <span className="sr-only">{item.label}</span> : <span className="truncate">{item.label}</span>}
        </Link>
    );
}

export default function BarraLateral({ modulos, url, organizacion, compacto, onToggleCompacto, abiertoMovil, onCerrarMovil }) {
    const [colapsados, setColapsados] = useState({});
    const nombreEmpresa = organizacion?.nombre_comercial || organizacion?.razon_social || 'Restaurante';

    return (
        <aside
            aria-label="Navegación principal"
            className={cx(
                'fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-900/[0.06] bg-white transition-[transform,width] duration-200',
                abiertoMovil ? 'translate-x-0 shadow-xl' : '-translate-x-full',
                'lg:translate-x-0 lg:shadow-none',
                compacto ? 'lg:w-16' : 'lg:w-60',
            )}
        >
            <div className={cx('flex h-16 shrink-0 items-center gap-2.5 px-4', compacto && 'lg:justify-center lg:px-0')}>
                <LogoEmpresa logoUrl={organizacion?.logo_url || organizacion?.logo_path} nombre={nombreEmpresa} />
                <p className={cx('min-w-0 flex-1 truncate text-base font-semibold tracking-[-0.01em] text-slate-900', compacto && 'lg:hidden')}>{nombreEmpresa}</p>
                <button type="button" onClick={onCerrarMovil} aria-label="Cerrar menú" className="grid h-8 w-8 place-items-center rounded-md text-slate-500 hover:bg-slate-100 lg:hidden">
                    <X className="h-4 w-4" aria-hidden="true" />
                </button>
            </div>

            <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-3 [scrollbar-width:thin]">
                {modulos.map((grupo) => {
                    const abierto = !colapsados[grupo.label];
                    return (
                        <section key={grupo.label} aria-label={grupo.label}>
                            {compacto && <div className="mx-2 mb-2 hidden h-px bg-slate-200 lg:block" aria-hidden="true" />}
                            <button
                                type="button"
                                onClick={() => setColapsados((actual) => ({ ...actual, [grupo.label]: abierto }))}
                                aria-expanded={abierto}
                                className={cx(
                                    'mb-1 flex w-full items-center justify-between rounded px-2.5 py-1 text-[0.6875rem] font-semibold tracking-wide text-slate-400 uppercase hover:text-slate-700',
                                    compacto && 'lg:hidden',
                                )}
                            >
                                {grupo.label}
                                <ChevronDown className={cx('h-3.5 w-3.5 transition-transform', !abierto && '-rotate-90')} aria-hidden="true" />
                            </button>
                            {(abierto || compacto) && (
                                <div className="space-y-0.5">
                                    {grupo.items.map((item) => (
                                        <ItemNavegacion key={item.href} item={item} activo={esRutaActiva(url, item.href, item.exacta)} compacto={compacto} onNavigate={onCerrarMovil} />
                                    ))}
                                </div>
                            )}
                        </section>
                    );
                })}
            </nav>

            <div className="hidden shrink-0 border-t border-slate-200 p-2 lg:block">
                <button
                    type="button"
                    onClick={onToggleCompacto}
                    aria-label={compacto ? 'Expandir menú lateral' : 'Contraer menú lateral'}
                    className={cx('flex h-8 w-full items-center gap-2.5 rounded-md px-2.5 text-sm text-slate-500 hover:bg-slate-100 hover:text-slate-800', compacto && 'justify-center px-0')}
                >
                    {compacto ? <PanelLeftOpen className="h-4 w-4" aria-hidden="true" /> : <PanelLeftClose className="h-4 w-4" aria-hidden="true" />}
                    {!compacto && <span>Contraer menú</span>}
                </button>
            </div>
        </aside>
    );
}
