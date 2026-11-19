import { useRemember } from '@inertiajs/react';
import CatalogoPagina, { AccionesFila } from '@/Components/Catalogo/CatalogoPagina';
import useCatalogoCrud, { useFiltroTexto } from '@/Components/Catalogo/useCatalogoCrud';
import { Badge, CheckboxField, Field, Input, Select, StatusBadge, Td, Tr } from '@/Components/ui';

const CAMPOS_BUSQUEDA = ['codigo_sunat', 'nombre', 'descripcion'];
const COLUMNAS = [
    { label: 'Código', campo: 'codigo_sunat' },
    { label: 'Documento', campo: 'nombre' },
    { label: 'Longitud', align: 'center' },
    { label: 'Tipo de persona', className: 'hidden md:table-cell' },
    { label: 'Estado' },
];
const TIPOS_PERSONA = {
    NO_APLICA: { etiqueta: 'Indistinto', tono: 'neutral' },
    NATURAL: { etiqueta: 'Natural', tono: 'info' },
    JURIDICA: { etiqueta: 'Jurídica', tono: 'brand' },
};

const nombreDocumento = (doc) => doc.nombre || doc.descripcion;

export default function Index({ tipos_documento = [] }) {
    const [busqueda, setBusqueda] = useRemember('', 'busqueda:tiposdocumento');
    const filtrados = useFiltroTexto(tipos_documento, busqueda, CAMPOS_BUSQUEDA);

    const crud = useCatalogoCrud({
        endpoint: '/tipos-documento',
        getId: (doc) => doc.id_tipo_documento,
        valoresIniciales: { codigo_sunat: '', nombre: '', longitud_exacta: '', tipo_persona: 'NO_APLICA', estado: true },
        aFormulario: (doc) => ({
            codigo_sunat: doc.codigo_sunat || '',
            nombre: nombreDocumento(doc) || '',
            longitud_exacta: doc.longitud_exacta ?? doc.longitud ?? '',
            tipo_persona: doc.tipo_persona ? doc.tipo_persona.toUpperCase() : 'NO_APLICA',
            estado: Boolean(doc.estado),
        }),
        validar: (data) => {
            if (!String(data.codigo_sunat).trim()) return { campo: 'codigo_sunat', mensaje: 'Ingresa el código SUNAT (1 para DNI, 6 para RUC).' };
            if (!data.nombre.trim()) return { campo: 'nombre', mensaje: 'Ingresa el nombre del documento.' };
            return null;
        },
        mensajes: {
            guardado: (data, esEdicion) => ({ titulo: esEdicion ? 'Documento actualizado' : 'Documento registrado', mensaje: data.nombre.trim() }),
            eliminado: (doc) => ({ titulo: 'Documento desactivado', mensaje: nombreDocumento(doc) }),
            errorEliminar: 'No se puede desactivar este documento porque está vinculado a registros existentes.',
        },
    });
    const { form, dialogo, eliminacion } = crud;

    return (
        <CatalogoPagina
            titulo="Documentos de identidad"
            seccion="Configuración"
            descripcion="Documentos válidos para clientes, proveedores y emisión electrónica según el catálogo SUNAT."
            entidad="documento"
            crud={crud}
            busqueda={busqueda}
            onBusqueda={setBusqueda}
            placeholderBusqueda="Buscar por código o nombre"
            columnas={COLUMNAS}
            registros={filtrados}
            total={tipos_documento.length}
            mensajeEliminar={(doc) => <>Se deshabilitará <strong>{nombreDocumento(doc)}</strong> (código {doc.codigo_sunat}).</>}
            renderFila={(doc) => {
                const tipo = TIPOS_PERSONA[(doc.tipo_persona || 'NO_APLICA').toUpperCase()] ?? TIPOS_PERSONA.NO_APLICA;
                const longitud = doc.longitud_exacta ?? doc.longitud;
                return (
                    <Tr key={doc.id_tipo_documento}>
                        <Td className="font-mono text-slate-900">{doc.codigo_sunat}</Td>
                        <Td className="font-medium text-slate-900">{nombreDocumento(doc)}</Td>
                        <Td align="center" className="tabular-nums">{longitud ? `${longitud} dígitos` : <span className="text-slate-400">Variable</span>}</Td>
                        <Td className="hidden md:table-cell"><Badge tone={tipo.tono}>{tipo.etiqueta}</Badge></Td>
                        <Td><StatusBadge active={Boolean(doc.estado)} /></Td>
                        <Td align="right">
                            <AccionesFila nombre={nombreDocumento(doc)} onEditar={() => dialogo.abrirEdicion(doc)} onEliminar={() => eliminacion.solicitar(doc)} />
                        </Td>
                    </Tr>
                );
            }}
            renderFormulario={() => (
                <>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Código SUNAT" error={form.errors.codigo_sunat} required>
                            <Input className="font-mono" maxLength={4} value={form.data.codigo_sunat} onChange={(e) => form.setData('codigo_sunat', e.target.value.trim())} placeholder="1 o 6" autoFocus />
                        </Field>
                        <Field label="Longitud exacta" error={form.errors.longitud_exacta} hint="Vacío si es variable.">
                            <Input
                                type="number"
                                min={1}
                                max={30}
                                className="tabular-nums"
                                value={form.data.longitud_exacta}
                                onChange={(e) => form.setData('longitud_exacta', e.target.value === '' ? '' : Number(e.target.value))}
                                placeholder="8, 11, 12…"
                            />
                        </Field>
                    </div>
                    <Field label="Nombre" error={form.errors.nombre} required>
                        <Input value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value.toUpperCase())} placeholder="DNI, RUC, CARNET DE EXTRANJERÍA…" />
                    </Field>
                    <Field label="Tipo de persona" error={form.errors.tipo_persona}>
                        <Select value={form.data.tipo_persona} onChange={(e) => form.setData('tipo_persona', e.target.value)}>
                            <option value="NO_APLICA">No aplica o indistinto</option>
                            <option value="NATURAL">Persona natural (DNI o CE)</option>
                            <option value="JURIDICA">Persona jurídica (RUC 20)</option>
                        </Select>
                    </Field>
                    {dialogo.editando && (
                        <CheckboxField label="Documento activo" description="Disponible al registrar clientes y proveedores." checked={form.data.estado} onChange={(valor) => form.setData('estado', valor)} />
                    )}
                </>
            )}
        />
    );
}
