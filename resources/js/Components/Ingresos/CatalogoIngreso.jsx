import { useMemo, useState } from 'react';
import { Boxes, CupSoda, Plus, Search, Wheat } from 'lucide-react';
import { EmptyState, SearchInput, cx } from '@/Components/ui';
import { etiquetaPresentacion, unidadContenido } from './LineaIngreso';

const TIPOS = [
    { clave: 'INSUMO', etiqueta: 'Insumos', icono: Wheat },
    { clave: 'DIRECTO', etiqueta: 'Bebidas y venta directa', etiquetaCorta: 'Venta directa', icono: CupSoda },
    { clave: 'todos', etiqueta: 'Todos', icono: Boxes },
];

/**
 * Productos que se pueden comprar. Los platos preparados no se compran (consumen insumos
 * por receta), así que no aparecen.
 */
export default function CatalogoIngreso({ productos, enCompra, onAgregar }) {
    const comprables = useMemo(() => productos.filter((p) => p.tipo_producto !== 'PREPARADO'), [productos]);
    const hayInsumos = comprables.some((p) => p.tipo_producto === 'INSUMO');
    const [tipo, setTipo] = useState(hayInsumos ? 'INSUMO' : 'todos');
    const [busqueda, setBusqueda] = useState('');

    const visibles = useMemo(() => {
        const q = busqueda.toLowerCase().trim();
        return comprables.filter((p) => {
            if (q) return (p.nombre || '').toLowerCase().includes(q) || (p.codigo_barras || '').includes(q);
            return tipo === 'todos' || (p.tipo_producto ?? 'DIRECTO') === tipo;
        });
    }, [comprables, tipo, busqueda]);

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="space-y-3 border-b border-slate-100 p-3 sm:p-4">
                <SearchInput value={busqueda} onChange={setBusqueda} placeholder="Buscar insumo o producto…" label="Buscar producto para la compra" autoFocus />
                {!busqueda && (
                    <div className="-mx-1 flex gap-1 overflow-x-auto px-1 [scrollbar-width:none]" role="tablist" aria-label="Tipo de producto">
                        {TIPOS.map(({ clave, etiqueta, etiquetaCorta, icono: Icono }) => (
                            <button key={clave} type="button" role="tab" aria-selected={tipo === clave} onClick={() => setTipo(clave)}
                                className={cx('inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg px-3 text-sm font-medium transition-colors', tipo === clave ? 'bg-brand-soft text-brand-ink' : 'text-slate-600 hover:bg-slate-100')}>
                                <Icono className="h-4 w-4" aria-hidden="true" />
                                <span className="hidden sm:inline">{etiqueta}</span><span className="sm:hidden">{etiquetaCorta ?? etiqueta}</span>
                            </button>
                        ))}
                    </div>
                )}
            </div>

            <div className="flex-1 overflow-y-auto p-3 sm:p-4">
                {visibles.length === 0 ? (
                    <EmptyState icon={Search} title="No hay productos que coincidan" description="Prueba con otra palabra o crea el insumo en Productos." />
                ) : (
                    <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 xl:grid-cols-4">
                        {visibles.map((p) => {
                            const primera = p.presentaciones?.[0];
                            const veces = enCompra[p.id_producto] ?? 0;
                            return (
                                <button key={p.id_producto} type="button" onClick={() => onAgregar(p)}
                                    className={cx('group relative flex min-h-24 flex-col justify-between rounded-xl bg-white p-3 text-left ring-1 transition-[box-shadow,transform] hover:ring-brand active:scale-[0.98]', veces ? 'ring-2 ring-brand' : 'ring-slate-200')}>
                                    {veces > 0 && <span className="absolute -top-2 -right-2 grid h-6 min-w-6 place-items-center rounded-full bg-brand px-1.5 font-mono text-xs font-bold text-on-brand" aria-label={`${veces} en la compra`}>{veces}</span>}
                                    <span className="text-sm leading-snug font-medium text-slate-900">{p.nombre}</span>
                                    <span className="mt-2 flex items-end justify-between gap-2">
                                        <span className="text-xs text-slate-500">
                                            {primera ? etiquetaPresentacion(primera.presentacion?.nombre || primera.nombre, primera.factor_conversion, unidadContenido(p)) : 'Sin presentación'}
                                            {p.presentaciones?.length > 1 && <span className="block">+{p.presentaciones.length - 1} más</span>}
                                        </span>
                                        <Plus className="h-4 w-4 shrink-0 text-slate-300 group-hover:text-brand-ink" aria-hidden="true" />
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}
