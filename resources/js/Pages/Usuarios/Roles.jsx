import React, { useState, useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader, Dialog } from '@/Components/ui';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Shield,
    ShieldCheck,
    Plus,
    Edit3,
    Trash2,
    X,
    Check,
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    Loader2,
    Search,
    Lock,
    Layers,
    CheckSquare,
    Square
} from 'lucide-react';

export default function Roles({ auth, roles, permisos = [] }) {
    // Normalizar lista de roles (soporta paginación de Laravel o colecciones simples)
    const rows = roles?.data ?? (Array.isArray(roles) ? roles : []);

    const [busqueda, setBusqueda] = useState('');
    const [filtroPermiso, setFiltroPermiso] = useState('');

    // Estados de Ventanas Emergentes Nativas
    const [modalFormOpen, setModalFormOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [rolAEliminar, setRolAEliminar] = useState(null);
    const [dialogo, setDialogo] = useState(null);

    // Formulario reactivo con Inertia
    const form = useForm({
        nombre: '',
        descripcion: '',
        estado: true,
        permisos: []
    });

    // Filtro reactivo en la tabla principal
    const listaFiltrada = useMemo(() => {
        if (!busqueda.trim()) return rows;
        const q = busqueda.toUpperCase().trim();
        return rows.filter(
            (r) =>
                (r.nombre || '').toUpperCase().includes(q) ||
                (r.descripcion || '').toUpperCase().includes(q)
        );
    }, [rows, busqueda]);

    // Permisos agrupados por módulo para visualización profesional
    const permisosAgrupados = useMemo(() => {
        const q = filtroPermiso.toLowerCase().trim();
        const filtrados = permisos.filter(
            (p) =>
                (p.nombre || '').toLowerCase().includes(q) ||
                (p.modulo || '').toLowerCase().includes(q)
        );

        return filtrados.reduce((grupos, perm) => {
            const modulo = (perm.modulo || 'GENERAL').toUpperCase();
            if (!grupos[modulo]) grupos[modulo] = [];
            grupos[modulo].push(perm);
            return grupos;
        }, {});
    }, [permisos, filtroPermiso]);

    // Abrir modal para crear
    const abrirModalCrear = () => {
        setEditing(null);
        setFiltroPermiso('');
        form.clearErrors();
        form.reset();
        form.setData({
            nombre: '',
            descripcion: '',
            estado: true,
            permisos: []
        });
        setModalFormOpen(true);
    };

    // Abrir modal para editar
    const abrirModalEditar = (role) => {
        setEditing(role);
        setFiltroPermiso('');
        form.clearErrors();
        form.setData({
            nombre: (role.nombre || '').toUpperCase(),
            descripcion: (role.descripcion || '').toUpperCase(),
            estado: Boolean(role.estado ?? true),
            permisos: Array.isArray(role.permisos)
                ? role.permisos.map((p) => (typeof p === 'object' ? p.id_permiso : p))
                : []
        });
        setModalFormOpen(true);
    };

    // Toggle de un permiso individual
    const togglePermiso = (id) => {
        form.setData(
            'permisos',
            form.data.permisos.includes(id)
                ? form.data.permisos.filter((p) => p !== id)
                : [...form.data.permisos, id]
        );
    };

    // Seleccionar o deseleccionar todos los permisos
    const toggleTodosPermisos = () => {
        if (form.data.permisos.length === permisos.length) {
            form.setData('permisos', []);
        } else {
            form.setData('permisos', permisos.map((p) => p.id_permiso));
        }
    };

    // Validación y Envío
    const handleSubmit = (e) => {
        e.preventDefault();

        const nombreLimpio = form.data.nombre.trim().toUpperCase();

        if (nombreLimpio.length < 3) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'NOMBRE INVÁLIDO',
                mensaje: 'EL NOMBRE DEL ROL DEBE TENER AL MENOS 3 CARACTERES (EJ. CAJERO, SUPERVISOR).'
            });
            return;
        }

        // Validación de nombres duplicados en frontend
        const idActual = editing ? editing.id_rol : null;
        const yaExiste = rows.some(
            (r) => r.id_rol !== idActual && (r.nombre || '').trim().toUpperCase() === nombreLimpio
        );

        if (yaExiste) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'ROL DUPLICADO',
                mensaje: `YA EXISTE UN PERFIL REGISTRADO CON EL NOMBRE "${nombreLimpio}". ESPECIFICA UNO DISTINTO.`
            });
            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setModalFormOpen(false);
                form.reset();
                setEditing(null);
                setDialogo({
                    tipo: 'exito',
                    titulo: editing ? 'ROL ACTUALIZADO' : 'ROL REGISTRADO',
                    mensaje: `EL PERFIL DE ACCESO "${nombreLimpio}" SE GUARDÓ CORRECTAMENTE EN LA BASE DE DATOS.`
                });
            },
            onError: (errs) => {
                const primerError = Object.values(errs)[0] || 'OCURRIÓ UN PROBLEMA AL PROCESAR EL FORMULARIO.';
                setDialogo({
                    tipo: 'error',
                    titulo: 'ERROR AL GUARDAR',
                    mensaje: String(primerError).toUpperCase()
                });
            }
        };

        if (editing) {
            form.put(`/roles/${editing.id_rol}`, options);
        } else {
            form.post('/roles', options);
        }
    };

    // Ejecutar desactivación
    const ejecutarEliminacion = () => {
        if (!rolAEliminar) return;
        const role = rolAEliminar;

        router.delete(`/roles/${role.id_rol}`, {
            preserveScroll: true,
            onSuccess: () => {
                setRolAEliminar(null);
                setDialogo({
                    tipo: 'exito',
                    titulo: 'ROL DESACTIVADO',
                    mensaje: `EL PERFIL "${role.nombre.toUpperCase()}" FUE DESACTIVADO SATISFACTORIAMENTE.`
                });
            },
            onError: () => {
                setRolAEliminar(null);
                setDialogo({
                    tipo: 'error',
                    titulo: 'OPERACIÓN RESTRINGIDA',
                    mensaje: 'ESTE ROL NO PUEDE SER DESACTIVADO PORQUE TIENE USUARIOS ACTIVOS ASIGNADOS EN EL SISTEMA.'
                });
            }
        });
    };

    return (
        <AuthenticatedLayout header="Roles y permisos">
            <Head title="Roles y permisos" />

            <div className="space-y-5">
                <PageHeader
                    breadcrumbs={[{ label: 'Configuración' }, { label: 'Roles y permisos' }]}
                    title="Roles y permisos"
                    description="Gestión de permisos, privilegios de módulos y reglas de acceso al sistema."
                    actions={
                        <>
                        <div className="flex items-center gap-3">
                            <div className="relative flex-1 md:w-72">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" />
                                <input
                                    type="text"
                                    placeholder="Buscar por rol o descripción…"
                                    value={busqueda}
                                    onChange={(e) => setBusqueda(e.target.value.toUpperCase())}
                                    className="w-full pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-800 placeholder:text-slate-400 uppercase transition"
                                />
                            </div>

                            <button
                                type="button"
                                onClick={abrirModalCrear}
                                className="h-9 px-4 rounded-xl bg-brand text-on-brand font-semibold text-xs flex items-center gap-2 hover:bg-brand-hover transition shrink-0 cursor-pointer"
                            >
                                <Plus className="w-4 h-4" />
                                <span>Nuevo Rol</span>
                            </button>
                        </div>
                        </>
                    }
                />

                {/* TABLA DE ANCHO COMPLETO */}
                <div className="bg-white rounded-2xl overflow-hidden shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-slate-100 bg-white text-xs font-medium text-slate-500">
                                    <th className="py-2.5 px-4 font-medium">Perfil / rol</th>
                                    <th className="py-2.5 px-4 font-medium">Descripción de responsabilidades</th>
                                    <th className="py-2.5 px-4 text-center font-medium">Permisos habilitados</th>
                                    <th className="py-2.5 px-4 text-center font-medium">Estado</th>
                                    <th className="py-2.5 px-4 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-sm text-slate-700">
                                {listaFiltrada.length > 0 ? (
                                    listaFiltrada.map((role) => {
                                        const countPermisos = Array.isArray(role.permisos) ? role.permisos.length : 0;

                                        return (
                                            <tr key={role.id_rol} className="hover:bg-brand-soft/40 transition-colors">
                                                <td className="py-2.5 px-4">
                                                    <div className="flex items-center gap-2">
                                                        <span className="p-1.5 bg-slate-100 text-slate-700 rounded-lg">
                                                            <Lock className="w-3.5 h-3.5" />
                                                        </span>
                                                        <div>
                                                            <p className="font-semibold text-slate-900">{role.nombre}</p>
                                                            <span className="text-xs text-slate-400 font-bold uppercase">
                                                                ID: #{role.id_rol}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="py-2.5 px-4">
                                                    <p className="text-slate-600 font-semibold max-w-md truncate text-xs">
                                                        {role.descripcion || 'SIN DESCRIPCIÓN ESPECIFICADA'}
                                                    </p>
                                                </td>
                                                <td className="py-2.5 px-4 text-center">
                                                    <span className="font-mono font-semibold text-xs text-slate-700 bg-slate-100 px-2.5 py-0.5 rounded-md border border-slate-200/60 uppercase">
                                                        {countPermisos} PERMISOS
                                                    </span>
                                                </td>
                                                <td className="py-2.5 px-4 text-center">
                                                    <span className={`inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full uppercase ${
                                                        role.estado !== false
                                                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                            : 'bg-slate-100 text-slate-400'
                                                    }`}>
                                                        <span className={`w-1.5 h-1.5 rounded-full ${role.estado !== false ? 'bg-emerald-500' : 'bg-slate-400'}`} />
                                                        {role.estado !== false ? 'ACTIVO' : 'INACTIVO'}
                                                    </span>
                                                </td>
                                                <td className="py-2.5 px-4 text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <button
                                                            type="button"
                                                            onClick={() => abrirModalEditar(role)}
                                                            className="p-1.5 text-slate-400 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition cursor-pointer"
                                                            title="Editar perfil y permisos"
                                                        >
                                                            <Edit3 className="w-4 h-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => setRolAEliminar(role)}
                                                            className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                                                            title="Desactivar perfil"
                                                        >
                                                            <Trash2 className="w-4 h-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan="5" className="py-12 text-center text-slate-400">
                                            <Shield className="w-8 h-8 mx-auto mb-2 text-slate-300" />
                                            <p className="font-semibold text-xs text-slate-600">No se encontraron perfiles</p>
                                            <p className="text-xs text-slate-400 mt-0.5">
                                                Prueba con otro término de búsqueda o crea un nuevo rol.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* MODAL 1: REGISTRAR / EDITAR ROL Y ASIGNAR PERMISOS */}
            <Dialog open={Boolean(modalFormOpen)} onClose={() => setModalFormOpen(false)} size="2xl">
                {(modalFormOpen) && (
                <div className="p-6 flex flex-col">
                        {/* Cabecera del modal */}
                        <div className="flex justify-between items-center pb-3 border-b border-slate-100 shrink-0">
                            <div className="flex items-center gap-2">
                                <span className="p-2 bg-slate-100 text-slate-700 rounded-xl">
                                    <ShieldCheck className="w-4 h-4" />
                                </span>
                                <div>
                                    <h3 className="text-sm font-semibold text-slate-900">
                                        {editing ? 'Editar Perfil de Acceso' : 'Nuevo Perfil de Acceso'}
                                    </h3>
                                    <p className="text-xs text-slate-400 font-bold">
                                        Define el nombre y marca los permisos requeridos
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setModalFormOpen(false)}
                                className="p-1 text-slate-400 hover:text-slate-600 rounded-lg transition cursor-pointer"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Cuerpo con scroll */}
                        <form onSubmit={handleSubmit} className="mt-4 space-y-4 overflow-y-auto pr-1 flex-1 [scrollbar-width:thin]">
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Nombre del Rol *
                                </label>
                                <input
                                    type="text"
                                    placeholder="EJ. ADMINISTRADOR, CAJERO, SUPERVISOR, LOGÍSTICA..."
                                    value={form.data.nombre}
                                    onChange={(e) => form.setData('nombre', e.target.value.toUpperCase())}
                                    className="w-full font-semibold text-xs text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-3 uppercase transition"
                                    required
                                    autoFocus
                                />
                                {form.errors.nombre && (
                                    <p className="text-xs font-bold text-rose-600 mt-1">{form.errors.nombre}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Descripción de Responsabilidades
                                </label>
                                <textarea
                                    rows="2"
                                    placeholder="DETALLES SOBRE EL ALCANCE Y RESTRICCIONES DE ESTE PERFIL..."
                                    value={form.data.descripcion}
                                    onChange={(e) => form.setData('descripcion', e.target.value.toUpperCase())}
                                    className="w-full font-bold text-xs text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-3 uppercase transition resize-none"
                                />
                            </div>

                            {/* SECCIÓN DE PERMISOS */}
                            <div className="pt-2 border-t border-slate-100">
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2.5">
                                    <div className="flex items-center gap-2">
                                        <Layers className="w-4 h-4 text-slate-400" />
                                        <label className="text-xs font-semibold text-slate-700">
                                            Matriz de Permisos ({form.data.permisos.length} de {permisos.length} activos)
                                        </label>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <div className="relative">
                                            <Search className="w-3 h-3 text-slate-400 absolute left-2 top-2 pointer-events-none" />
                                            <input
                                                type="text"
                                                placeholder="Filtrar permiso..."
                                                value={filtroPermiso}
                                                onChange={(e) => setFiltroPermiso(e.target.value)}
                                                className="w-36 pl-6 pr-2 py-1 bg-slate-100 border-none rounded-lg text-xs font-bold text-slate-800 placeholder:text-slate-400"
                                            />
                                        </div>

                                        <button
                                            type="button"
                                            onClick={toggleTodosPermisos}
                                            className="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-xs font-semibold text-slate-600 transition flex items-center gap-1 cursor-pointer"
                                        >
                                            {form.data.permisos.length === permisos.length ? (
                                                <>
                                                    <Square className="w-3 h-3" /> Desmarcar todo
                                                </>
                                            ) : (
                                                <>
                                                    <CheckSquare className="w-3 h-3" /> Marcar todo
                                                </>
                                            )}
                                        </button>
                                    </div>
                                </div>

                                <div className="max-h-60 overflow-y-auto space-y-3 p-3 bg-slate-50 border border-slate-200/80 rounded-lg [scrollbar-width:thin]">
                                    {Object.keys(permisosAgrupados).length > 0 ? (
                                        Object.entries(permisosAgrupados).map(([modulo, items]) => (
                                            <div key={modulo} className="space-y-1.5">
                                                <span className="text-xs font-semibold text-slate-400 block pl-1">
                                                    Módulo: {modulo}
                                                </span>
                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                                    {items.map((perm) => {
                                                        const checked = form.data.permisos.includes(perm.id_permiso);
                                                        return (
                                                            <label
                                                                key={perm.id_permiso}
                                                                className={`flex items-start gap-2 p-2 rounded-xl border text-xs font-bold transition cursor-pointer select-none ${
                                                                    checked
                                                                        ? 'border-emerald-800 bg-emerald-50/50 text-emerald-950'
                                                                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
                                                                }`}
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    checked={checked}
                                                                    onChange={() => togglePermiso(perm.id_permiso)}
                                                                    className="mt-0.5 rounded text-emerald-800 border-slate-300 cursor-pointer"
                                                                />
                                                                <div className="min-w-0">
                                                                    <p className="leading-tight text-xs truncate">
                                                                        {perm.nombre}
                                                                    </p>
                                                                    {perm.descripcion && (
                                                                        <span className="text-xs font-semibold text-slate-400 block truncate">
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
                                        <p className="text-center py-6 text-xs text-slate-400 font-bold">
                                            No se encontraron permisos coincidentes
                                        </p>
                                    )}
                                </div>
                            </div>

                            {editing && (
                                <div className="pt-2 border-t border-slate-100">
                                    <label className="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition select-none">
                                        <input
                                            type="checkbox"
                                            checked={form.data.estado}
                                            onChange={(e) => form.setData('estado', e.target.checked)}
                                            className="w-4 h-4 rounded text-emerald-800 border-slate-300 cursor-pointer"
                                        />
                                        <div>
                                            <p className="text-xs font-semibold text-slate-800">Perfil Activo</p>
                                            <p className="text-xs text-slate-400 font-medium">
                                                Disponible para ser asignado a usuarios del sistema.
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            )}

                            {/* Botones de acción del formulario */}
                            <div className="flex items-center gap-2 pt-3 border-t border-slate-100 shrink-0">
                                <button
                                    type="button"
                                    onClick={() => setModalFormOpen(false)}
                                    className="flex-1 h-10 rounded-xl border border-slate-200 text-slate-600 font-semibold text-xs hover:bg-slate-50 transition cursor-pointer"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="volumen-marca flex-1 h-10 rounded-xl text-on-brand font-semibold text-xs transition flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer hover:brightness-105"
                                >
                                    <Check className="w-4 h-4" />
                                    <span>{editing ? 'Guardar Cambios' : 'Crear Perfil'}</span>
                                </button>
                            </div>
                        </form>
                </div>
                )}
            </Dialog>

            {/* MODAL 2: ESTADO NATIVO DE CARGANDO / PROCESANDO (ESPERA) */}
            <Dialog open={Boolean(form.processing)} onClose={() => {}} size="sm" dismissible={false}>
                {(form.processing) && (
                <div className="p-6 text-center">
                        <div className="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-800 flex items-center justify-center mx-auto mb-3">
                            <Loader2 className="w-6 h-6 animate-spin" />
                        </div>
                        <h4 className="text-sm font-semibold text-slate-900">Procesando Solicitud</h4>
                        <p className="text-xs text-slate-500 font-bold mt-1">
                            Guardando cambios en Supabase...
                        </p>
                </div>
                )}
            </Dialog>

            {/* MODAL 3: CONFIRMACIÓN PROPIA DE DESACTIVACIÓN */}
            <Dialog open={Boolean(rolAEliminar)} onClose={() => setRolAEliminar(null)} size="sm">
                {(rolAEliminar) && (
                <div className="p-6 text-center">
                        <div className="w-12 h-12 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-3">
                            <AlertTriangle className="w-6 h-6" />
                        </div>
                        <h4 className="text-sm font-semibold text-slate-900">¿Desactivar Perfil?</h4>
                        <p className="text-xs text-slate-500 font-medium mt-1 leading-relaxed">
                            Estás a punto de desactivar el rol <strong className="text-slate-800">{rolAEliminar.nombre}</strong>.
                        </p>
                        <p className="text-xs text-amber-700 bg-amber-50 border border-amber-200 p-2 rounded-xl mt-3 text-left font-bold">
                            ⚠️ Si este perfil está asignado a usuarios en sesión, el sistema rechazará la acción para preservar el control de accesos.
                        </p>

                        <div className="flex items-center gap-2 mt-4">
                            <button
                                type="button"
                                onClick={() => setRolAEliminar(null)}
                                className="flex-1 h-10 rounded-xl border border-slate-200 text-slate-600 font-semibold text-xs hover:bg-slate-50 transition cursor-pointer"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={ejecutarEliminacion}
                                className="flex-1 h-10 rounded-xl bg-rose-600 text-white font-semibold text-xs hover:bg-rose-700 transition flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <Trash2 className="w-4 h-4" />
                                <span>Sí, Desactivar</span>
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