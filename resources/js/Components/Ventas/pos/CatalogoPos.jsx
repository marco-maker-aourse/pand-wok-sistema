import { Deferred, Link } from '@inertiajs/react';
import { Coins, Loader2, PackageSearch, Plus, Search } from 'lucide-react';
import { Badge, Button, Kbd, Skeleton, buttonClasses, cx } from '@/Components/ui';
import { formatoMoneda } from './formato';

function TarjetaProducto({ producto, onAgregar }) {
    const agotado = !producto.es_servicio && Number(producto.stock_actual) <= 0;
    const presentacionMinorista = producto.presentaciones.find((p) => Number(p.factor_conversion) === 1);
    const tieneMayorista = producto.presentaciones.some((p) => Number(p.factor_conversion) > 1);
    const precioDesde = presentacionMinorista?.precio_venta ?? producto.precio_venta;

    return (
        <button
            type="button"
            disabled={agotado}
            onClick={() => onAgregar(producto)}
            aria-label={`Agregar ${producto.nombre}, ${formatoMoneda.format(Number(precioDesde || 0))}`}
            className={cx(
                'group flex min-h-32 flex-col justify-between rounded-xl p-3 text-left ring-1',
                agotado ? 'cursor-not-allowed bg-slate-50 opacity-60 ring-slate-200' : 'elevable bg-white shadow-[var(--shadow-tarjeta)] ring-slate-900/[0.06] hover:ring-brand-border active:bg-brand-soft',
            )}
        >
            <span className="block">
                <span className="flex items-start gap-2.5">
                    {/* Inicial del producto: el catálogo no tiene imágenes. */}
                    <span aria-hidden="true" className={cx('grid h-9 w-9 shrink-0 place-items-center rounded-lg text-sm font-semibold ring-1', agotado ? 'bg-slate-100 text-slate-400 ring-slate-200' : 'volumen-suave text-brand-ink ring-brand-border')}>
                        {(producto.nombre || '?').trim().charAt(0).toUpperCase()}
                    </span>
                    <span className={cx('line-clamp-2 text-sm leading-snug font-medium', agotado ? 'text-slate-400' : 'text-slate-900')}>{producto.nombre}</span>
                </span>
                <span className="mt-2 flex flex-wrap items-center gap-1">
                    {producto.es_servicio ? (
                        <Badge tone="info">Servicio</Badge>
                    ) : agotado ? (
                        <Badge tone="danger">Agotado</Badge>
                    ) : (
                        <Badge className="tabular-nums">Stock {producto.stock_actual}</Badge>
                    )}
                    {tieneMayorista && !agotado && <Badge tone="warning">Mayorista</Badge>}
                </span>
            </span>
            <span className="mt-3 flex items-end justify-between border-t border-slate-100 pt-2">
                <span>
                    <span className="block text-sm font-semibold text-slate-900 tabular-nums">{formatoMoneda.format(Number(precioDesde || 0))}</span>
                    <span className="block text-xs text-slate-500">Precio detalle</span>
                </span>
                {!agotado && (
                    <span className="volumen-marca grid h-8 w-8 place-items-center rounded-lg text-on-brand transition-[filter] group-hover:brightness-110" aria-hidden="true">
                        <Plus className="h-4 w-4" />
                    </span>
                )}
            </span>
        </button>
    );
}

/** Panel izquierdo del POS: búsqueda y catálogo de productos. */
function SkeletonCatalogo() {
    return (
        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 2xl:grid-cols-4" role="status" aria-label="Cargando catálogo">
            {Array.from({ length: 8 }, (_, i) => (
                <div key={i} className="space-y-3 rounded-lg border border-slate-200 p-3">
                    <Skeleton className="h-4 w-4/5" />
                    <Skeleton className="h-4 w-1/3" />
                    <Skeleton className="mt-4 h-5 w-1/2" />
                </div>
            ))}
        </div>
    );
}

export default function CatalogoPos({ productos, busqueda, onBusqueda, buscando, turnoAbierto, onAbrirCaja, onAgregar, onEnter, className }) {
    return (
        <section aria-label="Catálogo de productos" className={cx('flex flex-1 flex-col overflow-hidden rounded-2xl bg-white shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]', className)}>
            <div className="flex items-center gap-2 border-b border-slate-200 p-3">
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <input
                        type="search"
                        id="buscador_producto"
                        aria-label="Buscar producto por nombre o código de barras"
                        placeholder="Buscar por nombre o código de barras"
                        className="block w-full pr-9 pl-9 text-sm"
                        value={busqueda}
                        onChange={(e) => onBusqueda(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                onEnter?.();
                            } else if (e.key === 'Escape' && busqueda) {
                                e.preventDefault();
                                onBusqueda('');
                            }
                        }}
                        aria-keyshortcuts="F2"
                        autoFocus
                    />
                    {buscando ? (
                        <Loader2 className="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin text-slate-400" aria-label="Buscando" />
                    ) : (
                        <Kbd className="pointer-events-none absolute top-1/2 right-2.5 hidden -translate-y-1/2 sm:inline">F2</Kbd>
                    )}
                </div>
                {turnoAbierto ? (
                    <Link href="/caja" className={buttonClasses({ variant: 'secondary' })}>
                        <span className="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true" />
                        Caja abierta
                    </Link>
                ) : (
                    <Button icon={Coins} onClick={onAbrirCaja}>Abrir caja</Button>
                )}
            </div>

            <div className="flex-1 overflow-y-auto p-3 [scrollbar-width:thin]">
                <Deferred data="productosIniciales" fallback={<SkeletonCatalogo />}>
                    {productos.length > 0 ? (
                        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 2xl:grid-cols-4">
                            {productos.map((producto) => <TarjetaProducto key={producto.id_producto} producto={producto} onAgregar={onAgregar} />)}
                        </div>
                    ) : (
                        <div className="flex h-64 flex-col items-center justify-center text-center">
                            <PackageSearch className="mb-2 h-6 w-6 text-slate-400" aria-hidden="true" />
                            <p className="text-sm font-medium text-slate-700">No se encontraron productos</p>
                            <p className="mt-0.5 text-xs text-slate-500">Prueba con otro nombre o código.</p>
                        </div>
                    )}
                </Deferred>
            </div>
        </section>
    );
}
