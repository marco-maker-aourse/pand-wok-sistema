import { useRemember } from '@inertiajs/react';
import CatalogoPagina, { AccionesFila, SugerenciasRapidas } from '@/Components/Catalogo/CatalogoPagina';
import useCatalogoCrud, { normalizarColeccion, useFiltroTexto } from '@/Components/Catalogo/useCatalogoCrud';
import { Badge, Field, Input, Td, Tr } from '@/Components/ui';

const CAMPOS_BUSQUEDA = ['nombre', 'codigo_sunat'];
const COLUMNAS = [{ label: 'Unidad de medida', campo: 'nombre' }, { label: 'Código SUNAT', campo: 'codigo_sunat' }];
const CATALOGO_SUNAT = [
    { etiqueta: 'UNIDAD', codigo: 'NIU', detalle: 'Bienes individuales y empaques base' },
    { etiqueta: 'KILOGRAMO', codigo: 'KGM', detalle: 'Pesaje y venta a granel' },
    { etiqueta: 'LITRO', codigo: 'LTR', detalle: 'Líquidos, bebidas y aceites' },
    { etiqueta: 'CAJA', codigo: 'BX', detalle: 'Empaques comerciales' },
    { etiqueta: 'BOLSA', codigo: 'BG', detalle: 'Granos, harinas o azúcar' },
    { etiqueta: 'METRO', codigo: 'MTR', detalle: 'Medición de longitud' },
    { etiqueta: 'GALÓN', codigo: 'GLL', detalle: 'Volumen comercial' },
    { etiqueta: 'PAQUETE', codigo: 'PK', detalle: 'Agrupaciones de artículos' },
];

const idUnidad = (unidad) => unidad.id_unidad ?? unidad.id;

export default function Index({ unidades = [], units = [], filters = {} }) {
    const lista = normalizarColeccion(unidades, units);
    const [busqueda, setBusqueda] = useRemember(filters?.buscar ?? '', 'busqueda:unidades');
    const filtradas = useFiltroTexto(lista, busqueda, CAMPOS_BUSQUEDA);

    const crud = useCatalogoCrud({
        endpoint: '/unidades',
        getId: idUnidad,
        valoresIniciales: { nombre: '', codigo_sunat: '' },
        aFormulario: (unidad) => ({ nombre: (unidad.nombre ?? '').toUpperCase(), codigo_sunat: (unidad.codigo_sunat ?? '').toUpperCase() }),
        validar: (data) => (!data.nombre.trim() ? { campo: 'nombre', mensaje: 'Ingresa el nombre de la unidad de medida.' } : null),
        mensajes: {
            guardado: (data, esEdicion) => ({
                titulo: esEdicion ? 'Unidad actualizada' : 'Unidad registrada',
                mensaje: `${data.nombre.trim()} (${data.codigo_sunat.trim() || 'sin código SUNAT'})`,
            }),
            eliminado: (unidad) => ({ titulo: 'Unidad eliminada', mensaje: `${unidad.nombre} se retiró del catálogo.` }),
            errorEliminar: 'No se puede eliminar la unidad porque está vinculada a productos o movimientos de kardex.',
        },
    });
    const { form, dialogo, eliminacion } = crud;

    return (
        <CatalogoPagina
            titulo="Unidades de medida"
            descripcion="Unidades base para controlar existencias en kardex y para la facturación electrónica."
            entidad="unidad"
            femenino
            crud={crud}
            busqueda={busqueda}
            onBusqueda={setBusqueda}
            placeholderBusqueda="Buscar por nombre o código SUNAT"
            columnas={COLUMNAS}
            registros={filtradas}
            total={lista.length}
            etiquetaEliminar="Eliminar"
            mensajeEliminar={(unidad) => <>Se eliminará <strong>{unidad.nombre}</strong>. Si está vinculada a productos o movimientos, el sistema no lo permitirá.</>}
            renderFila={(unidad) => (
                <Tr key={idUnidad(unidad)}>
                    <Td className="font-medium text-slate-900">{unidad.nombre}</Td>
                    <Td>{unidad.codigo_sunat ? <Badge className="font-mono">{unidad.codigo_sunat}</Badge> : <span className="text-slate-400">Sin código</span>}</Td>
                    <Td align="right">
                        <AccionesFila nombre={unidad.nombre} etiquetaEliminar="Eliminar" onEditar={() => dialogo.abrirEdicion(unidad)} onEliminar={() => eliminacion.solicitar(unidad)} />
                    </Td>
                </Tr>
            )}
            renderFormulario={() => (
                <>
                    <div className="grid grid-cols-3 gap-3">
                        <Field label="Nombre" error={form.errors.nombre} required className="col-span-2">
                            <Input value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value.toUpperCase())} placeholder="EJ. KILOGRAMO" autoFocus />
                        </Field>
                        <Field label="Código SUNAT" error={form.errors.codigo_sunat}>
                            <Input className="font-mono" maxLength={6} value={form.data.codigo_sunat} onChange={(e) => form.setData('codigo_sunat', e.target.value.toUpperCase())} placeholder="KGM" />
                        </Field>
                    </div>
                    {!dialogo.editando && (
                        <SugerenciasRapidas
                            titulo="Catálogo SUNAT frecuente"
                            opciones={CATALOGO_SUNAT}
                            onElegir={(s) => form.setData({ nombre: s.etiqueta, codigo_sunat: s.codigo })}
                        />
                    )}
                </>
            )}
        />
    );
}
