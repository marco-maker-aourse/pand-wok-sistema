import { Link } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import { cx } from '@/Components/ui';
import { esRutaActiva } from './filtrarNavegacionPorRol';

/** Módulos de uso diario que pueden ir en la barra inferior, en orden de prioridad. */
const RUTAS_OPERATIVAS = ['/salon', '/pos', '/cocina', '/caja', '/ventas', '/'];
const MAXIMO = 4;

/** Elige los accesos de la barra entre los módulos que el usuario ya puede ver. */
export function accesosBarraInferior(modulos) {
    const visibles = modulos.flatMap((grupo) => grupo.items);
    return RUTAS_OPERATIVAS
        .map((href) => visibles.find((item) => item.href === href))
        .filter(Boolean)
        .slice(0, MAXIMO);
}

/**
 * Navegación inferior en celular para mozos, cajeros y cocina: los módulos operativos
 * quedan a un toque con el pulgar y "Más" abre el menú completo.
 */
export default function BarraInferior({ accesos, url, onAbrirMenu }) {
    return (
        <nav
            aria-label="Accesos rápidos"
            className="fixed inset-x-0 bottom-0 z-30 border-t border-slate-900/[0.08] bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md lg:hidden"
        >
            <ul className="mx-auto grid max-w-lg" style={{ gridTemplateColumns: `repeat(${accesos.length + 1}, minmax(0, 1fr))` }}>
                {accesos.map((item) => {
                    const activo = esRutaActiva(url, item.href, item.exacta);
                    const Icono = item.icon;
                    return (
                        <li key={item.href}>
                            <Link
                                href={item.href}
                                aria-current={activo ? 'page' : undefined}
                                className={cx(
                                    'flex h-16 flex-col items-center justify-center gap-1 text-[0.6875rem] font-medium transition-colors',
                                    activo ? 'text-brand-ink' : 'text-slate-500 active:text-slate-900',
                                )}
                            >
                                <span className={cx('grid h-7 w-12 place-items-center rounded-full transition-colors', activo && 'bg-brand-soft')}>
                                    <Icono className="h-5 w-5" aria-hidden="true" />
                                </span>
                                <span className="max-w-full truncate px-1">{item.etiquetaCorta ?? item.label}</span>
                            </Link>
                        </li>
                    );
                })}
                <li>
                    <button
                        type="button"
                        onClick={onAbrirMenu}
                        className="flex h-16 w-full flex-col items-center justify-center gap-1 text-[0.6875rem] font-medium text-slate-500 active:text-slate-900"
                    >
                        <span className="grid h-7 w-12 place-items-center rounded-full"><Menu className="h-5 w-5" aria-hidden="true" /></span>
                        Más
                    </button>
                </li>
            </ul>
        </nav>
    );
}
