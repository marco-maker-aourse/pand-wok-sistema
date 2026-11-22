import { useMemo, useState } from 'react';
import { Ban, SlidersHorizontal } from 'lucide-react';
import { SearchInput, cx } from '@/Components/ui';
import { soles } from './formato';

const MINIMO_FRECUENTES = 8;

const normalizar = (texto) => (texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/**
 * Carta táctil para tomar el pedido. Se busca por nombre o por el número del plato
 * ("el 05"), que es como suele pedirlo el cliente.
 */
export default function CartaPedido({ carta, onElegir, sugerencias = null, relacionados = [], enTicket = {}, recien = null }) {
    // "Frecuentes" abre por defecto solo cuando ya llena la pantalla; con poco historial, la carta completa.
    const [categoria, setCategoria] = useState((sugerencias?.frecuentes?.length ?? 0) >= MINIMO_FRECUENTES ? 'frecuentes' : 'todas');
    const [busqueda, setBusqueda] = useState('');
    const porId = useMemo(() => new Map(carta.productos.map((p) => [p.id_producto, p])), [carta.productos]);
    // Pestañas calculadas con pedidos reales de la sucursal; sin historial no aparecen.
    const especiales = [
        sugerencias?.frecuentes?.length && { id_categoria: 'frecuentes', nombre: 'Frecuentes', ids: sugerencias.frecuentes },
        sugerencias?.ultimos?.length && { id_categoria: 'ultimos', nombre: 'Mis últimos', ids: sugerencias.ultimos },
    ].filter(Boolean);
    const sugeridos = relacionados.map((id) => porId.get(id)).filter((p) => p && !p.agotado);

    const productos = useMemo(() => {
        const termino = normalizar(busqueda.trim());
        return carta.productos.filter((producto) => {
            if (termino) {
                const numero = (producto.codigo_carta ?? '').replace(/^0+/, '');
                return normalizar(producto.nombre).includes(termino) || (numero !== '' && numero === termino.replace(/^0+/, ''));
            }
            return categoria === 'todas' || producto.id_categoria === categoria;
        });
    }, [carta.productos, categoria, busqueda]);
    const especial = !busqueda && especiales.find((e) => e.id_categoria === categoria);
    const visibles = especial ? especial.ids.map((id) => porId.get(id)).filter(Boolean) : productos;
    // "Todas" se agrupa por categoría: los números de carta se repiten entre categorías (01 combinación, 1 menú).
    const secciones = categoria === 'todas' && !busqueda
        ? carta.categorias.map((c) => ({ clave: c.id_categoria, titulo: c.nombre, productos: visibles.filter((p) => p.id_categoria === c.id_categoria) }))
            .filter((sec) => sec.productos.length > 0)
        : [{ clave: 'lista', titulo: null, productos: visibles }];

    return (
        <div className="flex min-h-0 flex-col">
            <div className="space-y-3 border-b border-slate-100 p-4">
                <SearchInput value={busqueda} onChange={setBusqueda} placeholder="Buscar plato o número…" label="Buscar en la carta" />
                {!busqueda && (
                    <div className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" role="tablist" aria-label="Categorías">
                        {[...especiales, { id_categoria: 'todas', nombre: 'Todas' }, ...carta.categorias].map((c) => (
                            <button
                                key={c.id_categoria}
                                type="button"
                                role="tab"
                                aria-selected={categoria === c.id_categoria}
                                onClick={() => setCategoria(c.id_categoria)}
                                className={cx(
                                    'h-10 shrink-0 rounded-full px-4 text-sm font-medium ring-1 transition-colors',
                                    categoria === c.id_categoria ? 'bg-brand text-on-brand ring-brand' : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50',
                                )}
                            >
                                {c.nombre}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {sugeridos.length > 0 && (
                <div className="flex items-center gap-2 overflow-x-auto border-b border-slate-100 px-4 py-2.5" aria-label="Suele pedirse con">
                    <span className="shrink-0 text-xs font-medium text-slate-500">Suele pedirse con</span>
                    {sugeridos.map((p) => (
                        <button key={p.id_producto} type="button" onClick={() => onElegir(p)} className="h-9 shrink-0 rounded-full bg-brand-soft px-3 text-sm font-medium text-brand-ink ring-1 ring-brand/30 hover:ring-brand">
                            + {p.nombre}
                        </button>
                    ))}
                </div>
            )}

            <div className="flex-1 overflow-y-auto p-3 pb-24 sm:p-4 lg:pb-4">
                {visibles.length === 0 && <p className="py-10 text-center text-sm text-slate-500">No hay platos que coincidan.</p>}
                {secciones.map((seccion) => (
                    <section key={seccion.clave} aria-label={seccion.titulo ?? 'Platos'} className="mb-4 last:mb-0">
                        {seccion.titulo && (
                            <h3 className="sticky -top-3 z-10 -mx-3 mb-2 bg-white/95 px-3 py-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase backdrop-blur sm:-top-4 sm:-mx-4 sm:px-4">
                                {seccion.titulo}
                            </h3>
                        )}
                        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 2xl:grid-cols-4">
                            {seccion.productos.map((producto) => (
                                <TarjetaPlato key={producto.id_producto} producto={producto} cantidad={enTicket[producto.id_producto] ?? 0} recien={recien === producto.id_producto} onElegir={onElegir} />
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </div>
    );
}

/** Plato de la carta: número, nombre, precio, si tiene opciones y cuántos van en el ticket. */
function TarjetaPlato({ producto, cantidad, recien, onElegir }) {
    const precio = producto.presentaciones[0]?.precio_venta;
    const conOpciones = producto.grupos.length > 0 || producto.presentaciones.length > 1;

    return (
        <button
            type="button"
            disabled={producto.agotado}
            onClick={() => onElegir(producto)}
            className={cx('group relative flex min-h-24 flex-col justify-between rounded-xl bg-white p-3 text-left ring-1 transition-[box-shadow,transform] hover:ring-brand active:scale-[0.97] disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-60',
                cantidad > 0 ? 'ring-2 ring-brand' : 'ring-slate-200', recien && 'animate-pulse')}
        >
            {cantidad > 0 && (
                <span className="absolute -top-2 -right-2 grid h-7 min-w-7 place-items-center rounded-full bg-brand px-1.5 font-mono text-sm font-bold text-on-brand shadow" aria-label={`${cantidad} en el ticket`}>{cantidad}</span>
            )}
            <span className="flex items-start gap-1.5">
                {producto.codigo_carta && <span className="mt-0.5 shrink-0 rounded bg-slate-100 px-1 font-mono text-[0.6875rem] font-semibold text-slate-600">{producto.codigo_carta}</span>}
                <span className="text-sm leading-snug font-medium text-slate-900">{producto.nombre}</span>
            </span>
            <span className="mt-2 flex items-center justify-between gap-2">
                {producto.agotado ? (
                    <span className="inline-flex items-center gap-1 text-xs font-medium text-rose-600"><Ban className="h-3.5 w-3.5" aria-hidden="true" />Agotado</span>
                ) : (
                    <span className="font-mono text-sm font-semibold text-brand-ink tabular-nums">{soles(precio)}</span>
                )}
                {conOpciones && !producto.agotado && <SlidersHorizontal className="h-3.5 w-3.5 text-slate-400" aria-label="Tiene opciones" />}
            </span>
        </button>
    );
}
