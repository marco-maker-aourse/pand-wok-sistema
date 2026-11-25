import { useRemember } from '@inertiajs/react';
import CatalogoPagina, { AccionesFila, SugerenciasRapidas } from '@/Components/Catalogo/CatalogoPagina';
import useCatalogoCrud, { normalizarColeccion, useFiltroTexto } from '@/Components/Catalogo/useCatalogoCrud';
import { CheckboxField, Field, Input, StatusBadge, Td, Textarea, Tr } from '@/Components/ui';

const CAMPOS_BUSQUEDA = ['nombre', 'descripcion'];
const COLUMNAS = [{ label: 'Categoría', campo: 'nombre' }, { label: 'Descripción', className: 'hidden md:table-cell' }, { label: 'Estado', campo: 'estado' }];
const SUGERENCIAS = [
    { etiqueta: 'MENÚS DEL DÍA', detalle: 'Platos que incluyen sopa o entrada' },
    { etiqueta: 'PLATOS A LA CARTA', detalle: 'Fondos y especialidades de la casa' },
    { etiqueta: 'COMBINADOS', detalle: 'Platos con acompañamientos a elegir' },
    { etiqueta: 'SOPAS Y ENTRADAS', detalle: 'Sopas, piqueos y entradas frías' },
    { etiqueta: 'BEBIDAS', detalle: 'Gaseosas, aguas, jugos y refrescos' },
    { etiqueta: 'POSTRES', detalle: 'Dulces y postres de la casa' },
    { etiqueta: 'INSUMOS DE COCINA', detalle: 'Carnes, verduras, abarrotes y salsas' },
    { etiqueta: 'DESCARTABLES', detalle: 'Envases, cubiertos y bolsas para llevar' },
];

export default function Index({ categorias = [] }) {
    const lista = normalizarColeccion(categorias);
    const [busqueda, setBusqueda] = useRemember('', 'busqueda:categorias');
    const filtradas = useFiltroTexto(lista, busqueda, CAMPOS_BUSQUEDA);

    const crud = useCatalogoCrud({
        endpoint: '/categorias',
        getId: (categoria) => categoria.id_categoria,
        valoresIniciales: { nombre: '', descripcion: '', estado: true },
        aFormulario: (categoria) => ({
            nombre: (categoria.nombre || '').toUpperCase(),
            descripcion: categoria.descripcion || '',
            estado: Boolean(categoria.estado),
        }),
        validar: (data) => (!data.nombre.trim() ? { campo: 'nombre', mensaje: 'Ingresa el nombre de la categoría.' } : null),
        mensajes: {
            guardado: (data, esEdicion) => ({ titulo: esEdicion ? 'Categoría actualizada' : 'Categoría registrada', mensaje: data.nombre.trim() }),
            eliminado: (categoria) => ({ titulo: 'Categoría desactivada', mensaje: `${categoria.nombre} se retiró del catálogo activo.` }),
            errorEliminar: 'No se pudo cambiar el estado de la categoría.',
        },
    });
    const { form, dialogo, eliminacion } = crud;

    return (
        <CatalogoPagina
            titulo="Categorías"
            descripcion="Familias de productos para ordenar el catálogo comercial y los reportes."
            entidad="categoría"
            femenino
            crud={crud}
            busqueda={busqueda}
            onBusqueda={setBusqueda}
            placeholderBusqueda="Buscar categoría"
            columnas={COLUMNAS}
            registros={filtradas}
            total={lista.length}
            mensajeEliminar={(categoria) => <>La categoría <strong>{categoria.nombre}</strong> dejará de estar disponible. Los productos asociados conservan su historial.</>}
            renderFila={(categoria) => (
                <Tr key={categoria.id_categoria}>
                    <Td className="font-medium text-slate-900">{categoria.nombre}</Td>
                    <Td className="hidden max-w-md truncate text-slate-500 md:table-cell">{categoria.descripcion || '—'}</Td>
                    <Td><StatusBadge active={categoria.estado ?? true} activeLabel="Activa" inactiveLabel="Inactiva" /></Td>
                    <Td align="right">
                        <AccionesFila nombre={categoria.nombre} onEditar={() => dialogo.abrirEdicion(categoria)} onEliminar={() => eliminacion.solicitar(categoria)} />
                    </Td>
                </Tr>
            )}
            renderFormulario={() => (
                <>
                    <Field label="Nombre" error={form.errors.nombre} required>
                        <Input value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value.toUpperCase())} placeholder="EJ. ABARROTES Y GRANOS" autoFocus />
                    </Field>
                    <Field label="Descripción" error={form.errors.descripcion}>
                        <Textarea rows={2} value={form.data.descripcion} onChange={(e) => form.setData('descripcion', e.target.value)} placeholder="Productos que incluye esta familia" />
                    </Field>
                    {!dialogo.editando && (
                        <SugerenciasRapidas
                            titulo="Familias frecuentes"
                            opciones={SUGERENCIAS}
                            onElegir={(s) => form.setData((actual) => ({ ...actual, nombre: s.etiqueta, descripcion: s.detalle }))}
                        />
                    )}
                    <CheckboxField label="Categoría activa" description="Disponible al crear o editar productos." checked={form.data.estado} onChange={(valor) => form.setData('estado', valor)} />
                </>
            )}
        />
    );
}
