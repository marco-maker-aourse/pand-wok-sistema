import { useRemember } from '@inertiajs/react';
import CatalogoPagina, { AccionesFila, SugerenciasRapidas } from '@/Components/Catalogo/CatalogoPagina';
import useCatalogoCrud, { normalizarColeccion, useFiltroTexto } from '@/Components/Catalogo/useCatalogoCrud';
import { CheckboxField, Field, Input, StatusBadge, Td, Tr } from '@/Components/ui';

const CAMPOS_BUSQUEDA = ['nombre'];
const COLUMNAS = [{ label: 'Marca', campo: 'nombre' }, { label: 'Estado', campo: 'estado' }];
const SUGERENCIAS = ['GLORIA', 'ALICORP', 'NESTLÉ', 'PRIMOR', 'MOLITALIA', 'SAN JORGE', 'COSTEÑO', 'BACKUS', 'PROCTER & GAMBLE', 'COLGATE-PALMOLIVE'].map((etiqueta) => ({ etiqueta }));

export default function Index({ marcas = [] }) {
    const lista = normalizarColeccion(marcas);
    const [busqueda, setBusqueda] = useRemember('', 'busqueda:marcas');
    const filtradas = useFiltroTexto(lista, busqueda, CAMPOS_BUSQUEDA);

    const crud = useCatalogoCrud({
        endpoint: '/marcas',
        getId: (marca) => marca.id_marca,
        valoresIniciales: { nombre: '', estado: true },
        aFormulario: (marca) => ({ nombre: (marca.nombre || '').toUpperCase(), estado: Boolean(marca.estado) }),
        validar: (data) => (!data.nombre.trim() ? { campo: 'nombre', mensaje: 'Ingresa el nombre de la marca.' } : null),
        mensajes: {
            guardado: (data, esEdicion) => ({ titulo: esEdicion ? 'Marca actualizada' : 'Marca registrada', mensaje: data.nombre.trim() }),
            eliminado: (marca) => ({ titulo: 'Marca desactivada', mensaje: `${marca.nombre} ya no se ofrecerá al registrar productos.` }),
            errorEliminar: 'No se pudo cambiar el estado de la marca.',
        },
    });
    const { form, dialogo, eliminacion } = crud;

    return (
        <CatalogoPagina
            titulo="Marcas"
            descripcion="Marcas de fabricantes y proveedores para clasificar productos, compras e inventario."
            entidad="marca"
            femenino
            crud={crud}
            busqueda={busqueda}
            onBusqueda={setBusqueda}
            placeholderBusqueda="Buscar marca"
            columnas={COLUMNAS}
            registros={filtradas}
            total={lista.length}
            mensajeEliminar={(marca) => <>La marca <strong>{marca.nombre}</strong> dejará de aparecer al registrar productos. El historial de existencias y comprobantes se conserva.</>}
            renderFila={(marca) => (
                <Tr key={marca.id_marca}>
                    <Td className="font-medium text-slate-900">{marca.nombre}</Td>
                    <Td><StatusBadge active={marca.estado ?? true} activeLabel="Activa" inactiveLabel="Inactiva" /></Td>
                    <Td align="right">
                        <AccionesFila nombre={marca.nombre} onEditar={() => dialogo.abrirEdicion(marca)} onEliminar={() => eliminacion.solicitar(marca)} />
                    </Td>
                </Tr>
            )}
            renderFormulario={() => (
                <>
                    <Field label="Nombre de la marca" error={form.errors.nombre} required>
                        <Input value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value.toUpperCase())} placeholder="EJ. GLORIA" autoFocus />
                    </Field>
                    {!dialogo.editando && <SugerenciasRapidas titulo="Marcas frecuentes" opciones={SUGERENCIAS} onElegir={(s) => form.setData('nombre', s.etiqueta)} />}
                    <CheckboxField label="Marca activa" description="Disponible al crear productos o registrar entradas." checked={form.data.estado} onChange={(valor) => form.setData('estado', valor)} />
                </>
            )}
        />
    );
}
