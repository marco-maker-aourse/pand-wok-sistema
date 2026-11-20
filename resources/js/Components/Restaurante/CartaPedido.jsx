import { useMemo, useState } from 'react';
import { Ban, SlidersHorizontal } from 'lucide-react';
import { SearchInput, cx } from '@/Components/ui';
import { soles } from './formato';

const normalizar = (texto) => (texto ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/**
 * Carta táctil para tomar el pedido. Se busca por nombre o por el número del plato
 * ("el 05"), que es como suele pedirlo el cliente.
 */
export default function CartaPedido({ carta, onElegir, sugerencias = null, relacionados = [] }) {
    const [categoria, setCategoria] = useState(sugerencias?.frecuentes?.length ? 'frecuentes' : 'todas');
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

            <div className="grid flex-1 auto-rows-min grid-cols-2 gap-2.5 overflow-y-auto p-4 sm:grid-cols-3 2xl:grid-cols-4">
                {visibles.length === 0 && (
                    <p className="col-span-full py-10 text-center text-sm text-slate-500">No hay platos que coincidan.</p>
                )}
                {visibles.map((producto) => {
                    const precio = producto.presentaciones[0]?.precio_venta;
                    const conOpciones = producto.grupos.length > 0 || producto.presentaciones.length > 1;
                    return (
                        <button
                            key={producto.id_producto}
                            type="button"
                            disabled={producto.agotado}
                            onClick={() => onElegir(producto)}
                            className="group relative flex min-h-24 flex-col justify-between rounded-xl bg-white p-3 text-left ring-1 ring-slate-200 transition-[box-shadow,transform] hover:ring-brand active:scale-[0.97] disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-60"
                        >
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
                })}
            </div>
        </div>
    );
}
