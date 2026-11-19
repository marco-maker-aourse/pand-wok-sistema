import { useRemember } from '@inertiajs/react';
import CatalogoPagina, { AccionesFila } from '@/Components/Catalogo/CatalogoPagina';
import useCatalogoCrud, { useFiltroTexto } from '@/Components/Catalogo/useCatalogoCrud';
import { Badge, CheckboxField, Field, Input, StatusBadge, Td, Tr } from '@/Components/ui';

const CAMPOS_BUSQUEDA = ['codigo_sunat', 'descripcion'];
const COLUMNAS = [
    { label: 'Código', campo: 'codigo_sunat' },
    { label: 'Descripción', campo: 'descripcion' },
    { label: 'Exige RUC', align: 'center' },
    { label: 'Anulación', align: 'center', className: 'hidden md:table-cell' },
    { label: 'Estado' },
];

export default function Index({ tipos_comprobante = [] }) {
    const [busqueda, setBusqueda] = useRemember('', 'busqueda:tiposcomprobante');
    const filtrados = useFiltroTexto(tipos_comprobante, busqueda, CAMPOS_BUSQUEDA);

    const crud = useCatalogoCrud({
        endpoint: '/tipos-comprobante',
        getId: (item) => item.id_tipo_comprobante,
        valoresIniciales: { codigo_sunat: '', descripcion: '', exige_ruc: false, permite_anulacion: true, estado: true },
        aFormulario: (item) => ({
            codigo_sunat: item.codigo_sunat || '',
            descripcion: (item.descripcion || '').toUpperCase(),
            exige_ruc: Boolean(item.exige_ruc),
            permite_anulacion: Boolean(item.permite_anulacion),
            estado: Boolean(item.estado),
        }),
        validar: (data) => {
            if (!/^\d{2}$/.test(data.codigo_sunat.trim())) return { campo: 'codigo_sunat', mensaje: 'Debe tener exactamente 2 dígitos (01, 03, 07, 08).' };
            if (data.descripcion.trim().length < 3) return { campo: 'descripcion', mensaje: 'Ingresa al menos 3 caracteres.' };
            return null;
        },
        mensajes: {
            guardado: (data, esEdicion) => ({ titulo: esEdicion ? 'Comprobante actualizado' : 'Comprobante registrado', mensaje: data.descripcion.trim() }),
            eliminado: (item) => ({ titulo: 'Comprobante desactivado', mensaje: item.descripcion }),
            errorEliminar: 'No se puede desactivar porque tiene documentos tributarios asociados.',
        },
    });
    const { form, dialogo, eliminacion } = crud;

    return (
        <CatalogoPagina
            titulo="Tipos de comprobante"
            seccion="Configuración"
            descripcion="Comprobantes electrónicos que el sistema puede emitir según el catálogo SUNAT."
            entidad="comprobante"
            crud={crud}
            busqueda={busqueda}
            onBusqueda={setBusqueda}
            placeholderBusqueda="Buscar por código o descripción"
            columnas={COLUMNAS}
            registros={filtrados}
            total={tipos_comprobante.length}
            mensajeEliminar={(item) => <>Se deshabilitará <strong>{item.descripcion}</strong> (código {item.codigo_sunat}) para nuevas emisiones.</>}
            renderFila={(item) => (
                <Tr key={item.id_tipo_comprobante}>
                    <Td className="font-mono text-slate-900">{item.codigo_sunat}</Td>
                    <Td className="font-medium text-slate-900">{item.descripcion}</Td>
                    <Td align="center">{item.exige_ruc ? <Badge tone="warning">Sí</Badge> : <span className="text-slate-400">No</span>}</Td>
                    <Td align="center" className="hidden md:table-cell">{item.permite_anulacion ? 'Permitida' : <span className="text-slate-400">No permitida</span>}</Td>
                    <Td><StatusBadge active={Boolean(item.estado)} /></Td>
                    <Td align="right">
                        <AccionesFila nombre={item.descripcion} onEditar={() => dialogo.abrirEdicion(item)} onEliminar={() => eliminacion.solicitar(item)} />
                    </Td>
                </Tr>
            )}
            renderFormulario={() => (
                <>
                    <div className="grid grid-cols-4 gap-3">
                        <Field label="Código" error={form.errors.codigo_sunat} required>
                            <Input
                                className="font-mono"
                                inputMode="numeric"
                                maxLength={2}
                                value={form.data.codigo_sunat}
                                onChange={(e) => form.setData('codigo_sunat', e.target.value.replace(/\D/g, '').slice(0, 2))}
                                placeholder="01"
                                autoFocus
                            />
                        </Field>
                        <Field label="Descripción" error={form.errors.descripcion} required className="col-span-3">
                            <Input value={form.data.descripcion} onChange={(e) => form.setData('descripcion', e.target.value.toUpperCase())} placeholder="FACTURA ELECTRÓNICA" />
                        </Field>
                    </div>
                    <div className="space-y-3 rounded-lg border border-slate-200 p-3">
                        <CheckboxField label="Exige RUC del cliente" description="El cliente debe identificarse con RUC para emitirlo." checked={form.data.exige_ruc} onChange={(valor) => form.setData('exige_ruc', valor)} />
                        <CheckboxField label="Permite anulación" description="Puede anularse o comunicarse de baja después de emitido." checked={form.data.permite_anulacion} onChange={(valor) => form.setData('permite_anulacion', valor)} />
                        {dialogo.editando && (
                            <CheckboxField label="Comprobante activo" description="Disponible para nuevas ventas." checked={form.data.estado} onChange={(valor) => form.setData('estado', valor)} />
                        )}
                    </div>
                </>
            )}
        />
    );
}
