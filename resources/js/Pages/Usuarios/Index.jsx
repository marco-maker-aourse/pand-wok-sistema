import { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Building2, KeyRound, Loader2, Pencil, ShieldCheck, Trash2, UserCog, UserPlus, Users } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge, Button, Card, ConfirmDialog, EmptyState, IconButton, PageHeader, PaginacionServidor, SearchInput, Select, Table, TBody, Td, Th, THead, Tr, cx, toast } from '@/Components/ui';
import FormularioUsuario from '@/Components/Usuarios/FormularioUsuario';
import SelectorPermisos from '@/Components/Usuarios/SelectorPermisos';
import useBusquedaServidor from '@/hooks/useBusquedaServidor';

const permisosDe = (rol) => (rol?.permisos ?? []).map((p) => (typeof p === 'object' ? p.id_permiso : p));

function MatrizPermisos({ roles, permisos }) {
    const [idRol, setIdRol] = useState(roles[0]?.id_rol ?? '');
    const rol = roles.find((r) => String(r.id_rol) === String(idRol));
    const [seleccionados, setSeleccionados] = useState(() => permisosDe(rol));
    const [guardando, setGuardando] = useState(false);
    const original = useMemo(() => new Set(permisosDe(rol).map(Number)), [rol]);
    const cambios = seleccionados.length !== original.size || seleccionados.some((id) => !original.has(Number(id)));

    const elegirRol = (id) => {
        setIdRol(id);
        setSeleccionados(permisosDe(roles.find((r) => String(r.id_rol) === String(id))));
    };

    const guardar = () => router.put(window.route ? route('roles.permissions.update', idRol) : `/roles/${idRol}/permisos`, { permisos: seleccionados }, {
        preserveScroll: true,
        onStart: () => setGuardando(true),
        onFinish: () => setGuardando(false),
        onSuccess: () => toast.success('Permisos guardados', rol?.nombre),
        onError: (errores) => toast.error('No se guardaron los permisos', Object.values(errores)[0]),
    });

    return (
        <Card className="space-y-4 p-4 sm:p-5">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <label className="block min-w-0 sm:w-72">
                    <span className="mb-1.5 block text-xs font-medium text-slate-700">Rol</span>
                    <Select value={idRol} onChange={(e) => elegirRol(e.target.value)}>
                        {roles.map((r) => <option key={r.id_rol} value={r.id_rol}>{r.nombre}</option>)}
                    </Select>
                </label>
                <div className="flex items-center gap-2">
                    {cambios && <span className="text-xs text-amber-700">Cambios sin guardar</span>}
                    <Button variant="secondary" onClick={() => setSeleccionados(permisosDe(rol))} disabled={!cambios || guardando}>Descartar</Button>
                    <Button icon={ShieldCheck} onClick={guardar} loading={guardando} disabled={!cambios}>Guardar permisos</Button>
                </div>
            </div>
            <SelectorPermisos permisos={permisos} seleccionados={seleccionados} onCambiar={setSeleccionados} />
        </Card>
    );
}

