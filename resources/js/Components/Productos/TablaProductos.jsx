import { Link } from '@inertiajs/react';
import { Eye, Layers, Pencil, Trash2 } from 'lucide-react';
import { Badge, Button, IconButton, Paginacion, StatusBadge, Table, TableEmpty, TBody, THead, Td, Th, ThOrdenable, Tr, buttonClasses } from '@/Components/ui';
import useTablaLocal from '@/hooks/useTablaLocal';

const CAMPOS_ORDEN = {
    codigo: (p) => p.codigo_barras || p.codigo_sunat || '',
    nombre: 'nombre',
    categoria: (p) => p.categoria?.nombre || '',
    estado: (p) => p.estado !== false,
};

/** Listado ordenable y paginado de productos con accesos a ficha, empaques, edición y desactivación. */
export default function TablaProductos({ productos, total, onEmpaques, onEditar, onDesactivar, hayFiltros }) {
    const tabla = useTablaLocal(productos, { campos: CAMPOS_ORDEN, porPagina: 30 });
    const ordenable = { orden: tabla.orden, onOrdenar: tabla.ordenarPor };

    return (
        <>
        <Table caption="Productos">
            <THead>
                <ThOrdenable columna="codigo" {...ordenable}>Código</ThOrdenable>
                <ThOrdenable columna="nombre" {...ordenable}>Producto</ThOrdenable>
                <ThOrdenable columna="categoria" {...ordenable} className="hidden md:table-cell">Categoría y marca</ThOrdenable>
                <Th className="hidden lg:table-cell">Unidad base</Th>
                <ThOrdenable columna="estado" {...ordenable}>Estado</ThOrdenable>
                <Th align="right"><span className="sr-only">Acciones</span></Th>
            </THead>
            <TBody>
                {tabla.visibles.length > 0 ? (
                    tabla.visibles.map((producto) => (
                        <Tr key={producto.id_producto}>
                            <Td className="font-mono text-xs text-slate-600">{producto.codigo_barras || producto.codigo_sunat || <span className="text-slate-400">Sin código</span>}</Td>
                            <Td>
                                <p className="font-medium text-slate-900">{producto.nombre}</p>
                                {producto.es_servicio && (
                                    <p className="mt-0.5 flex gap-1">
                                        <Badge tone="info">Servicio</Badge>
                                    </p>
                                )}
                            </Td>
                            <Td className="hidden md:table-cell">
                                <span className="text-slate-900">{producto.categoria?.nombre || 'General'}</span>
                                {producto.marca?.nombre && <span className="block text-xs text-slate-500">{producto.marca.nombre}</span>}
                            </Td>
                            <Td className="hidden lg:table-cell">{producto.unidad_base?.nombre || 'UNIDAD'}</Td>
                            <Td><StatusBadge active={producto.estado !== false} /></Td>
                            <Td align="right">
                                <div className="flex items-center justify-end gap-1">
                                    <Link href={`/productos/${producto.id_producto}`} aria-label={`Ver ficha de ${producto.nombre}`} title="Ver ficha" className={buttonClasses({ variant: 'ghost', size: 'sm', className: 'w-8 px-0' })}>
                                        <Eye className="h-4 w-4" aria-hidden="true" />
                                    </Link>
                                    <Button variant="secondary" size="sm" icon={Layers} onClick={() => onEmpaques(producto)} aria-label={`Empaques de ${producto.nombre}`}>
                                        {producto.presentaciones?.length || 0}
                                    </Button>
                                    <IconButton icon={Pencil} label={`Editar ${producto.nombre}`} onClick={() => onEditar(producto)} />
                                    <IconButton icon={Trash2} tone="danger" label={`Desactivar ${producto.nombre}`} onClick={() => onDesactivar(producto)} />
                                </div>
                            </Td>
                        </Tr>
                    ))
                ) : (
                    <TableEmpty colSpan={6} title={hayFiltros ? 'Sin coincidencias' : 'Aún no hay productos'} description={hayFiltros ? 'Prueba con otros filtros.' : 'Usa «Nuevo producto» para registrar el primero.'} />
                )}
            </TBody>
        </Table>
        <Paginacion paginacion={tabla.paginacion} entidad={productos.length === total ? 'productos' : `coincidencias (de ${total})`} />
        </>
    );
}
