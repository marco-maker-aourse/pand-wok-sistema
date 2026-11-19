import { useMemo } from 'react';
import { Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, Card, ConfirmDialog, Dialog, IconButton, PageHeader, Paginacion, SearchInput, Table, TableEmpty, TBody, THead, Th, ThOrdenable, cx } from '@/Components/ui';
import useTablaLocal from '@/hooks/useTablaLocal';

/**
 * Estructura común de los catálogos: encabezado con acción principal,
 * tabla filtrable, ordenable y paginada, diálogo de formulario y confirmación.
 * Las columnas con `campo` se pueden ordenar.
 */
export default function CatalogoPagina({
    titulo,
    descripcion,
    seccion = 'Catálogos',
    entidad,
    femenino = false,
    crud,
    busqueda,
    onBusqueda,
    placeholderBusqueda,
    columnas,
    registros,
    total,
    renderFila,
    renderFormulario,
    mensajeEliminar,
    etiquetaEliminar = 'Desactivar',
    anchoFormulario = 'md',
}) {
    const { form, dialogo, eliminacion } = crud;
    const nuevo = `${femenino ? 'Nueva' : 'Nuevo'} ${entidad}`;
    const campos = useMemo(() => Object.fromEntries(columnas.filter((c) => c.campo).map((c) => [c.label, c.campo])), [columnas]);
    const tabla = useTablaLocal(registros, { campos });

    return (
        <AuthenticatedLayout header={titulo}>
            <Head title={titulo} />

            <div className="space-y-4">
                <PageHeader
                    breadcrumbs={[{ label: seccion }, { label: titulo }]}
                    title={titulo}
                    description={descripcion}
                    actions={<Button icon={Plus} onClick={() => dialogo.abrirNuevo()}>{nuevo}</Button>}
                />

                <Card>
                    <div className="flex flex-wrap items-center gap-3 border-b border-slate-200 p-3">
                        <SearchInput value={busqueda} onChange={onBusqueda} placeholder={placeholderBusqueda} className="w-full sm:max-w-xs" />
                    </div>
                    <Table caption={titulo}>
                        <THead>
                            {columnas.map((col) => col.campo ? (
                                <ThOrdenable key={col.label} columna={col.label} orden={tabla.orden} onOrdenar={tabla.ordenarPor} align={col.align} className={col.className}>{col.label}</ThOrdenable>
                            ) : (
                                <Th key={col.label} align={col.align} className={col.className}>{col.label}</Th>
                            ))}
                            <Th align="right"><span className="sr-only">Acciones</span></Th>
                        </THead>
                        <TBody>
                            {tabla.visibles.length > 0 ? (
                                tabla.visibles.map(renderFila)
                            ) : (
                                <TableEmpty
                                    colSpan={columnas.length + 1}
                                    title={busqueda ? 'Sin coincidencias' : 'Aún no hay registros'}
                                    description={busqueda ? 'Prueba con otro término de búsqueda.' : `Usa «${nuevo}» para registrar el primero.`}
                                />
                            )}
                        </TBody>
                    </Table>
                    <Paginacion paginacion={tabla.paginacion} entidad={registros.length === total ? 'registros' : `coincidencias (de ${total})`} />
                </Card>
            </div>

            <Dialog
                open={dialogo.abierto}
                onClose={dialogo.cerrar}
                size={anchoFormulario}
                title={dialogo.editando ? `Editar ${entidad}` : nuevo}
                footer={
                    <>
                        <Button variant="secondary" onClick={dialogo.cerrar} disabled={form.processing}>Cancelar</Button>
                        <Button type="submit" form="catalogo-formulario" loading={form.processing}>
                            {dialogo.editando ? 'Guardar cambios' : 'Registrar'}
                        </Button>
                    </>
                }
            >
                <form id="catalogo-formulario" onSubmit={dialogo.guardar} className="space-y-4 p-5" noValidate>
                    {renderFormulario()}
                </form>
            </Dialog>

            <ConfirmDialog
                open={Boolean(eliminacion.registro)}
                onClose={eliminacion.cancelar}
                onConfirm={eliminacion.confirmar}
                loading={eliminacion.procesando}
                title={`¿${etiquetaEliminar} ${entidad}?`}
                confirmLabel={etiquetaEliminar}
            >
                {eliminacion.registro && mensajeEliminar(eliminacion.registro)}
            </ConfirmDialog>
        </AuthenticatedLayout>
    );
}

/** Botones de edición y desactivación de una fila. */
export function AccionesFila({ onEditar, onEliminar, nombre, etiquetaEliminar = 'Desactivar' }) {
    return (
        <div className="flex justify-end gap-1">
            <IconButton icon={Pencil} label={`Editar ${nombre}`} onClick={onEditar} />
            <IconButton icon={Trash2} tone="danger" label={`${etiquetaEliminar} ${nombre}`} onClick={onEliminar} />
        </div>
    );
}

/** Sugerencias de un clic para completar el formulario de alta. */
export function SugerenciasRapidas({ titulo = 'Sugerencias', opciones, onElegir }) {
    return (
        <div>
            <p className="mb-1.5 text-xs font-medium text-slate-500">{titulo}</p>
            <div className="flex flex-wrap gap-1.5">
                {opciones.map((opcion) => (
                    <button
                        key={opcion.etiqueta}
                        type="button"
                        title={opcion.detalle}
                        onClick={() => onElegir(opcion)}
                        className={cx('rounded-md border border-slate-200 bg-white px-2 py-1 text-xs text-slate-700 hover:border-brand-border hover:bg-brand-soft hover:text-brand-ink')}
                    >
                        {opcion.etiqueta}
                        {opcion.codigo && <span className="ml-1 font-mono text-slate-400">{opcion.codigo}</span>}
                    </button>
                ))}
            </div>
        </div>
    );
}
