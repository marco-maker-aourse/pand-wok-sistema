import { useEffect, useMemo, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge, Button, Card, CheckboxField, ConfirmDialog, Dialog, EmptyState, Field, IconButton, Input, PageHeader, PaginacionServidor, SearchInput, Table, TBody, Td, Th, THead, Tr, Textarea, toast } from '@/Components/ui';
import SelectorPermisos from '@/Components/Usuarios/SelectorPermisos';

const permisosDe = (rol) => (Array.isArray(rol?.permisos) ? rol.permisos.map((p) => (typeof p === 'object' ? p.id_permiso : p)) : []);

function FormularioRol({ rol, abierto, onCerrar, permisos, nombresExistentes }) {
    const editando = Boolean(rol);
    const form = useForm({ nombre: '', descripcion: '', estado: true, permisos: [] });
    const { data, setData, errors, setError, clearErrors, processing } = form;

    useEffect(() => {
        if (!abierto) return;
        clearErrors();
        setData(rol
            ? { nombre: (rol.nombre || '').toUpperCase(), descripcion: rol.descripcion || '', estado: Boolean(rol.estado ?? true), permisos: permisosDe(rol) }
            : { nombre: '', descripcion: '', estado: true, permisos: [] });
    }, [abierto, rol]); // eslint-disable-line react-hooks/exhaustive-deps

    const guardar = (e) => {
        e.preventDefault();
        const nombre = data.nombre.trim().toUpperCase();
        clearErrors();
        if (nombre.length < 3) return setError('nombre', 'Mínimo 3 caracteres (ej.: CAJERO, MOZO).');
        if (nombresExistentes.some((r) => r.id_rol !== rol?.id_rol && (r.nombre || '').trim().toUpperCase() === nombre)) return setError('nombre', 'Ya existe un rol con ese nombre.');
        const opciones = {
            preserveScroll: true,
            onSuccess: () => { toast.success(editando ? 'Rol actualizado' : 'Rol creado', nombre); onCerrar(); },
            onError: () => toast.error('Revisa los datos del rol'),
        };
        return editando ? form.put(`/roles/${rol.id_rol}`, opciones) : form.post('/roles', opciones);
    };

    return (
        <Dialog open={abierto} onClose={processing ? () => {} : onCerrar} size="4xl" title={editando ? `Editar rol ${rol.nombre}` : 'Nuevo rol'} description="Qué puede hacer el personal con este rol."
            footer={<><Button variant="secondary" onClick={onCerrar} disabled={processing}>Cancelar</Button><Button type="submit" form="formulario-rol" loading={processing}>{editando ? 'Guardar cambios' : 'Crear rol'}</Button></>}>
            <form id="formulario-rol" onSubmit={guardar} className="space-y-4 p-5" noValidate>
                <div className="grid gap-3 sm:grid-cols-[16rem_minmax(0,1fr)]">
                    <Field label="Nombre" required error={errors.nombre}>
                        <Input className="uppercase" autoFocus={!editando} maxLength={50} value={data.nombre} onChange={(e) => setData('nombre', e.target.value.toUpperCase())} />
                    </Field>
                    <Field label="Descripción" error={errors.descripcion}>
                        <Textarea rows={1} maxLength={255} value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} placeholder="Ej.: toma pedidos y los envía a cocina" />
                    </Field>
                </div>
                {editando && <CheckboxField label="Rol activo" description="Se puede asignar a usuarios." checked={data.estado} onChange={(valor) => setData('estado', valor)} />}
                <div>
                    <h3 className="mb-2 text-sm font-semibold text-slate-900">Permisos</h3>
                    {errors.permisos && <p className="mb-2 text-xs font-medium text-rose-600" role="alert">{errors.permisos}</p>}
                    <SelectorPermisos permisos={permisos} seleccionados={data.permisos} onCambiar={(ids) => setData('permisos', ids)} />
                </div>
            </form>
        </Dialog>
    );
}

