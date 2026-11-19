import { useRemember } from '@inertiajs/react';
import CatalogoPagina, { AccionesFila } from '@/Components/Catalogo/CatalogoPagina';
import useCatalogoCrud, { normalizarColeccion, useFiltroTexto } from '@/Components/Catalogo/useCatalogoCrud';
import { Badge, CheckboxField, Field, Input, StatusBadge, Td, Tr } from '@/Components/ui';

const CAMPOS_BUSQUEDA = ['nombre', 'direccion', 'codigo_establecimiento', 'responsable'];
const COLUMNAS = [
    { label: 'Sucursal', campo: 'nombre' },
    { label: 'Dirección', campo: 'direccion' },
    { label: 'Cód. SUNAT', campo: 'codigo_establecimiento' },
    { label: 'Usuarios', campo: 'usuarios_count', align: 'right' },
    { label: 'Estado', campo: 'estado' },
];

const validar = (data) => {
    if (!data.nombre.trim()) return { campo: 'nombre', mensaje: 'Ingresa el nombre de la sucursal.' };
    if (!data.direccion.trim()) return { campo: 'direccion', mensaje: 'Ingresa la dirección de la sucursal.' };
    if (data.codigo_establecimiento && !/^\d{4}$/.test(data.codigo_establecimiento)) {
        return { campo: 'codigo_establecimiento', mensaje: 'El código de establecimiento SUNAT tiene 4 dígitos.' };
    }
    return null;
};

export default function Index({ sucursales = [], sucursalActivaId = null }) {
    const lista = normalizarColeccion(sucursales);
    const [busqueda, setBusqueda] = useRemember('', 'busqueda:sucursales');
    const filtradas = useFiltroTexto(lista, busqueda, CAMPOS_BUSQUEDA);

    const crud = useCatalogoCrud({
        endpoint: '/sucursales',
        getId: (sucursal) => sucursal.id_sucursal,
        valoresIniciales: { nombre: '', direccion: '', telefono: '', responsable: '', codigo_establecimiento: '', estado: true },
        aFormulario: (sucursal) => ({
            nombre: sucursal.nombre || '',
            direccion: sucursal.direccion || '',
            telefono: sucursal.telefono || '',
            responsable: sucursal.responsable || '',
            codigo_establecimiento: sucursal.codigo_establecimiento || '',
            estado: Boolean(sucursal.estado),
        }),
        validar,
        mensajes: {
            guardado: (data, esEdicion) => ({
                titulo: esEdicion ? 'Sucursal actualizada' : 'Sucursal registrada',
                mensaje: esEdicion ? data.nombre.trim() : `${data.nombre.trim()}: cambia a esta sucursal para configurar sus datos generales.`,
            }),
            eliminado: (sucursal) => ({ titulo: 'Sucursal desactivada', mensaje: `${sucursal.nombre} ya no estará disponible para operar.` }),
            errorEliminar: 'No se pudo desactivar la sucursal.',
        },
    });
    const { form, dialogo, eliminacion } = crud;

    return (
        <CatalogoPagina
            titulo="Sucursales"
            seccion="Configuración"
            descripcion="Sedes de la empresa. Cada sucursal tiene sus propios datos generales, series, caja, inventario y ventas."
            entidad="sucursal"
            femenino
            crud={crud}
            busqueda={busqueda}
            onBusqueda={setBusqueda}
            placeholderBusqueda="Buscar sucursal"
            columnas={COLUMNAS}
            registros={filtradas}
            total={lista.length}
            mensajeEliminar={(sucursal) => <>La sucursal <strong>{sucursal.nombre}</strong> dejará de estar disponible para operar. Sus ventas, Kardex y comprobantes se conservan.</>}
            renderFila={(sucursal) => (
                <Tr key={sucursal.id_sucursal}>
                    <Td className="font-medium text-slate-900">
                        <span className="flex items-center gap-2">
                            {sucursal.nombre}
                            {sucursal.id_sucursal === sucursalActivaId && <Badge tone="brand">Trabajando aquí</Badge>}
                        </span>
                    </Td>
                    <Td className="max-w-xs truncate text-slate-600">{sucursal.direccion}</Td>
                    <Td className="font-mono text-slate-600">{sucursal.codigo_establecimiento || '—'}</Td>
                    <Td align="right" className="font-mono tabular-nums">{sucursal.usuarios_count ?? 0}</Td>
                    <Td><StatusBadge active={sucursal.estado ?? true} activeLabel="Activa" inactiveLabel="Inactiva" /></Td>
                    <Td align="right">
                        <AccionesFila nombre={sucursal.nombre} onEditar={() => dialogo.abrirEdicion(sucursal)} onEliminar={() => eliminacion.solicitar(sucursal)} />
                    </Td>
                </Tr>
            )}
            renderFormulario={() => (
                <>
                    <Field label="Nombre de la sucursal" error={form.errors.nombre} required>
                        <Input value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value.toUpperCase())} maxLength={100} autoFocus />
                    </Field>
                    <Field label="Dirección" error={form.errors.direccion} required>
                        <Input value={form.data.direccion} onChange={(e) => form.setData('direccion', e.target.value)} maxLength={255} />
                    </Field>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <Field label="Teléfono" error={form.errors.telefono}>
                            <Input value={form.data.telefono} onChange={(e) => form.setData('telefono', e.target.value.replace(/[^0-9+\s-]/g, ''))} maxLength={20} inputMode="tel" />
                        </Field>
                        <Field label="Código de establecimiento SUNAT" error={form.errors.codigo_establecimiento} hint="4 dígitos; 0000 es el domicilio fiscal.">
                            <Input value={form.data.codigo_establecimiento} onChange={(e) => form.setData('codigo_establecimiento', e.target.value.replace(/\D/g, '').slice(0, 4))} inputMode="numeric" className="font-mono" />
                        </Field>
                    </div>
                    <Field label="Responsable" error={form.errors.responsable}>
                        <Input value={form.data.responsable} onChange={(e) => form.setData('responsable', e.target.value)} maxLength={150} />
                    </Field>
                    {form.errors.estado && <p className="text-sm text-rose-600">{form.errors.estado}</p>}
                    <CheckboxField label="Sucursal activa" description="Disponible para iniciar sesión, vender y registrar inventario." checked={form.data.estado} onChange={(valor) => form.setData('estado', valor)} />
                </>
            )}
        />
    );
}
