import { useMemo, useState } from 'react';
import { Head, useRemember } from '@inertiajs/react';
import { Plus, RotateCcw } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, Card, ConfirmDialog, NoticeDialog, PageHeader, SearchInput, Select } from '@/Components/ui';
import AsistenteProducto from '@/Components/Productos/asistente/AsistenteProducto';
import TablaProductos from '@/Components/Productos/TablaProductos';
import useFormularioProducto from '@/Components/Productos/useFormularioProducto';
import PresentacionesModal from './PresentacionesModal';

const TONO_DIALOGO = { exito: 'success', error: 'danger', alerta: 'warning' };

export default function Index({ productos = [], categorias = [], unidades = [], catalogoPresentaciones = [] }) {
    const listaProductos = useMemo(() => {
        if (Array.isArray(productos)) return productos;
        return productos?.data ?? [];
    }, [productos]);

    const [dialogo, setDialogo] = useState(null);
    const [productoEmpaques, setProductoEmpaques] = useState(null);
    const [busqueda, setBusqueda] = useRemember('', 'productos:busqueda');
    const [filtroCategoria, setFiltroCategoria] = useRemember('', 'productos:categoria');

    const f = useFormularioProducto({ categorias, unidades, catalogoPresentaciones, setDialogo });
    const { localCatalogs, modalEliminar, setModalEliminar, ejecutarEliminacion } = f;

    const productosFiltrados = useMemo(() => {
        const q = busqueda.toUpperCase().trim();
        return listaProductos.filter((p) => {
            const coincideTexto = !q || (p.nombre || '').toUpperCase().includes(q) || (p.codigo_barras || '').includes(q);
            const coincideCategoria = !filtroCategoria || String(p.id_categoria) === String(filtroCategoria);
            return coincideTexto && coincideCategoria;
        });
    }, [listaProductos, busqueda, filtroCategoria]);

    const hayFiltros = Boolean(busqueda || filtroCategoria);
    const limpiarFiltros = () => {
        setBusqueda('');
        setFiltroCategoria('');
    };

    return (
        <AuthenticatedLayout header="Productos">
            <Head title="Productos" />

            <div className="space-y-4">
                <PageHeader
                    breadcrumbs={[{ label: 'Gestión' }, { label: 'Productos' }]}
                    title="Productos"
                    description="Productos e insumos, unidades de medida, precios y presentaciones."
                    actions={<Button icon={Plus} onClick={f.openCreate}>Nuevo producto</Button>}
                />

                <Card>
                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 p-3">
                        <SearchInput value={busqueda} onChange={(valor) => setBusqueda(valor.toUpperCase())} placeholder="Buscar por nombre o código de barras" className="w-full sm:max-w-xs" />
                        <Select aria-label="Filtrar por categoría" value={filtroCategoria} onChange={(e) => setFiltroCategoria(e.target.value)} className="w-auto max-w-[14rem]">
                            <option value="">Todas las categorías</option>
                            {localCatalogs.categorias.map((c) => <option key={c.id_categoria} value={c.id_categoria}>{c.nombre}</option>)}
                        </Select>
                        {hayFiltros && <Button variant="ghost" size="sm" icon={RotateCcw} onClick={limpiarFiltros}>Limpiar filtros</Button>}
                    </div>
                    <TablaProductos
                        productos={productosFiltrados}
                        hayFiltros={hayFiltros}
                        total={listaProductos.length}
                        onEmpaques={setProductoEmpaques}
                        onEditar={f.openEdit}
                        onDesactivar={setModalEliminar}
                    />
                </Card>
            </div>

            <AsistenteProducto f={f} />

            <ConfirmDialog
                open={Boolean(modalEliminar)}
                onClose={() => setModalEliminar(null)}
                onConfirm={ejecutarEliminacion}
                title="¿Desactivar producto?"
                confirmLabel="Desactivar"
            >
                {modalEliminar && <>Se retirará <strong>{modalEliminar.nombre}</strong> del catálogo de ventas. Las ventas anteriores conservan su historial en kardex.</>}
            </ConfirmDialog>

            <NoticeDialog
                notice={dialogo && { tone: TONO_DIALOGO[dialogo.tipo] ?? 'info', title: dialogo.titulo, message: dialogo.mensaje }}
                onClose={() => setDialogo(null)}
            />

            <PresentacionesModal
                producto={productoEmpaques}
                show={Boolean(productoEmpaques)}
                onClose={() => setProductoEmpaques(null)}
                catalogoPresentaciones={f.catalogoLocalPresentaciones}
                unidades={unidades}
            />
        </AuthenticatedLayout>
    );
}
