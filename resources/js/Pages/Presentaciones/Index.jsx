import { useRemember } from '@inertiajs/react';
import CatalogoPagina, { AccionesFila, SugerenciasRapidas } from '@/Components/Catalogo/CatalogoPagina';
import useCatalogoCrud, { normalizarColeccion, useFiltroTexto } from '@/Components/Catalogo/useCatalogoCrud';
import { CheckboxField, Field, Input, StatusBadge, Td, Tr } from '@/Components/ui';

const CAMPOS_BUSQUEDA = ['nombre'];
const COLUMNAS = [{ label: 'Presentación', campo: 'nombre' }, { label: 'Estado', campo: 'estado' }];
const SUGERENCIAS = [
    { etiqueta: 'PORCIÓN', detalle: 'Plato servido en la carta' },
    { etiqueta: 'PORCIÓN FAMILIAR', detalle: 'Plato para compartir' },
    { etiqueta: 'BOTELLA PERSONAL', detalle: 'Bebida de 500 ml o similar' },
    { etiqueta: 'BOTELLA 1.5 L', detalle: 'Bebida para la mesa' },
    { etiqueta: 'JARRA', detalle: 'Refresco de la casa por jarra' },
    { etiqueta: 'VASO', detalle: 'Refresco o bebida por vaso' },
    { etiqueta: 'PAQUETE X 6', detalle: 'Empaque de compra de bebidas' },
    { etiqueta: 'KILOGRAMO', detalle: 'Insumo comprado a granel' },
];

const idPresentacion = (item) => item.id_presentacion ?? item.id;

export default function Index({ presentaciones = [], items = [], filters = {} }) {
    const lista = normalizarColeccion(presentaciones, items);
    const [busqueda, setBusqueda] = useRemember(filters?.buscar ?? '', 'busqueda:presentaciones');
    const filtradas = useFiltroTexto(lista, busqueda, CAMPOS_BUSQUEDA);

    const crud = useCatalogoCrud({
        endpoint: '/presentaciones',
        getId: idPresentacion,
        valoresIniciales: { nombre: '', estado: true },
        aFormulario: (item) => ({ nombre: (item.nombre ?? '').toUpperCase(), estado: Boolean(item.estado ?? true) }),
        validar: (data) => (!data.nombre.trim() ? { campo: 'nombre', mensaje: 'Ingresa el nombre de la presentación.' } : null),
        mensajes: {
            guardado: (data, esEdicion) => ({ titulo: esEdicion ? 'Presentación actualizada' : 'Presentación registrada', mensaje: data.nombre.trim() }),
            eliminado: (item) => ({ titulo: 'Presentación desactivada', mensaje: `${item.nombre} ya no aparecerá como opción en caja.` }),
            errorEliminar: 'No se pudo cambiar el estado de la presentación.',
        },
    });
    const { form, dialogo, eliminacion } = crud;

    return (
        <CatalogoPagina
            titulo="Presentaciones"
            descripcion="Formatos de empaque con los que se vende o compra un producto."
            entidad="presentación"
            femenino
            crud={crud}
            busqueda={busqueda}
            onBusqueda={setBusqueda}
            placeholderBusqueda="Buscar presentación"
            columnas={COLUMNAS}
            registros={filtradas}
            total={lista.length}
            mensajeEliminar={(item) => <>La presentación <strong>{item.nombre}</strong> dejará de ofrecerse en caja. Las ventas anteriores conservan su historial.</>}
            renderFila={(item) => (
                <Tr key={idPresentacion(item)}>
                    <Td className="font-medium text-slate-900">{item.nombre}</Td>
                    <Td><StatusBadge active={item.estado ?? true} activeLabel="Activa" inactiveLabel="Inactiva" /></Td>
                    <Td align="right">
                        <AccionesFila nombre={item.nombre} onEditar={() => dialogo.abrirEdicion(item)} onEliminar={() => eliminacion.solicitar(item)} />
                    </Td>
                </Tr>
            )}
            renderFormulario={() => (
                <>
                    <Field label="Nombre" error={form.errors.nombre} required>
                        <Input value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value.toUpperCase())} placeholder="EJ. CAJA X 12" autoFocus />
                    </Field>
                    {!dialogo.editando && <SugerenciasRapidas titulo="Formatos frecuentes" opciones={SUGERENCIAS} onElegir={(s) => form.setData('nombre', s.etiqueta)} />}
                    <CheckboxField label="Presentación activa" description="Disponible en caja y al registrar productos." checked={form.data.estado} onChange={(valor) => form.setData('estado', valor)} />
                </>
            )}
        />
    );
}
