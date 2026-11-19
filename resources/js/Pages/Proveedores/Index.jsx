import React, { useState, useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, ConfirmDialog, Dialog, NoticeDialog, PageHeader } from '@/Components/ui';
import { Head, useForm, router } from '@inertiajs/react';
import axios from 'axios';
import { PREFIJOS_RUC } from '@/Utils/documentos';
import CampoTelefono, { limpiarTelefono } from '@/Components/Contactos/CampoTelefono';
import {
    Truck,
    Building2,
    Search,
    Phone,
    Mail,
    Globe,
    MapPin,
    Edit3,
    Trash2,
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    Info,
    X,
    Loader2,
    Check,
    Lock,
    Plus
} from 'lucide-react';

export default function Index({ auth, proveedores = [], proveedores_con_ingresos = [] }) {
    // Normalizar colección de proveedores (soporta array directo o paginación Laravel)
    const providerRows = useMemo(() => {
        if (Array.isArray(proveedores)) return proveedores;
        if (proveedores?.data) return proveedores.data;
        return [];
    }, [proveedores]);

    // Búsqueda en la tabla
    const [busqueda, setBusqueda] = useState('');

    // Estados de Modales Nativos
    const [modalProveedorOpen, setModalProveedorOpen] = useState(false);
    const [editando, setEditando] = useState(false);
    const [idActual, setIdActual] = useState(null);
    const [buscandoRuc, setBuscandoRuc] = useState(false);
    const [tipoTelefono, setTipoTelefono] = useState('movil_pe'); // 'movil_pe' | 'fijo_pe' | 'internacional'
    const [provAEliminar, setProvAEliminar] = useState(null);
    const [dialogo, setDialogo] = useState(null); // { tipo: 'exito' | 'error' | 'alerta' | 'info', titulo: '', mensaje: '' }

    // Permisos y restricciones de auditoría
    const puedeEditarDocumento = auth?.user?.permissions?.includes('proveedores.editar_documento');
    const tieneIngresos = editando ? proveedores_con_ingresos?.includes(idActual) : false;

    // Formulario de Inertia
    const { data, setData, post, put, processing, reset, errors, clearErrors } = useForm({
        ruc: '',
        razon_social: '',
        direccion: '',
        telefono: '',
        email: '',
        web: '',
        motivo_cambio_documento: '',
        estado: true
    });

    // Filtro reactivo en la tabla
    const proveedoresFiltrados = useMemo(() => {
        if (!busqueda.trim()) return providerRows;
        const q = busqueda.toUpperCase().trim();
        return providerRows.filter((p) => {
            const rucVal = (p.ruc ?? p.num_documento ?? '').toUpperCase();
            const razonVal = (p.razon_social ?? '').toUpperCase();
            const telVal = (p.telefono ?? '').toUpperCase();
            const emailVal = (p.email ?? '').toUpperCase();
            const webVal = (p.web ?? '').toUpperCase();

            return (
                rucVal.includes(q) ||
                razonVal.includes(q) ||
                telVal.includes(q) ||
                emailVal.includes(q) ||
                webVal.includes(q)
            );
        });
    }, [providerRows, busqueda]);

    // Abrir Modal para Crear Proveedor
    const abrirModalCrear = () => {
        setEditando(false);
        setIdActual(null);
        clearErrors();
        reset();
        setData({
            ruc: '',
            razon_social: '',
            direccion: '',
            telefono: '',
            email: '',
            web: '',
            motivo_cambio_documento: '',
            estado: true
        });
        setTipoTelefono('movil_pe');
        setModalProveedorOpen(true);
    };

    // Abrir Modal para Editar Proveedor
    const abrirModalEditar = (prov) => {
        setEditando(true);
        setIdActual(prov.id_proveedor);
        clearErrors();

        const telGuardado = prov.telefono ? String(prov.telefono).trim() : '';
        if (telGuardado.startsWith('+') && !telGuardado.startsWith('+51')) {
            setTipoTelefono('internacional');
        } else if (telGuardado.length <= 8 && !telGuardado.startsWith('9')) {
            setTipoTelefono('fijo_pe');
        } else {
            setTipoTelefono('movil_pe');
        }

        setData({
            ruc: (prov.ruc ?? prov.num_documento) || '',
            razon_social: (prov.razon_social || '').toUpperCase(),
            direccion: (prov.direccion || '').toUpperCase(),
            telefono: telGuardado.replace('+51 ', ''),
            email: prov.email || '',
            web: prov.web || '',
            motivo_cambio_documento: '',
            estado: Boolean(prov.estado ?? true)
        });
        setModalProveedorOpen(true);
    };

    // Consulta de RUC a SUNAT
    const consultarRuc = async () => {
        const rucLimpio = data.ruc.trim();

        if (!rucLimpio) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'RUC REQUERIDO',
                mensaje: 'INGRESA EL NÚMERO DE RUC ANTES DE SOLICITAR LA CONSULTA A SUNAT.'
            });
            return;
        }

        if (rucLimpio.length !== 11) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'RUC INCOMPLETO',
                mensaje: 'EL RUC DEBE TENER EXACTAMENTE 11 DÍGITOS NUMÉRICOS.'
            });
            return;
        }

        if (!PREFIJOS_RUC.includes(rucLimpio.slice(0, 2))) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'PREFIJO DE RUC NO VÁLIDO',
                mensaje: 'EN EL PERÚ TODO RUC OFICIAL DEBE COMENZAR CON "10", "15", "16", "17" (PERSONA NATURAL) O "20" (PERSONA JURÍDICA).'
            });
            return;
        }

        setBuscandoRuc(true);
        try {
            const endpoint = window.route ? route('documentos.lookup') : '/api/documentos/consulta';
            const response = await axios.post(
                endpoint,
                {
                    context: 'proveedor',
                    tipo: 'ruc',
                    documento: rucLimpio
                },
                {
                    headers: { Accept: 'application/json' }
                }
            );

            const docData = response.data?.data || response.data;
            const razonSocial = docData?.nombre || docData?.razon_social || docData?.nombre_razon_social;
            const direccionFiscal = docData?.direccion || docData?.direccion_fiscal;

            if (razonSocial) {
                const razonUpper = String(razonSocial).toUpperCase();
                const dirUpper = direccionFiscal ? String(direccionFiscal).toUpperCase() : data.direccion;

                setData((prev) => ({
                    ...prev,
                    razon_social: razonUpper,
                    direccion: dirUpper
                }));

                setDialogo({
                    tipo: 'exito',
                    titulo: 'RAZÓN SOCIAL RECUPERADA (SUNAT)',
                    mensaje: `SE VERIFICÓ FISCALMENTE A "${razonUpper}". COMPLETA EL CONTACTO Y GUARDA EL REGISTRO.`
                });
            } else {
                setDialogo({
                    tipo: 'info',
                    titulo: 'SIN DATOS AUTOMÁTICOS',
                    mensaje: 'EL PADRÓN FISCAL NO DEVOLVIÓ EL NOMBRE DE LA EMPRESA. INGRESA LA RAZÓN SOCIAL MANUALMENTE.'
                });
            }
        } catch (error) {
            const status = error.response?.status;
            const msg = error.response?.data?.message;

            if (status === 401 || status === 403) {
                setDialogo({
                    tipo: 'error',
                    titulo: 'PERMISO DENEGADO',
                    mensaje: 'NO TIENES PRIVILEGIOS DE CONSULTA EN SUNAT. CONTACTA AL ADMINISTRADOR.'
                });
            } else if (status === 502 || status === 503 || msg?.includes('consulta documental')) {
                setDialogo({
                    tipo: 'error',
                    titulo: 'SERVICIO SUNAT NO DISPONIBLE',
                    mensaje: 'EL SERVIDOR DE CONSULTA NO RESPONDE EN ESTE MOMENTO. DIGITA LA RAZÓN SOCIAL DIRECTAMENTE.'
                });
            } else {
                setDialogo({
                    tipo: 'error',
                    titulo: 'ERROR EN CONSULTA FISCAL',
                    mensaje: String(msg || 'NO FUE POSIBLE CONECTAR CON EL SERVICIO DE CONSULTA DE PADRÓN.').toUpperCase()
                });
            }
        } finally {
            setBuscandoRuc(false);
        }
    };

    // Formateador de teléfono
    const handleTelefonoChange = (e) => setData('telefono', limpiarTelefono(tipoTelefono, e.target.value));

    // Validación estricta y envío del formulario
    const handleSubmit = (e) => {
        e.preventDefault();

        const rucLimpio = data.ruc.trim();
        if (rucLimpio.length !== 11) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'RUC INVÁLIDO',
                mensaje: 'EL RUC DEL PROVEEDOR DEBE TENER EXACTAMENTE 11 DÍGITOS NUMÉRICOS.'
            });
            return;
        }

        if (!PREFIJOS_RUC.includes(rucLimpio.slice(0, 2))) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'PREFIJO DE RUC NO VÁLIDO',
                mensaje: 'EL RUC INGRESADO DEBE COMENZAR CON "10", "15", "17" O "20".'
            });
            return;
        }

        const razonLimpia = data.razon_social.trim().toUpperCase();
        if (razonLimpia.length < 3) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'RAZÓN SOCIAL REQUERIDA',
                mensaje: 'DEBES ESPECIFICAR LA DENOMINACIÓN COMERCIAL DEL PROVEEDOR (MÍNIMO 3 CARACTERES).'
            });
            return;
        }

        // Validación de teléfono si fue ingresado
        const tel = data.telefono?.trim();
        if (tel && tel.length > 0) {
            if (tipoTelefono === 'movil_pe' && (tel.length !== 9 || !tel.startsWith('9'))) {
                setDialogo({
                    tipo: 'alerta',
                    titulo: 'CELULAR PERUANO INVÁLIDO',
                    mensaje: 'EL NÚMERO CELULAR DEBE TENER 9 DÍGITOS Y EMPEZAR CON 9 (EJ. 964112233).'
                });
                return;
            }
            if (tipoTelefono === 'fijo_pe') {
                const soloDigitos = tel.replace(/\D/g, '');
                if (soloDigitos.length < 7 || soloDigitos.length > 9) {
                    setDialogo({
                        tipo: 'alerta',
                        titulo: 'TELÉFONO FIJO INVÁLIDO',
                        mensaje: 'EL TELÉFONO FIJO EN PERÚ DEBE CONTENER ENTRE 7 Y 9 DÍGITOS (EJ. 01-445566 O 064-241122).'
                    });
                    return;
                }
            }
        }

        const endpoint = editando
            ? (window.route ? route('proveedores.update', idActual) : `/proveedores/${idActual}`)
            : (window.route ? route('proveedores.store') : '/proveedores');

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setModalProveedorOpen(false);
                reset();
                setDialogo({
                    tipo: 'exito',
                    titulo: editando ? 'PROVEEDOR ACTUALIZADO' : 'PROVEEDOR REGISTRADO',
                    mensaje: `LOS DATOS DE "${razonLimpia}" QUEDARON REGISTRADOS CORRECTAMENTE PARA COMPRAS E INGRESOS EN KARDEX.`
                });
            },
            onError: (errs) => {
                const primerError = Object.values(errs)[0] || 'VERIFICA QUE EL RUC NO ESTÉ DUPLICADO EN EL SISTEMA.';
                setDialogo({
                    tipo: 'error',
                    titulo: 'ERROR AL GUARDAR',
                    mensaje: String(primerError).toUpperCase()
                });
            }
        };

        if (editando) {
            put(endpoint, options);
        } else {
            post(endpoint, options);
        }
    };

    // Ejecutar desactivación de proveedor
    const ejecutarEliminacion = () => {
        if (!provAEliminar) return;
        const prov = provAEliminar;

        const endpoint = window.route
            ? route('proveedores.destroy', prov.id_proveedor)
            : `/proveedores/${prov.id_proveedor}`;

        router.delete(endpoint, {
            preserveScroll: true,
            onSuccess: () => {
                setProvAEliminar(null);
                setDialogo({
                    tipo: 'exito',
                    titulo: 'PROVEEDOR RETIRADO',
                    mensaje: `"${prov.razon_social.toUpperCase()}" FUE RETIRADO DE LAS COMPRAS ACTIVAS SIN ALTERAR EL HISTORIAL DE KARDEX.`
                });
            },
            onError: () => {
                setProvAEliminar(null);
                setDialogo({
                    tipo: 'error',
                    titulo: 'OPERACIÓN RESTRINGIDA',
                    mensaje: 'NO SE PUEDE DESACTIVAR ESTE PROVEEDOR EN ESTE MOMENTO.'
                });
            }
        });
    };

    return (
        <AuthenticatedLayout header="Proveedores">
            <Head title="Proveedores" />

            <div className="space-y-5">
                <PageHeader
                    breadcrumbs={[{ label: 'Gestión' }, { label: 'Proveedores' }]}
                    title="Proveedores"
                    description="Administración de casas comerciales, consulta RUC en SUNAT y control de kardex."
                    actions={
                        <>
                        <div className="flex items-center gap-3">
                            <div className="relative flex-1 md:w-72">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-3 pointer-events-none" />
                                <input
                                    type="text"
                                    placeholder="Buscar por RUC, razón social o contacto…"
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
                                <span>Nuevo Proveedor</span>
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
                                    <th className="py-2.5 px-4 font-medium">RUC oficial</th>
                                    <th className="py-2.5 px-4 font-medium">Empresa / razón social</th>
                                    <th className="py-2.5 px-4 font-medium">Canal de contacto</th>
                                    <th className="py-2.5 px-4 text-center font-medium">Estado</th>
                                    <th className="py-2.5 px-4 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-sm text-slate-700">
                                {proveedoresFiltrados.length > 0 ? (
                                    proveedoresFiltrados.map((prov) => {
                                        const estaActivo = prov.estado !== false;

                                        return (
                                            <tr key={prov.id_proveedor} className="hover:bg-brand-soft/40 transition-colors">
                                                <td className="py-2.5 px-4">
                                                    <span className="inline-block px-2 py-0.5 rounded-md text-xs font-mono font-semibold mb-1 bg-slate-100 text-slate-700 border border-slate-200/60 uppercase">
                                                        RUC
                                                    </span>
                                                    <p className="font-mono font-semibold text-slate-900 text-xs">
                                                        {prov.ruc ?? prov.num_documento}
                                                    </p>
                                                </td>

                                                <td className="py-2.5 px-4">
                                                    <p className="font-semibold text-slate-900">{prov.razon_social}</p>
                                                    {prov.direccion ? (
                                                        <div className="flex items-center gap-1 text-slate-400 text-xs font-semibold mt-0.5 truncate max-w-sm uppercase">
                                                            <MapPin className="w-3 h-3 shrink-0 text-slate-400" />
                                                            <span className="truncate">{prov.direccion}</span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-slate-400 italic uppercase">Sin dirección registrada</span>
                                                    )}
                                                </td>

                                                <td className="py-2.5 px-4 space-y-0.5">
                                                    {prov.telefono && (
                                                        <div className="flex items-center gap-1.5 text-slate-800 font-mono text-xs font-bold">
                                                            <Phone className="w-3 h-3 text-slate-400 shrink-0" />
                                                            <span>{prov.telefono}</span>
                                                        </div>
                                                    )}
                                                    {prov.email && (
                                                        <div className="flex items-center gap-1.5 text-slate-500 font-medium text-xs">
                                                            <Mail className="w-3 h-3 text-slate-400 shrink-0" />
                                                            <span className="truncate max-w-[170px] lowercase">{prov.email}</span>
                                                        </div>
                                                    )}
                                                    {prov.web && (
                                                        <div className="flex items-center gap-1.5 text-emerald-800 font-medium text-xs">
                                                            <Globe className="w-3 h-3 text-slate-400 shrink-0" />
                                                            <a
                                                                href={prov.web.startsWith('http') ? prov.web : `https://${prov.web}`}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="hover:underline truncate max-w-[170px] lowercase"
                                                            >
                                                                {prov.web}
                                                            </a>
                                                        </div>
                                                    )}
                                                    {!prov.telefono && !prov.email && !prov.web && (
                                                        <span className="text-slate-300 italic text-xs uppercase">Sin contacto</span>
                                                    )}
                                                </td>

                                                <td className="py-2.5 px-4 text-center">
                                                    <span className={`inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-0.5 rounded-full uppercase ${
                                                        estaActivo
                                                            ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                            : 'bg-slate-100 text-slate-400'
                                                    }`}>
                                                        <span className={`w-1.5 h-1.5 rounded-full ${estaActivo ? 'bg-emerald-500' : 'bg-slate-400'}`} />
                                                        {estaActivo ? 'Activo' : 'Inactivo'}
                                                    </span>
                                                </td>

                                                <td className="py-2.5 px-4 text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <button
                                                            type="button"
                                                            onClick={() => abrirModalEditar(prov)}
                                                            className="p-1.5 text-slate-400 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition cursor-pointer"
                                                            title="Editar datos del proveedor"
                                                        >
                                                            <Edit3 className="w-4 h-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => setProvAEliminar(prov)}
                                                            className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                                                            title="Desactivar proveedor"
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
                                            <Truck className="w-8 h-8 mx-auto mb-2 text-slate-300" />
                                            <p className="font-semibold text-xs text-slate-600">No se encontraron proveedores</p>
                                            <p className="text-xs text-slate-400 mt-0.5">
                                                Prueba con otro término de búsqueda o agrega un nuevo proveedor.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <Dialog
                open={modalProveedorOpen}
                onClose={() => setModalProveedorOpen(false)}
                size="xl"
                title={editando ? 'Editar proveedor' : 'Nuevo proveedor'}
                description="Datos fiscales y canales de contacto."
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setModalProveedorOpen(false)} disabled={processing}>Cancelar</Button>
                        <Button type="submit" form="formulario-proveedor" loading={processing}>{editando ? 'Guardar cambios' : 'Registrar proveedor'}</Button>
                    </>
                }
            >
                <form id="formulario-proveedor" onSubmit={handleSubmit} className="space-y-4 p-5" noValidate>
                            {/* RUC con Consulta Automática */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    RUC del Proveedor (11 Dígitos) *
                                </label>
                                <div className="flex rounded-xl bg-slate-50 border border-slate-200 overflow-hidden focus-within:bg-white transition">
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        value={data.ruc}
                                        onChange={(e) => setData('ruc', e.target.value.replace(/\D/g, '').slice(0, 11))}
                                        maxLength={11}
                                        placeholder="EJ. 20100055237 (11 DÍGITOS)"
                                        disabled={editando && (!puedeEditarDocumento || tieneIngresos)}
                                        className="flex-1 text-xs font-mono font-semibold text-slate-800 bg-transparent py-2.5 px-3 disabled:bg-slate-100 disabled:text-slate-400"
                                        required
                                        autoFocus
                                    />

                                    <button
                                        type="button"
                                        onClick={consultarRuc}
                                        disabled={buscandoRuc || data.ruc.length !== 11 || (editando && (!puedeEditarDocumento || tieneIngresos))}
                                        className="volumen-marca px-3.5 text-on-brand flex items-center justify-center transition disabled:opacity-40 disabled:bg-slate-300 cursor-pointer hover:brightness-105"
                                        title="Consultar razón social en SUNAT"
                                    >
                                        {buscandoRuc ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Search className="w-3.5 h-3.5" />}
                                    </button>
                                </div>
                                {errors.ruc && (
                                    <p className="text-xs font-bold text-rose-600 mt-1">{errors.ruc}</p>
                                )}
                                {editando && (!puedeEditarDocumento || tieneIngresos) && (
                                    <p className="text-xs font-bold text-slate-400 mt-1 flex items-center gap-1">
                                        <Lock className="w-3 h-3 text-slate-400" />
                                        {tieneIngresos ? 'RUC bloqueado: posee compras registradas en Kardex.' : 'Edición de RUC restringida a administradores.'}
                                    </p>
                                )}
                            </div>

                            {/* Razón Social Comercial */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Razón Social Comercial *
                                </label>
                                <input
                                    type="text"
                                    placeholder="EJ. ALICORP S.A.A."
                                    value={data.razon_social}
                                    onChange={(e) => setData('razon_social', e.target.value.toUpperCase())}
                                    className="w-full text-xs font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-3 uppercase transition"
                                    required
                                />
                                {errors.razon_social && (
                                    <p className="text-xs font-bold text-rose-600 mt-1">{errors.razon_social}</p>
                                )}
                            </div>

                            {/* Dirección Fiscal / Planta */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Dirección Fiscal / Planta (Opcional)
                                </label>
                                <input
                                    type="text"
                                    placeholder="EJ. AV. ARGENTINA 4793, CALLAO"
                                    value={data.direccion}
                                    onChange={(e) => setData('direccion', e.target.value.toUpperCase())}
                                    className="w-full text-xs font-bold text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-3 uppercase transition"
                                />
                            </div>

                            <CampoTelefono
                                tipo={tipoTelefono}
                                onTipo={(tipo) => { setTipoTelefono(tipo); setData('telefono', ''); }}
                                value={data.telefono}
                                onChange={handleTelefonoChange}
                                error={errors.telefono}
                                ejemploFijo="01-445566"
                                ejemploInternacional="+1 800 555-0199"
                            />

                            {/* Email y Portal Web */}
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 mb-1">
                                        Correo Electrónico
                                    </label>
                                    <div className="relative">
                                        <Mail className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
                                        <input
                                            type="email"
                                            placeholder="pedidos@empresa.com"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value.toLowerCase())}
                                            className="w-full pl-9 pr-3 py-2 text-xs font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl transition lowercase"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 mb-1">
                                        Sitio Web / Portal B2B
                                    </label>
                                    <div className="relative">
                                        <Globe className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
                                        <input
                                            type="text"
                                            placeholder="www.empresa.com"
                                            value={data.web}
                                            onChange={(e) => setData('web', e.target.value.toLowerCase())}
                                            className="w-full pl-9 pr-3 py-2 text-xs font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl transition lowercase"
                                        />
                                    </div>
                                </div>
                            </div>

                            {editando && (
                                <div className="pt-1">
                                    <label className="flex items-center gap-2.5 p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer transition select-none">
                                        <input
                                            type="checkbox"
                                            checked={data.estado}
                                            onChange={(e) => setData('estado', e.target.checked)}
                                            className="w-4 h-4 rounded text-emerald-800 border-slate-300 cursor-pointer"
                                        />
                                        <div>
                                            <p className="text-xs font-semibold text-slate-800">Proveedor Habilitado</p>
                                            <p className="text-xs text-slate-400 font-medium">
                                                Disponible para órdenes de compra e ingresos de mercadería.
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            )}

                </form>
            </Dialog>

            <Dialog open={processing || buscandoRuc} onClose={() => {}} dismissible={false} size="sm">
                <div className="flex flex-col items-center gap-3 px-6 py-8 text-center" role="status">
                    <Loader2 className="h-7 w-7 animate-spin text-brand-ink" aria-hidden="true" />
                    <p className="text-sm font-medium text-slate-900">{buscandoRuc ? 'Consultando el RUC en SUNAT…' : 'Guardando…'}</p>
                </div>
            </Dialog>

            <ConfirmDialog
                open={Boolean(provAEliminar)}
                onClose={() => setProvAEliminar(null)}
                onConfirm={ejecutarEliminacion}
                title="¿Desactivar proveedor?"
                confirmLabel="Desactivar"
            >
                {provAEliminar && <><strong>{provAEliminar.razon_social}</strong> dejará de estar disponible para compras. Los ingresos registrados conservan su historial en kardex.</>}
            </ConfirmDialog>

            <NoticeDialog
                notice={dialogo && { tone: { exito: 'success', error: 'danger', alerta: 'warning' }[dialogo.tipo] ?? 'info', title: dialogo.titulo, message: dialogo.mensaje }}
                onClose={() => setDialogo(null)}
            />
        </AuthenticatedLayout>
    );
}