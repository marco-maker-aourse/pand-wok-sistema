import React, { useState, useMemo } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader, Dialog } from '@/Components/ui';
import SucursalesAdicionales from '@/Components/Usuarios/SucursalesAdicionales';
import {
    User,
    UserPlus,
    Users,
    Shield,
    ShieldCheck,
    Lock,
    Mail,
    Building2,
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    X,
    Check,
    Loader2,
    Search,
    Edit3,
    Trash2,
    CheckSquare,
    Square,
    CreditCard,
    KeyRound
} from 'lucide-react';

export default function Index({ usuarios, roles = [], permisos = [], sucursales = [], tiposDocumento = [], puedeGestionarPin = false, puedeAsignarSucursales = false }) {
    // Normalizar lista de usuarios (colección o paginada)
    const rows = usuarios?.data ?? (Array.isArray(usuarios) ? usuarios : []);

    // Navegación entre pestañas de alta jerarquía
    const [tabActiva, setTabActiva] = useState('usuarios'); // 'usuarios' | 'permisos'

    // Búsqueda y Filtros en la tabla
    const [busqueda, setBusqueda] = useState('');
    const [filtroRol, setFiltroRol] = useState('TODOS');

    // Estados de Modales Nativos
    const [modalUsuarioOpen, setModalUsuarioOpen] = useState(false);
    const [usuarioEditando, setUsuarioEditando] = useState(null);
    const [usuarioAEliminar, setUsuarioAEliminar] = useState(null);
    const [dialogo, setDialogo] = useState(null); // { tipo: 'exito' | 'error' | 'alerta', titulo: '', mensaje: '' }
    const [guardandoPermisos, setGuardandoPermisos] = useState(false);

    // Estado para Matriz de Permisos
    const [selectedRoleId, setSelectedRoleId] = useState(roles[0]?.id_rol ?? '');
    const selectedRole = roles.find((role) => String(role.id_rol) === String(selectedRoleId));
    const [selectedPermissions, setSelectedPermissions] = useState(
        selectedRole?.permisos ? selectedRole.permisos.map((p) => (typeof p === 'object' ? p.id_permiso : p)) : []
    );
    const [filtroPermisoTexto, setFiltroPermisoTexto] = useState('');

    // Formulario de Usuario con Inertia
    const formUsuario = useForm({
        nombres: '',
        email: '',
        password: '',
        password_confirmation: '',
        pin_cajero: '',
        pin_cajero_confirmation: '',
        id_rol: roles[0]?.id_rol ?? '',
        id_sucursal: sucursales[0]?.id_sucursal ?? '',
        sucursales_adicionales: [],
        id_tipo_documento: '',
        num_documento: '',
        estado: true,
    });
    const tipoDocumentoSeleccionado = tiposDocumento.find(
        (tipo) => String(tipo.id_tipo_documento) === String(formUsuario.data.id_tipo_documento)
    );
    const documentoSoloNumerico = ['1', '6'].includes(String(tipoDocumentoSeleccionado?.codigo_sunat));

    // Filtro reactivo en la tabla de usuarios
    const usuariosFiltrados = useMemo(() => {
        return rows.filter((u) => {
            const q = busqueda.toUpperCase().trim();
            const coincideTexto =
                !q ||
                (u.nombres || '').toUpperCase().includes(q) ||
                (u.email || '').toUpperCase().includes(q) ||
                (u.num_documento || '').includes(q);

            const coincideRol =
                filtroRol === 'TODOS' ||
                String(u.id_rol) === String(filtroRol) ||
                (u.rol || '').toUpperCase() === filtroRol.toUpperCase();

            return coincideTexto && coincideRol;
        });
    }, [rows, busqueda, filtroRol]);

    // Permisos agrupados por módulo con filtro de búsqueda
    const groupedPermissions = useMemo(() => {
        const q = filtroPermisoTexto.toLowerCase().trim();
        const filtrados = permisos.filter(
            (p) =>
                (p.nombre || '').toLowerCase().includes(q) ||
                (p.modulo || '').toLowerCase().includes(q)
        );

        return filtrados.reduce((acc, perm) => {
            const moduleName = (perm.modulo || 'GENERAL').toUpperCase();
            if (!acc[moduleName]) acc[moduleName] = [];
            acc[moduleName].push(perm);
            return acc;
        }, {});
    }, [permisos, filtroPermisoTexto]);

    // Abrir Modal para Crear Usuario
    const abrirModalCrearUsuario = () => {
        setUsuarioEditando(null);
        formUsuario.clearErrors();
        formUsuario.reset();
        formUsuario.setData({
            nombres: '',
            email: '',
            password: '',
            password_confirmation: '',
            pin_cajero: '',
            pin_cajero_confirmation: '',
            id_rol: roles[0]?.id_rol ?? '',
            id_sucursal: sucursales[0]?.id_sucursal ?? '',
            sucursales_adicionales: [],
            id_tipo_documento: tiposDocumento[0]?.id_tipo_documento ?? '',
            num_documento: '',
            estado: true,
        });
        setModalUsuarioOpen(true);
    };

    // Abrir Modal para Editar Usuario
    const abrirModalEditarUsuario = (user) => {
        setUsuarioEditando(user);
        formUsuario.clearErrors();
        formUsuario.setData({
            nombres: (user.nombres || '').toUpperCase(),
            email: user.email || '',
            password: '',
            password_confirmation: '',
            pin_cajero: '',
            pin_cajero_confirmation: '',
            id_rol: user.id_rol ?? roles[0]?.id_rol ?? '',
            id_sucursal: user.id_sucursal ?? '',
            sucursales_adicionales: (user.sucursales_adicionales ?? []).map((sucursal) => sucursal.id_sucursal),
            id_tipo_documento: user.id_tipo_documento ?? '',
            num_documento: user.num_documento ?? '',
            estado: Boolean(user.estado ?? true),
        });
        setModalUsuarioOpen(true);
    };

    // Guardar / Actualizar Usuario
    const handleSaveUser = (e) => {
        e.preventDefault();
        formUsuario.clearErrors();

        const nombresLimpio = formUsuario.data.nombres.trim().toUpperCase();

        if (nombresLimpio.length < 3) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'NOMBRE INVÁLIDO',
                mensaje: 'DEBE INGRESAR LOS NOMBRES COMPLETOS DEL USUARIO (MÍNIMO 3 CARACTERES).'
            });
            return;
        }

        if (!formUsuario.data.id_rol || !formUsuario.data.id_sucursal || !formUsuario.data.id_tipo_documento) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'DATOS OBLIGATORIOS',
                mensaje: 'SELECCIONA EL ROL, LA SUCURSAL Y EL TIPO DE DOCUMENTO DEL USUARIO.'
            });
            return;
        }

        const documento = String(formUsuario.data.num_documento || '').trim();
        if (!documento) {
            setDialogo({ tipo: 'alerta', titulo: 'DOCUMENTO REQUERIDO', mensaje: 'INGRESA EL NÚMERO DE DOCUMENTO DEL USUARIO.' });
            return;
        }
        if (tipoDocumentoSeleccionado?.longitud_exacta && documento.length !== Number(tipoDocumentoSeleccionado.longitud_exacta)) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'DOCUMENTO INVÁLIDO',
                mensaje: `${tipoDocumentoSeleccionado.nombre.toUpperCase()} DEBE TENER ${tipoDocumentoSeleccionado.longitud_exacta} CARACTERES.`
            });
            return;
        }

        // Validación de contraseñas
        if (!usuarioEditando || formUsuario.data.password) {
            if (formUsuario.data.password.length < 8) {
                setDialogo({
                    tipo: 'alerta',
                    titulo: 'CONTRASEÑA INSEGURA',
                    mensaje: 'LA CONTRASEÑA DEBE CONTENER AL MENOS 8 CARACTERES.'
                });
                return;
            }

            if (formUsuario.data.password !== formUsuario.data.password_confirmation) {
                setDialogo({
                    tipo: 'alerta',
                    titulo: 'CONTRASEÑAS NO COINCIDEN',
                    mensaje: 'LA CONTRASEÑA Y SU CONFIRMACIÓN DEBEN SER EXACTAMENTE IGUALES.'
                });
                return;
            }
        }

        const pinCajero = String(formUsuario.data.pin_cajero || '');
        if (pinCajero) {
            if (!/^\d{4,6}$/.test(pinCajero)) {
                setDialogo({
                    tipo: 'alerta',
                    titulo: 'PIN DE CAJA INVÁLIDO',
                    mensaje: 'EL PIN DE CAJA DEBE CONTENER ENTRE 4 Y 6 DÍGITOS.'
                });
                return;
            }

            if (pinCajero !== formUsuario.data.pin_cajero_confirmation) {
                setDialogo({
                    tipo: 'alerta',
                    titulo: 'PIN DE CAJA NO COINCIDE',
                    mensaje: 'CONFIRMA EL MISMO PIN DE CAJA PARA PODER GUARDAR EL USUARIO.'
                });
                return;
            }
        }

        const endpoint = usuarioEditando
            ? (window.route ? route('usuarios.update', usuarioEditando.id_usuario) : `/usuarios/${usuarioEditando.id_usuario}`)
            : (window.route ? route('usuarios.store') : '/usuarios');

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setModalUsuarioOpen(false);
                formUsuario.reset();
                setDialogo({
                    tipo: 'exito',
                    titulo: usuarioEditando ? 'USUARIO ACTUALIZADO' : 'USUARIO REGISTRADO',
                    mensaje: `LA CUENTA DE ACCESO PARA "${nombresLimpio}" QUEDÓ REGISTRADA EXITOSAMENTE EN SUPABASE.`
                });
            },
            onError: (errs) => {
                const primerError = Object.values(errs)[0] || 'OCURRIÓ UN ERROR AL PROCESAR EL FORMULARIO.';
                setDialogo({
                    tipo: 'error',
                    titulo: 'ERROR AL GUARDAR',
                    mensaje: String(primerError).toUpperCase()
                });
            }
        };

        // Solo un administrador envía accesos a sucursales; para el resto el servidor los conserva.
        formUsuario.transform(({ sucursales_adicionales, ...datos }) => (puedeAsignarSucursales
            ? { ...datos, sucursales_adicionales: sucursales_adicionales.filter((id) => String(id) !== String(datos.id_sucursal)) }
            : datos));

        if (usuarioEditando) {
            formUsuario.put(endpoint, options);
        } else {
            formUsuario.post(endpoint, options);
        }
    };

    // Desactivar Usuario
    const ejecutarEliminarUsuario = () => {
        if (!usuarioAEliminar) return;
        const target = usuarioAEliminar;

        const endpoint = window.route
            ? route('usuarios.destroy', target.id_usuario)
            : `/usuarios/${target.id_usuario}`;

        router.delete(endpoint, {
            preserveScroll: true,
            onSuccess: () => {
                setUsuarioAEliminar(null);
                setDialogo({
                    tipo: 'exito',
                    titulo: 'USUARIO DESACTIVADO',
                    mensaje: `LA CUENTA DE "${target.nombres.toUpperCase()}" FUE DESACTIVADA CORRECTAMENTE.`
                });
            },
            onError: () => {
                setUsuarioAEliminar(null);
                setDialogo({
                    tipo: 'error',
                    titulo: 'OPERACIÓN RESTRINGIDA',
                    mensaje: 'NO SE PUEDE DESACTIVAR ESTE USUARIO DEBIDO A REGISTROS DE AUDITORÍA ASOCIADOS.'
                });
            }
        });
    };

    // Cambiar rol activo en la matriz
    const changeRole = (value) => {
        setSelectedRoleId(value);
        const r = roles.find((role) => String(role.id_rol) === String(value));
        const perms = r?.permisos ? r.permisos.map((p) => (typeof p === 'object' ? p.id_permiso : p)) : [];
        setSelectedPermissions(perms);
    };

    // Toggle de permiso individual
    const togglePermission = (permissionId) => {
        setSelectedPermissions((current) =>
            current.includes(permissionId)
                ? current.filter((id) => id !== permissionId)
                : [...current, permissionId]
        );
    };

    // Marcar / Desmarcar todos los permisos
    const toggleTodosPermisos = () => {
        if (selectedPermissions.length === permisos.length) {
            setSelectedPermissions([]);
        } else {
            setSelectedPermissions(permisos.map((p) => p.id_permiso));
        }
    };

    // Guardar Permisos del Rol Seleccionado
    const savePermissions = (event) => {
        event.preventDefault();
        setGuardandoPermisos(true);

        const endpoint = window.route
            ? route('roles.permissions.update', selectedRoleId)
            : `/roles/${selectedRoleId}/permisos`;

        router.put(
            endpoint,
            { permisos: selectedPermissions },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setGuardandoPermisos(false);
                    setDialogo({
                        tipo: 'exito',
                        titulo: 'MATRIZ ACTUALIZADA',
                        mensaje: `SE RECONFIGURARON LOS PERMISOS PARA EL ROL "${(selectedRole?.nombre || '').toUpperCase()}".`
                    });
                },
                onError: (errs) => {
                    setGuardandoPermisos(false);
                    const msg = Object.values(errs)[0] || 'OCURRIÓ UN ERROR AL ACTUALIZAR LOS PERMISOS.';
                    setDialogo({
                        tipo: 'error',
                        titulo: 'ERROR AL ACTUALIZAR',
                        mensaje: String(msg).toUpperCase()
                    });
                }
            }
        );
    };

    return (
        <AuthenticatedLayout header="Usuarios">
            <Head title="Usuarios" />

            <div className="space-y-5">
                <PageHeader
                    breadcrumbs={[{ label: 'Configuración' }, { label: 'Usuarios' }]}
                    title="Usuarios"
                    description="Administración de cuentas, sedes asignadas y matriz de permisos granulares."
                    actions={
                        <>
                        {/* Selector de Pestañas Estilo Píldora */}
                        <div className="flex items-center p-1 bg-slate-100 rounded-lg border border-slate-200/80">
                            <button
                                type="button"
                                onClick={() => setTabActiva('usuarios')}
                                className={`px-4 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer flex items-center gap-2 ${
                                    tabActiva === 'usuarios'
                                        ? 'bg-white text-slate-900'
                                        : 'text-slate-500 hover:text-slate-800'
                                }`}
                            >
                                <Users className="w-3.5 h-3.5" />
                                <span>Usuarios ({rows.length})</span>
                            </button>

                            <button
                                type="button"
                                onClick={() => setTabActiva('permisos')}
                                className={`px-4 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer flex items-center gap-2 ${
                                    tabActiva === 'permisos'
                                        ? 'bg-white text-slate-900'
                                        : 'text-slate-500 hover:text-slate-800'
                                }`}
                            >
                                <ShieldCheck className="w-3.5 h-3.5" />
                                <span>Matriz de Permisos</span>
                            </button>
                        </div>
                        </>
                    }
                />

                {/* 2. PESTAÑA A: DIRECTORIO DE USUARIOS A PANTALLA COMPLETA */}
                {tabActiva === 'usuarios' && (
                    <div className="space-y-4">
                        {/* Barra de Filtros y Botón Nuevo */}
                        <div className="bg-white rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                            <div className="flex flex-1 items-center gap-2">
                                <div className="relative flex-1 max-w-sm">
                                    <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" />
                                    <input
                                        type="text"
                                        placeholder="Buscar por nombre, correo o DNI…"
                                        value={busqueda}
                                        onChange={(e) => setBusqueda(e.target.value.toUpperCase())}
                                        className="w-full pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-lg text-sm placeholder:text-slate-400 uppercase transition"
                                    />
                                </div>

                                <select
                                    value={filtroRol}
                                    onChange={(e) => setFiltroRol(e.target.value)}
                                    className="bg-white border border-slate-200 rounded-xl py-2 px-3 text-xs font-bold text-slate-800 uppercase transition cursor-pointer"
                                >
                                    <option value="TODOS">Todos los roles</option>
                                    {roles.map((r) => (
                                        <option key={r.id_rol} value={r.nombre}>
                                            ROL: {r.nombre.toUpperCase()}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <button
                                type="button"
                                onClick={abrirModalCrearUsuario}
                                className="volumen-marca h-9 px-4 rounded-xl text-on-brand font-semibold text-xs flex items-center gap-2 transition shrink-0 cursor-pointer hover:brightness-105"
                            >
                                <UserPlus className="w-4 h-4" />
                                <span>Nuevo Usuario</span>
                            </button>
                        </div>

                        {/* Tabla de Usuarios de Ancho Completo */}
                        <div className="bg-white rounded-2xl overflow-hidden shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="border-b border-slate-100 bg-white text-xs font-medium text-slate-500">
                                            <th className="py-2.5 px-4 font-medium">Usuario / correo electrónico</th>
                                            <th className="py-2.5 px-4 font-medium">Documento identidad</th>
                                            <th className="py-2.5 px-4 font-medium">Rol de acceso</th>
                                            <th className="py-2.5 px-4 font-medium">Sede / sucursal</th>
                                            <th className="py-2.5 px-4 text-center font-medium">Pin de caja</th>
                                            <th className="py-2.5 px-4 text-center font-medium">Estado</th>
                                            <th className="py-2.5 px-4 text-right font-medium">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100 text-sm text-slate-700">
                                        {usuariosFiltrados.length > 0 ? (
                                            usuariosFiltrados.map((user) => (
                                                <tr key={user.id_usuario} className="hover:bg-brand-soft/40 transition-colors">
                                                    <td className="py-2.5 px-4">
                                                        <div className="flex items-center gap-2.5">
                                                            <div className="w-8 h-8 rounded-xl bg-slate-100 border border-slate-200/60 text-slate-700 flex items-center justify-center font-semibold text-xs">
                                                                {user.nombres ? user.nombres.charAt(0).toUpperCase() : 'U'}
                                                            </div>
                                                            <div>
                                                                <p className="font-semibold text-slate-900">{user.nombres}</p>
                                                                <p className="text-xs font-mono text-slate-400 font-semibold lowercase">
                                                                    {user.email}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="py-2.5 px-4 font-mono text-xs">
                                                        {user.num_documento ? (
                                                            <span className="font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200/60">
                                                                {user.num_documento}
                                                            </span>
                                                        ) : (
                                                            <span className="text-slate-400 font-medium uppercase text-xs">Sin asignar</span>
                                                        )}
                                                    </td>
                                                    <td className="py-2.5 px-4">
                                                        <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-xs font-semibold uppercase bg-brand-soft text-brand-ink border border-brand-border">
                                                            <Shield className="w-3 h-3 text-brand-ink" />
                                                            {user.rol || 'Sin Rol'}
                                                        </span>
                                                    </td>
                                                    <td className="py-2.5 px-4">
                                                        <span className="inline-flex items-center gap-1 text-slate-700 font-bold uppercase text-xs">
                                                            <Building2 className="w-3.5 h-3.5 text-slate-400" />
                                                            {user.sucursal || 'Sin Asignar'}
                                                        </span>
                                                        {user.sucursales_adicionales?.length > 0 && (
                                                            <span className="mt-0.5 block text-xs text-slate-500" title={user.sucursales_adicionales.map((s) => s.nombre).join(', ')}>
                                                                +{user.sucursales_adicionales.length} con acceso
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-2.5 px-4 text-center">
                                                        <span className={`inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full uppercase ${
                                                            user.tiene_pin_cajero
                                                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                                : 'bg-amber-50 text-amber-700 border border-amber-200'
                                                        }`}>
                                                            <KeyRound className="w-3 h-3" />
                                                            {user.tiene_pin_cajero ? 'Configurado' : 'Pendiente'}
                                                        </span>
                                                    </td>
                                                    <td className="py-2.5 px-4 text-center">
                                                        <span className={`inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full uppercase ${
                                                            user.estado !== false
                                                                ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                                : 'bg-rose-50 text-rose-700 border border-rose-200'
                                                        }`}>
                                                            <span className={`w-1.5 h-1.5 rounded-full ${user.estado !== false ? 'bg-emerald-500' : 'bg-rose-500'}`} />
                                                            {user.estado !== false ? 'Activo' : 'Inactivo'}
                                                        </span>
                                                    </td>
                                                    <td className="py-2.5 px-4 text-right">
                                                        <div className="flex items-center justify-end gap-1.5">
                                                            <button
                                                                type="button"
                                                                onClick={() => abrirModalEditarUsuario(user)}
                                                                className="p-1.5 text-slate-400 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition cursor-pointer"
                                                                title="Editar cuenta"
                                                            >
                                                                <Edit3 className="w-4 h-4" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => setUsuarioAEliminar(user)}
                                                                className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                                                                title="Desactivar cuenta"
                                                            >
                                                                <Trash2 className="w-4 h-4" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan="7" className="py-12 text-center text-slate-400">
                                                    <Users className="w-8 h-8 mx-auto mb-2 text-slate-300" />
                                                    <p className="font-semibold text-xs text-slate-600">No se encontraron usuarios</p>
                                                    <p className="text-xs text-slate-400 mt-0.5">
                                                        Prueba cambiando los filtros o registra una nueva cuenta.
                                                    </p>
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {/* 3. PESTAÑA B: MATRIZ DE PERMISOS POR ROL A PANTALLA COMPLETA */}
                {tabActiva === 'permisos' && (
                    <div className="bg-white rounded-2xl p-6 space-y-5 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                        {/* Selector de Rol y Acciones Rápidas */}
                        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                            <div className="flex items-center gap-3">
                                <span className="p-2.5 bg-emerald-50 text-emerald-800 rounded-lg">
                                    <ShieldCheck className="w-5 h-5" />
                                </span>
                                <div>
                                    <h3 className="text-sm font-semibold text-slate-900">
                                        Matriz de Capacidades y Privilegios
                                    </h3>
                                    <p className="text-xs text-slate-400 font-bold">
                                        Selecciona el rol que deseas configurar ({selectedPermissions.length} de {permisos.length} permisos habilitados)
                                    </p>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-3">
                                <div className="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5">
                                    <span className="text-xs font-semibold text-slate-400 uppercase">Perfil:</span>
                                    <select
                                        value={selectedRoleId}
                                        onChange={(e) => changeRole(e.target.value)}
                                        className="bg-transparent text-xs font-semibold text-slate-800 uppercase cursor-pointer"
                                    >
                                        {roles.map((r) => (
                                            <option key={r.id_rol} value={r.id_rol}>
                                                {r.nombre.toUpperCase()}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div className="relative">
                                    <Search className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
                                    <input
                                        type="text"
                                        placeholder="FILTRAR PERMISO..."
                                        value={filtroPermisoTexto}
                                        onChange={(e) => setFiltroPermisoTexto(e.target.value)}
                                        className="pl-8 pr-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800 uppercase"
                                    />
                                </div>

                                <button
                                    type="button"
                                    onClick={toggleTodosPermisos}
                                    className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded-xl text-xs font-semibold text-slate-700 transition flex items-center gap-1.5 cursor-pointer"
                                >
                                    {selectedPermissions.length === permisos.length ? (
                                        <>
                                            <Square className="w-3.5 h-3.5" /> Desmarcar todo
                                        </>
                                    ) : (
                                        <>
                                            <CheckSquare className="w-3.5 h-3.5" /> Marcar todo
                                        </>
                                    )}
                                </button>

                                <button
                                    type="button"
                                    onClick={savePermissions}
                                    disabled={guardandoPermisos}
                                    className="volumen-marca h-9 px-4 rounded-xl text-on-brand font-semibold text-xs flex items-center gap-2 transition cursor-pointer disabled:opacity-50 hover:brightness-105"
                                >
                                    {guardandoPermisos ? (
                                        <Loader2 className="w-4 h-4 animate-spin" />
                                    ) : (
                                        <Check className="w-4 h-4" />
                                    )}
                                    <span>Guardar Matriz</span>
                                </button>
                            </div>
                        </div>

                        {/* Contenedor de Módulos */}
                        <div className="space-y-4">
                            {Object.keys(groupedPermissions).length > 0 ? (
                                Object.entries(groupedPermissions).map(([modulo, items]) => (
                                    <div key={modulo} className="p-4 bg-slate-50/70 border border-slate-200/70 rounded-lg space-y-3">
                                        <span className="text-xs font-semibold text-slate-400 block pl-1">
                                            Módulo: {modulo} ({items.filter((p) => selectedPermissions.includes(p.id_permiso)).length} / {items.length} activos)
                                        </span>
                                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                            {items.map((perm) => {
                                                const checked = selectedPermissions.includes(perm.id_permiso);
                                                return (
                                                    <label
                                                        key={perm.id_permiso}
                                                        className={`flex items-start gap-2.5 p-3 rounded-xl border text-xs font-bold transition cursor-pointer select-none ${
                                                            checked
                                                                ? 'border-emerald-800 bg-emerald-50/60 text-emerald-950'
                                                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
                                                        }`}
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={checked}
                                                            onChange={() => togglePermission(perm.id_permiso)}
                                                            className="mt-0.5 rounded text-emerald-800 border-slate-300 cursor-pointer"
                                                        />
                                                        <div className="min-w-0">
                                                            <p className="leading-tight text-xs truncate">
                                                                {perm.nombre}
                                                            </p>
                                                            {perm.descripcion && (
                                                                <span className="text-xs font-semibold text-slate-400 block truncate mt-0.5">
                                                                    {perm.descripcion}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="text-center py-12 text-xs text-slate-400 font-bold">
                                    No se encontraron permisos coincidentes con la búsqueda.
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* MODAL 1: REGISTRAR / EDITAR USUARIO */}
            <Dialog open={Boolean(modalUsuarioOpen)} onClose={() => setModalUsuarioOpen(false)} size="lg">
                {(modalUsuarioOpen) && (
                <div className="p-6 flex flex-col">
                        <div className="flex justify-between items-center pb-3 border-b border-slate-100 shrink-0">
                            <div className="flex items-center gap-2">
                                <span className="p-2 bg-slate-100 text-slate-700 rounded-xl">
                                    <UserPlus className="w-4 h-4" />
                                </span>
                                <div>
                                    <h3 className="text-sm font-semibold text-slate-900">
                                        {usuarioEditando ? 'Editar Usuario' : 'Nuevo Usuario del Sistema'}
                                    </h3>
                                    <p className="text-xs text-slate-400 font-bold">
                                        Credenciales, sede laboral y perfil asignado
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setModalUsuarioOpen(false)}
                                className="p-1 text-slate-400 hover:text-slate-600 rounded-lg transition cursor-pointer"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleSaveUser} className="mt-4 space-y-3.5 overflow-y-auto pr-1 flex-1 [scrollbar-width:thin]">
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Nombres y Apellidos Completos *
                                </label>
                                <div className="relative">
                                    <User className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-3 pointer-events-none" />
                                    <input
                                        type="text"
                                        placeholder="EJ. CARLOS ALBERTO MENDOZA"
                                        value={formUsuario.data.nombres}
                                        onChange={(e) => formUsuario.setData('nombres', e.target.value.toUpperCase())}
                                        className="w-full pl-9 pr-3 py-2 text-xs font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl uppercase transition"
                                        required
                                        autoFocus
                                    />
                                </div>
                                {formUsuario.errors.nombres && (
                                    <p className="text-xs font-bold text-rose-600 mt-1">{formUsuario.errors.nombres}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Correo Electrónico de Acceso *
                                </label>
                                <div className="relative">
                                    <Mail className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-3 pointer-events-none" />
                                    <input
                                        type="email"
                                        placeholder="usuario@empresa.com"
                                        value={formUsuario.data.email}
                                        onChange={(e) => formUsuario.setData('email', e.target.value)}
                                        className="w-full pl-9 pr-3 py-2 text-sm bg-white border border-slate-200 rounded-lg transition"
                                        required
                                    />
                                </div>
                                {formUsuario.errors.email && (
                                    <p className="text-xs font-bold text-rose-600 mt-1">{formUsuario.errors.email}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 mb-1">
                                        Rol de Trabajo *
                                    </label>
                                    <select
                                        value={formUsuario.data.id_rol}
                                        onChange={(e) => formUsuario.setData('id_rol', e.target.value)}
                                        className="w-full text-xs font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-2.5 uppercase transition cursor-pointer"
                                        required
                                    >
                                        {roles.map((r) => (
                                            <option key={r.id_rol} value={r.id_rol}>
                                                {r.nombre.toUpperCase()}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 mb-1">
                                        Sede / Sucursal
                                    </label>
                                    <select
                                        value={formUsuario.data.id_sucursal}
                                        onChange={(e) => formUsuario.setData('id_sucursal', e.target.value)}
                                        className="w-full text-xs font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-2.5 uppercase transition cursor-pointer"
                                        required
                                    >
                                        {sucursales.map((s) => (
                                            <option key={s.id_sucursal} value={s.id_sucursal}>
                                                {s.nombre.toUpperCase()}
                                            </option>
                                        ))}
                                    </select>
                                    {formUsuario.errors.id_sucursal && <p className="text-xs font-bold text-rose-600 mt-1">{formUsuario.errors.id_sucursal}</p>}
                                </div>
                            </div>

                            {puedeAsignarSucursales && sucursales.length > 1 && (
                                <SucursalesAdicionales
                                    sucursales={sucursales}
                                    idSede={formUsuario.data.id_sucursal}
                                    seleccionadas={formUsuario.data.sucursales_adicionales}
                                    onCambiar={(ids) => formUsuario.setData('sucursales_adicionales', ids)}
                                    error={formUsuario.errors.sucursales_adicionales ?? Object.entries(formUsuario.errors).find(([campo]) => campo.startsWith('sucursales_adicionales.'))?.[1]}
                                />
                            )}

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 mb-1">
                                        Tipo Documento
                                    </label>
                                    <select
                                        value={formUsuario.data.id_tipo_documento}
                                        onChange={(e) => formUsuario.setData('id_tipo_documento', e.target.value)}
                                        className="w-full text-xs font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-2.5 uppercase transition cursor-pointer"
                                        required
                                    >
                                        {tiposDocumento.map((d) => (
                                            <option key={d.id_tipo_documento} value={d.id_tipo_documento}>
                                                {d.nombre.toUpperCase()}
                                            </option>
                                        ))}
                                    </select>
                                    {formUsuario.errors.id_tipo_documento && <p className="text-xs font-bold text-rose-600 mt-1">{formUsuario.errors.id_tipo_documento}</p>}
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 mb-1">
                                        N° de Documento
                                    </label>
                                    <input
                                        type="text"
                                        inputMode={documentoSoloNumerico ? 'numeric' : 'text'}
                                        placeholder={tipoDocumentoSeleccionado ? `N° ${tipoDocumentoSeleccionado.nombre.toUpperCase()}` : 'SELECCIONA TIPO'}
                                        value={formUsuario.data.num_documento}
                                        onChange={(e) => formUsuario.setData('num_documento', documentoSoloNumerico ? e.target.value.replace(/\D/g, '') : e.target.value.toUpperCase())}
                                        className="w-full font-mono font-semibold text-xs text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-3 transition"
                                        maxLength={tipoDocumentoSeleccionado?.longitud_exacta || 15}
                                        required
                                    />
                                    <p className="mt-1 text-xs font-medium text-slate-400">{tipoDocumentoSeleccionado?.longitud_exacta ? `Debe tener ${tipoDocumentoSeleccionado.longitud_exacta} caracteres.` : 'Longitud variable según el documento.'}</p>
                                    {formUsuario.errors.num_documento && <p className="text-xs font-bold text-rose-600 mt-1">{formUsuario.errors.num_documento}</p>}
                                </div>
                            </div>

                            <div className="p-3 bg-slate-50 border border-slate-200/80 rounded-lg space-y-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 mb-1">
                                        {usuarioEditando ? 'Nueva Contraseña (Opcional)' : 'Contraseña de Acceso *'}
                                    </label>
                                    <div className="relative">
                                        <KeyRound className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-3 pointer-events-none" />
                                        <input
                                            type="password"
                                            placeholder="••••••••••••"
                                            value={formUsuario.data.password}
                                            onChange={(e) => formUsuario.setData('password', e.target.value)}
                                            className="w-full pl-9 pr-3 py-2 text-sm bg-white border border-slate-200 rounded-lg transition"
                                            required={!usuarioEditando}
                                            minLength={8}
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 mb-1">
                                        Confirmar Contraseña {usuarioEditando ? '' : '*'}
                                    </label>
                                    <div className="relative">
                                        <Lock className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-3 pointer-events-none" />
                                        <input
                                            type="password"
                                            placeholder="••••••••••••"
                                            value={formUsuario.data.password_confirmation}
                                            onChange={(e) => formUsuario.setData('password_confirmation', e.target.value)}
                                            className="w-full pl-9 pr-3 py-2 text-sm bg-white border border-slate-200 rounded-lg transition"
                                            required={Boolean(formUsuario.data.password)}
                                            minLength={8}
                                        />
                                    </div>
                                </div>
                            </div>

                            {puedeGestionarPin && (
                                <div className="p-3 bg-amber-50/70 border border-amber-200/80 rounded-lg space-y-3">
                                    <div className="flex items-start gap-2">
                                        <KeyRound className="w-4 h-4 shrink-0 text-amber-700 mt-0.5" />
                                        <div>
                                            <p className="text-xs font-semibold text-amber-900">PIN para operaciones de caja</p>
                                            <p className="mt-0.5 text-xs leading-relaxed text-amber-800">
                                                {usuarioEditando
                                                    ? 'Déjalo vacío para conservar el PIN actual. Solo se solicita al abrir, cerrar o autorizar movimientos de caja.'
                                                    : 'Opcional para cuentas que operarán caja. No se puede recuperar ni se muestra después de guardarlo.'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <label className="block text-xs font-semibold text-amber-800 mb-1">PIN de caja</label>
                                            <input
                                                type="password"
                                                inputMode="numeric"
                                                autoComplete="new-password"
                                                placeholder="4 a 6 dígitos"
                                                value={formUsuario.data.pin_cajero}
                                                onChange={(e) => formUsuario.setData('pin_cajero', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                                className="w-full font-mono font-bold text-xs text-slate-800 bg-white border border-amber-200 rounded-xl py-2 px-3 focus:border-amber-600 transition"
                                            />
                                            {formUsuario.errors.pin_cajero && <p className="text-xs font-bold text-rose-600 mt-1">{formUsuario.errors.pin_cajero}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-xs font-semibold text-amber-800 mb-1">Confirmar PIN</label>
                                            <input
                                                type="password"
                                                inputMode="numeric"
                                                autoComplete="new-password"
                                                placeholder="Repite el PIN"
                                                value={formUsuario.data.pin_cajero_confirmation}
                                                onChange={(e) => formUsuario.setData('pin_cajero_confirmation', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                                className="w-full font-mono font-bold text-xs text-slate-800 bg-white border border-amber-200 rounded-xl py-2 px-3 focus:border-amber-600 transition"
                                            />
                                        </div>
                                    </div>
                                </div>
                            )}

                            {usuarioEditando && (
                                <div className="pt-1">
                                    <label className="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition select-none">
                                        <input
                                            type="checkbox"
                                            checked={formUsuario.data.estado}
                                            onChange={(e) => formUsuario.setData('estado', e.target.checked)}
                                            className="w-4 h-4 rounded text-emerald-800 border-slate-300 cursor-pointer"
                                        />
                                        <div>
                                            <p className="text-xs font-semibold text-slate-800">Cuenta Activa</p>
                                            <p className="text-xs text-slate-400 font-medium">
                                                Permitir autenticación en el panel y punto de venta.
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            )}

                            <div className="flex items-center gap-2 pt-3 border-t border-slate-100 shrink-0">
                                <button
                                    type="button"
                                    onClick={() => setModalUsuarioOpen(false)}
                                    className="flex-1 h-10 rounded-xl border border-slate-200 text-slate-600 font-semibold text-xs hover:bg-slate-50 transition cursor-pointer"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={formUsuario.processing}
                                    className="volumen-marca flex-1 h-10 rounded-xl text-on-brand font-semibold text-xs transition flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer hover:brightness-105"
                                >
                                    <Check className="w-4 h-4" />
                                    <span>{usuarioEditando ? 'Guardar Cambios' : 'Crear Usuario'}</span>
                                </button>
                            </div>
                        </form>
                </div>
                )}
            </Dialog>

            {/* MODAL 2: ESTADO NATIVO DE CARGANDO / ESPERA */}
            <Dialog open={Boolean(formUsuario.processing)} onClose={() => {}} size="sm" dismissible={false}>
                {(formUsuario.processing) && (
                <div className="p-6 text-center">
                        <div className="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center mx-auto mb-3">
                            <Loader2 className="w-6 h-6 animate-spin" />
                        </div>
                        <h4 className="text-sm font-semibold text-slate-900">Procesando Credenciales</h4>
                        <p className="text-xs text-slate-500 font-bold mt-1">
                            Sincronizando con Supabase...
                        </p>
                </div>
                )}
            </Dialog>

            {/* MODAL 3: CONFIRMACIÓN PROPIA DE DESACTIVACIÓN */}
            <Dialog open={Boolean(usuarioAEliminar)} onClose={() => setUsuarioAEliminar(null)} size="sm">
                {(usuarioAEliminar) && (
                <div className="p-6 text-center">
                        <div className="w-12 h-12 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-3">
                            <AlertTriangle className="w-6 h-6" />
                        </div>
                        <h4 className="text-sm font-semibold text-slate-900">¿Desactivar Cuenta?</h4>
                        <p className="text-xs text-slate-500 font-medium mt-1 leading-relaxed">
                            Estás a punto de deshabilitar el acceso a <strong className="text-slate-800">{usuarioAEliminar.nombres}</strong> ({usuarioAEliminar.email}).
                        </p>
                        <p className="text-xs text-amber-700 bg-amber-50 border border-amber-200 p-2 rounded-xl mt-3 text-left font-bold">
                            ⚠️ El usuario no podrá iniciar sesión en ninguna sede ni realizar cobros hasta que sea reactivado.
                        </p>

                        <div className="flex items-center gap-2 mt-4">
                            <button
                                type="button"
                                onClick={() => setUsuarioAEliminar(null)}
                                className="flex-1 h-10 rounded-xl border border-slate-200 text-slate-600 font-semibold text-xs hover:bg-slate-50 transition cursor-pointer"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={ejecutarEliminarUsuario}
                                className="flex-1 h-10 rounded-xl bg-rose-600 text-white font-semibold text-xs hover:bg-rose-700 transition flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <Trash2 className="w-4 h-4" />
                                <span>Desactivar</span>
                            </button>
                        </div>
                </div>
                )}
            </Dialog>

            {/* MODAL 4: DIÁLOGO DE NOTIFICACIÓN PROPIO (ÉXITO / ERROR / ALERTA) */}
            <Dialog open={Boolean(dialogo)} onClose={() => setDialogo(null)} size="sm">
                {(dialogo) && (
                <div className="p-6 text-center">
                        <div
                            className={`w-12 h-12 rounded-lg flex items-center justify-center mx-auto mb-3 ${
                                dialogo.tipo === 'exito'
                                    ? 'bg-emerald-50 text-emerald-800'
                                    : dialogo.tipo === 'error'
                                    ? 'bg-rose-50 text-rose-600'
                                    : 'bg-amber-50 text-amber-600'
                            }`}
                        >
                            {dialogo.tipo === 'exito' && <CheckCircle2 className="w-6 h-6 stroke-[2]" />}
                            {dialogo.tipo === 'error' && <AlertCircle className="w-6 h-6 stroke-[2]" />}
                            {dialogo.tipo === 'alerta' && <AlertTriangle className="w-6 h-6 stroke-[2]" />}
                        </div>

                        <h3 className="text-sm font-semibold text-slate-900 tracking-tight">
                            {dialogo.titulo}
                        </h3>
                        <p className="text-xs text-slate-600 font-medium mt-1.5 leading-relaxed">
                            {dialogo.mensaje}
                        </p>

                        <button
                            type="button"
                            onClick={() => setDialogo(null)}
                            className="volumen-marca w-full mt-4 h-10 rounded-xl text-on-brand font-semibold text-xs transition cursor-pointer hover:brightness-105"
                        >
                            Entendido
                        </button>
                </div>
                )}
            </Dialog>
        </AuthenticatedLayout>
    );
}
