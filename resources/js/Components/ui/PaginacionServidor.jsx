import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cx } from './cx';

const numero = new Intl.NumberFormat('es-PE');

/**
 * Paginación de un paginador de Laravel (`->paginate()`): usa sus propios enlaces,
 * así que respeta filtros de la URL y no carga más datos de los que envía el servidor.
 */
export default function PaginacionServidor({ paginador, entidad = 'registros' }) {
    if (!paginador || Array.isArray(paginador) || !paginador.last_page) return null;

    const { current_page: actual, last_page: ultima, from, to, total, prev_page_url: anterior, next_page_url: siguiente } = paginador;
    const paginas = (paginador.links ?? []).slice(1, -1);
    const enlace = 'grid h-8 min-w-8 place-items-center rounded-lg px-2 text-sm tabular-nums transition-colors';

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 text-sm text-slate-500">
            <span className="tabular-nums">
                {total ? `${numero.format(from ?? 0)}–${numero.format(to ?? 0)} de ${numero.format(total)} ${entidad}` : `0 ${entidad}`}
            </span>
            {ultima > 1 && (
                <nav className="flex items-center gap-1" aria-label="Paginación">
                    {anterior ? (
                        <Link href={anterior} preserveScroll preserveState className={cx(enlace, 'text-slate-600 hover:bg-slate-100')} aria-label="Página anterior">
                            <ChevronLeft className="h-4 w-4" aria-hidden="true" />
                        </Link>
                    ) : (
                        <span className={cx(enlace, 'text-slate-300')} aria-hidden="true"><ChevronLeft className="h-4 w-4" /></span>
                    )}
                    {paginas.map((pagina, i) =>
                        pagina.url ? (
                            <Link
                                key={`${pagina.label}-${i}`}
                                href={pagina.url}
                                preserveScroll
                                preserveState
                                aria-current={pagina.active ? 'page' : undefined}
                                className={cx(enlace, 'hidden sm:grid', pagina.active ? 'volumen-marca font-semibold text-on-brand' : 'text-slate-600 hover:bg-slate-100')}
                            >
                                {pagina.label}
                            </Link>
                        ) : (
                            <span key={`sep-${i}`} className="hidden px-1 text-slate-400 sm:inline" aria-hidden="true">…</span>
                        ),
                    )}
                    <span className="px-2 tabular-nums sm:hidden" aria-live="polite">{actual} / {ultima}</span>
                    {siguiente ? (
                        <Link href={siguiente} preserveScroll preserveState className={cx(enlace, 'text-slate-600 hover:bg-slate-100')} aria-label="Página siguiente">
                            <ChevronRight className="h-4 w-4" aria-hidden="true" />
                        </Link>
                    ) : (
                        <span className={cx(enlace, 'text-slate-300')} aria-hidden="true"><ChevronRight className="h-4 w-4" /></span>
                    )}
                </nav>
            )}
        </div>
    );
}