/** Cuentas de acceso: rol, sede, accesos a otras sucursales y PIN de caja; y permisos por rol. */
export default function Index({ usuarios, roles = [], permisos = [], sucursales = [], tiposDocumento = [], puedeGestionarPin = false, puedeAsignarSucursales = false, filters = {} }) {
    const lista = usuarios?.data ?? (Array.isArray(usuarios) ? usuarios : []);
    const [pestana, setPestana] = useState('usuarios');
    const { busqueda, setBusqueda, buscando } = useBusquedaServidor('/usuarios', filters.buscar, ['usuarios', 'filters']);
    const [filtroRol, setFiltroRol] = useState('TODOS');
    const [formulario, setFormulario] = useState({ abierto: false, usuario: null });
    const [aDesactivar, setADesactivar] = useState(null);
    const [desactivando, setDesactivando] = useState(false);

    const visibles = useMemo(() => (filtroRol === 'TODOS' ? lista : lista.filter((u) => String(u.id_rol) === String(filtroRol))), [lista, filtroRol]);
    const total = Number(usuarios?.total ?? lista.length);

    const desactivar = () => router.delete(window.route ? route('usuarios.destroy', aDesactivar.id_usuario) : `/usuarios/${aDesactivar.id_usuario}`, {
        preserveScroll: true,
        onStart: () => setDesactivando(true),
        onFinish: () => setDesactivando(false),
        onSuccess: () => { toast.success('Cuenta desactivada', aDesactivar.nombres); setADesactivar(null); },
        onError: (errores) => toast.error('No se pudo desactivar la cuenta', Object.values(errores ?? {})[0]),
    });

    const abrir = (usuario = null) => setFormulario({ abierto: true, usuario });

    return (
        <AuthenticatedLayout header="Usuarios">
            <Head title="Usuarios" />
            <div className="space-y-4">
                <PageHeader title="Usuarios" description="Cuentas del personal, su rol y la sucursal donde trabajan." icon={UserCog}
                    actions={pestana === 'usuarios' && <Button icon={UserPlus} onClick={() => abrir()}>Nuevo usuario</Button>} />

                <div className="flex gap-1 border-b border-slate-200" role="tablist" aria-label="Secciones">
                    {[['usuarios', `Usuarios (${total})`, Users], ['permisos', 'Permisos por rol', ShieldCheck]].map(([clave, etiqueta, Icono]) => (
                        <button key={clave} type="button" role="tab" aria-selected={pestana === clave} onClick={() => setPestana(clave)}
                            className={cx('-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors', pestana === clave ? 'border-brand text-brand-ink' : 'border-transparent text-slate-500 hover:text-slate-800')}>
                            <Icono className="h-4 w-4" aria-hidden="true" />{etiqueta}
                        </button>
                    ))}
                </div>

                {pestana === 'permisos' ? <MatrizPermisos roles={roles} permisos={permisos} /> : (
                    <Card className="overflow-hidden">
                        <div className="flex flex-col gap-3 border-b border-slate-100 p-3 sm:flex-row sm:items-center sm:p-4">
                            <div className="flex flex-1 items-center gap-2">
                                <SearchInput value={busqueda} onChange={setBusqueda} placeholder="Nombre, correo o documento…" label="Buscar usuario" className="w-full sm:w-80" />
                                {buscando && <Loader2 className="h-4 w-4 animate-spin text-slate-400" aria-label="Buscando" />}
                            </div>
                            <Select aria-label="Filtrar por rol" value={filtroRol} onChange={(e) => setFiltroRol(e.target.value)} className="sm:w-52">
                                <option value="TODOS">Todos los roles</option>
                                {roles.map((r) => <option key={r.id_rol} value={r.id_rol}>{r.nombre}</option>)}
                            </Select>
                        </div>

                        {visibles.length === 0 ? (
                            <EmptyState icon={Users} title="Ningún usuario coincide" description="Prueba con otra búsqueda o rol." />
                        ) : (
                            <>
                                <ul className="divide-y divide-slate-100 md:hidden">
                                    {visibles.map((u) => (
                                        <li key={u.id_usuario}>
                                            <button type="button" onClick={() => abrir(u)} className="flex w-full items-center gap-3 px-4 py-3 text-left active:bg-slate-50">
                                                <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">{(u.nombres || 'U').charAt(0)}</span>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium text-slate-900">{u.nombres}</p>
                                                    <p className="truncate text-xs text-slate-500">{u.rol || 'Sin rol'} · {u.sucursal || 'Sin sede'}</p>
                                                </div>
                                                {u.estado === false && <Badge tone="danger">Inactivo</Badge>}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                                <Table className="hidden md:block" caption="Usuarios">
                                    <THead><Th>Usuario</Th><Th>Documento</Th><Th>Rol</Th><Th>Sede</Th><Th>PIN de caja</Th><Th>Estado</Th><Th align="right"><span className="sr-only">Acciones</span></Th></THead>
                                    <TBody>
                                        {visibles.map((u) => (
                                            <Tr key={u.id_usuario}>
                                                <Td>
                                                    <div className="flex items-center gap-2.5">
                                                        <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700">{(u.nombres || 'U').charAt(0)}</span>
                                                        <div className="min-w-0"><p className="font-medium text-slate-900">{u.nombres}</p><p className="truncate text-xs text-slate-500">{u.email}</p></div>
                                                    </div>
                                                </Td>
                                                <Td className="font-mono text-xs">{u.num_documento || <span className="text-slate-400">—</span>}</Td>
                                                <Td><Badge tone="brand" icon={ShieldCheck}>{u.rol || 'Sin rol'}</Badge></Td>
                                                <Td>
                                                    <span className="flex items-center gap-1.5 text-slate-700"><Building2 className="h-3.5 w-3.5 text-slate-400" aria-hidden="true" />{u.sucursal || 'Sin sede'}</span>
                                                    {u.sucursales_adicionales?.length > 0 && <span className="text-xs text-slate-500" title={u.sucursales_adicionales.map((s) => s.nombre).join(', ')}>+{u.sucursales_adicionales.length} con acceso</span>}
                                                </Td>
                                                <Td><Badge tone={u.tiene_pin_cajero ? 'success' : 'neutral'} icon={KeyRound}>{u.tiene_pin_cajero ? 'Configurado' : 'Sin PIN'}</Badge></Td>
                                                <Td><Badge tone={u.estado === false ? 'danger' : 'success'} dot>{u.estado === false ? 'Inactivo' : 'Activo'}</Badge></Td>
                                                <Td align="right">
                                                    <div className="flex justify-end gap-1">
                                                        <IconButton icon={Pencil} label={`Editar ${u.nombres}`} onClick={() => abrir(u)} />
                                                        {u.estado !== false && <IconButton icon={Trash2} tone="danger" label={`Desactivar ${u.nombres}`} onClick={() => setADesactivar(u)} />}
                                                    </div>
                                                </Td>
                                            </Tr>
                                        ))}
                                    </TBody>
                                </Table>
                            </>
                        )}
                        <PaginacionServidor paginador={usuarios} entidad="usuarios" />
                    </Card>
                )}
            </div>

            <FormularioUsuario abierto={formulario.abierto} usuario={formulario.usuario} onCerrar={() => setFormulario({ abierto: false, usuario: null })}
                roles={roles} sucursales={sucursales} tiposDocumento={tiposDocumento} puedeGestionarPin={puedeGestionarPin} puedeAsignarSucursales={puedeAsignarSucursales} />

            <ConfirmDialog open={Boolean(aDesactivar)} onClose={() => setADesactivar(null)} onConfirm={desactivar} loading={desactivando} title="¿Desactivar la cuenta?" confirmLabel="Desactivar">
                {aDesactivar && <><strong>{aDesactivar.nombres}</strong> ({aDesactivar.email}) no podrá iniciar sesión ni cobrar hasta que se reactive.</>}
            </ConfirmDialog>
        </AuthenticatedLayout>
    );
}