/** Roles y sus permisos. La autorización real la aplican los middleware y policies del servidor. */
export default function Roles({ roles, permisos = [] }) {
    const lista = roles?.data ?? (Array.isArray(roles) ? roles : []);
    const [busqueda, setBusqueda] = useState('');
    const [formulario, setFormulario] = useState({ abierto: false, rol: null });
    const [aDesactivar, setADesactivar] = useState(null);
    const [desactivando, setDesactivando] = useState(false);

    const visibles = useMemo(() => {
        const q = busqueda.toLowerCase().trim();
        return q ? lista.filter((r) => (r.nombre || '').toLowerCase().includes(q) || (r.descripcion || '').toLowerCase().includes(q)) : lista;
    }, [lista, busqueda]);

    const desactivar = () => router.delete(`/roles/${aDesactivar.id_rol}`, {
        preserveScroll: true,
        onStart: () => setDesactivando(true),
        onFinish: () => setDesactivando(false),
        onSuccess: () => { toast.success('Rol desactivado', aDesactivar.nombre); setADesactivar(null); },
        onError: (errores) => toast.error('No se pudo desactivar el rol', Object.values(errores ?? {})[0]),
    });

    const abrir = (rol = null) => setFormulario({ abierto: true, rol });

    return (
        <AuthenticatedLayout header="Roles y permisos">
            <Head title="Roles y permisos" />
            <div className="space-y-4">
                <PageHeader title="Roles y permisos" description="Perfiles de acceso del personal: mozo, cajero, cocina, administración." icon={ShieldCheck}
                    actions={<Button icon={Plus} onClick={() => abrir()}>Nuevo rol</Button>} />

                <Card className="overflow-hidden">
                    <div className="border-b border-slate-100 p-3 sm:p-4">
                        <SearchInput value={busqueda} onChange={setBusqueda} placeholder="Nombre o descripción…" label="Buscar rol" className="sm:w-80" />
                    </div>
                    {visibles.length === 0 ? (
                        <EmptyState icon={ShieldCheck} title="Ningún rol coincide" description="Prueba con otra búsqueda." />
                    ) : (
                        <Table caption="Roles">
                            <THead><Th>Rol</Th><Th className="hidden md:table-cell">Descripción</Th><Th>Permisos</Th><Th>Estado</Th><Th align="right"><span className="sr-only">Acciones</span></Th></THead>
                            <TBody>
                                {visibles.map((r) => {
                                    const cantidad = permisosDe(r).length;
                                    return (
                                        <Tr key={r.id_rol}>
                                            <Td className="font-medium text-slate-900">{r.nombre}</Td>
                                            <Td className="hidden max-w-md text-slate-600 md:table-cell"><p className="truncate">{r.descripcion || '—'}</p></Td>
                                            <Td>
                                                <div className="flex items-center gap-2">
                                                    <div className="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                                        <div className="h-full bg-brand" style={{ width: `${permisos.length ? (cantidad / permisos.length) * 100 : 0}%` }} />
                                                    </div>
                                                    <span className="text-xs text-slate-600 tabular-nums">{cantidad} de {permisos.length}</span>
                                                </div>
                                            </Td>
                                            <Td><Badge tone={r.estado === false ? 'neutral' : 'success'} dot>{r.estado === false ? 'Inactivo' : 'Activo'}</Badge></Td>
                                            <Td align="right">
                                                <div className="flex justify-end gap-1">
                                                    <IconButton icon={Pencil} label={`Editar ${r.nombre}`} onClick={() => abrir(r)} />
                                                    {r.estado !== false && <IconButton icon={Trash2} tone="danger" label={`Desactivar ${r.nombre}`} onClick={() => setADesactivar(r)} />}
                                                </div>
                                            </Td>
                                        </Tr>
                                    );
                                })}
                            </TBody>
                        </Table>
                    )}
                    <PaginacionServidor paginador={roles} entidad="roles" />
                </Card>
            </div>

            <FormularioRol abierto={formulario.abierto} rol={formulario.rol} onCerrar={() => setFormulario({ abierto: false, rol: null })} permisos={permisos} nombresExistentes={lista} />

            <ConfirmDialog open={Boolean(aDesactivar)} onClose={() => setADesactivar(null)} onConfirm={desactivar} loading={desactivando} title="¿Desactivar el rol?" confirmLabel="Desactivar">
                {aDesactivar && <>El rol <strong>{aDesactivar.nombre}</strong> no podrá asignarse a nuevos usuarios.</>}
            </ConfirmDialog>
        </AuthenticatedLayout>
    );
}
