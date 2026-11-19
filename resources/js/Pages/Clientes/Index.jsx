import React, { useState, useEffect, useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import axios from 'axios';
import CampoTelefono, { limpiarTelefono } from '@/Components/Contactos/CampoTelefono';
import { Button, ConfirmDialog, Dialog, NoticeDialog, PageHeader, SearchInput, Select } from '@/Components/ui';
import {
    Users,
    UserPlus,
    Search,
    Phone,
    Mail,
    MapPin,
    Edit3,
    Trash2,
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    Info,
    X,
    Loader2,
    Building2,
    UserCheck,
    Globe,
    Check,
    Lock,
    CreditCard
} from 'lucide-react';

export default function Index({ auth, clientes = [], tiposDocumento = [], clientes_con_ventas = [] }) {
    // Normalizar lista de clientes (soporta colección o paginación Laravel)
    const clientRows = useMemo(() => {
        if (Array.isArray(clientes)) return clientes;
        if (clientes?.data) return clientes.data;
        return [];
    }, [clientes]);

    const documentTypes = useMemo(
        () => (Array.isArray(tiposDocumento) ? tiposDocumento : []),
        [tiposDocumento]
    );

    // Estados de navegación y filtros de la tabla principal
    const [busqueda, setBusqueda] = useState('');
    const [filtroTipoPersona, setFiltroTipoPersona] = useState('TODOS'); // 'TODOS' | 'NATURAL' | 'JURIDICA'

    // Estados de Modales Nativos
    const [modalClienteOpen, setModalClienteOpen] = useState(false);
    const [editando, setEditando] = useState(false);
    const [idActual, setIdActual] = useState(null);
    const [buscandoInfo, setBuscandoInfo] = useState(false);
    const [tipoTelefono, setTipoTelefono] = useState('movil_pe'); // 'movil_pe' | 'fijo_pe' | 'internacional'
    const [clienteAEliminar, setClienteAEliminar] = useState(null);
    const [dialogo, setDialogo] = useState(null); // { tipo: 'exito' | 'error' | 'alerta' | 'info', titulo: '', mensaje: '' }

    // Permisos y restricciones
    const puedeEditarDocumento = auth?.user?.permissions?.includes('clientes.editar_documento');
    const tieneVentas = editando ? clientes_con_ventas?.includes(idActual) : false;

    // Formulario de Inertia
    const { data, setData, post, put, processing, reset, errors, clearErrors } = useForm({
        tipo_persona: 'NATURAL',
        tipo_documento: '',
        num_documento: '',
        nombre_razon_social: '',
        direccion_fiscal: '',
        telefono: '',
        email: '',
        motivo_cambio_documento: '',
        estado: true
    });

    const selectedDocumentType = useMemo(() => {
        return documentTypes.find(
            (doc) => String(doc.codigo_sunat) === String(data.tipo_documento)
        );
    }, [documentTypes, data.tipo_documento]);

    // Inicializar tipo de documento por defecto (DNI si existe)
    useEffect(() => {
        if (!documentTypes.length) return;
        if (!data.tipo_documento) {
            const defaultDoc = documentTypes.find((d) => String(d.codigo_sunat) === '1') || documentTypes[0];
            setData((prev) => ({
                ...prev,
                tipo_documento: String(defaultDoc.codigo_sunat),
                tipo_persona: defaultDoc.tipo_persona || 'NATURAL'
            }));
        }
    }, [documentTypes]);

    // Sincronización inteligente Tipo Persona <-> Tipo de Documento y prefijos 10 / 20
    const handleCambioTipoPersona = (nuevoTipo) => {
        if (editando && (!puedeEditarDocumento || tieneVentas)) return;

        const docRUC = documentTypes.find((d) => String(d.codigo_sunat) === '6');
        const docDNI = documentTypes.find((d) => String(d.codigo_sunat) === '1');

        if (nuevoTipo === 'JURIDICA') {
            setData((prev) => ({
                ...prev,
                tipo_persona: 'JURIDICA',
                tipo_documento: docRUC ? String(docRUC.codigo_sunat) : prev.tipo_documento,
                num_documento: prev.num_documento.startsWith('20') ? prev.num_documento : '20'
            }));
        } else {
            setData((prev) => ({
                ...prev,
                tipo_persona: 'NATURAL',
                tipo_documento: docDNI ? String(docDNI.codigo_sunat) : prev.tipo_documento,
                num_documento: prev.num_documento.startsWith('20') ? '' : prev.num_documento
            }));
        }
    };

    // Control de escritura en Número de Documento con auto-detección
    const handleNumDocumentoChange = (e) => {
        const val = e.target.value.replace(/\D/g, '');
        const maxLen = Number(selectedDocumentType?.longitud_exacta) || 15;
        const finalVal = val.slice(0, maxLen);

        // Si es RUC (código SUNAT 6), auto-conmutar tipo persona según prefijo 20 o 10
        if (String(data.tipo_documento) === '6') {
            if (finalVal.startsWith('20') && data.tipo_persona !== 'JURIDICA') {
                setData((prev) => ({ ...prev, num_documento: finalVal, tipo_persona: 'JURIDICA' }));
                return;
            } else if ((finalVal.startsWith('10') || finalVal.startsWith('15') || finalVal.startsWith('17')) && data.tipo_persona !== 'NATURAL') {
                setData((prev) => ({ ...prev, num_documento: finalVal, tipo_persona: 'NATURAL' }));
                return;
            }
        }

        setData('num_documento', finalVal);
    };

    // Filtro reactivo en la tabla
    const clientesFiltrados = useMemo(() => {
        return clientRows.filter((cli) => {
            const q = busqueda.toUpperCase().trim();
            const coincideTexto =
                !q ||
                (cli.num_documento || '').includes(q) ||
                (cli.nombre_razon_social || '').toUpperCase().includes(q) ||
                (cli.telefono || '').includes(q) ||
                (cli.email || '').toUpperCase().includes(q);

            const coincideTipo =
                filtroTipoPersona === 'TODOS' ||
                (cli.tipo_persona || 'NATURAL').toUpperCase() === filtroTipoPersona;

            return coincideTexto && coincideTipo;
        });
    }, [clientRows, busqueda, filtroTipoPersona]);

    // Abrir Modal para Registrar
    const abrirModalCrear = () => {
        setEditando(false);
        setIdActual(null);
        clearErrors();
        reset();
        const docDNI = documentTypes.find((d) => String(d.codigo_sunat) === '1') || documentTypes[0];
        setData({
            tipo_persona: 'NATURAL',
            tipo_documento: docDNI ? String(docDNI.codigo_sunat) : '',
            num_documento: '',
            nombre_razon_social: '',
            direccion_fiscal: '',
            telefono: '',
            email: '',
            motivo_cambio_documento: '',
            estado: true
        });
        setTipoTelefono('movil_pe');
        setModalClienteOpen(true);
    };

    // Abrir Modal para Editar
    const abrirModalEditar = (cli) => {
        setEditando(true);
        setIdActual(cli.id_cliente);
        clearErrors();

        const telGuardado = cli.telefono ? String(cli.telefono).trim() : '';
        if (telGuardado.startsWith('+') && !telGuardado.startsWith('+51')) {
            setTipoTelefono('internacional');
        } else if (telGuardado.length <= 8 && !telGuardado.startsWith('9')) {
            setTipoTelefono('fijo_pe');
        } else {
            setTipoTelefono('movil_pe');
        }

        setData({
            tipo_persona: cli.tipo_persona || 'NATURAL',
            tipo_documento: String(cli.tipo_documento?.codigo_sunat || cli.id_tipo_documento || '1'),
            num_documento: cli.num_documento || '',
            nombre_razon_social: (cli.nombre_razon_social || '').toUpperCase(),
            direccion_fiscal: (cli.direccion_fiscal || '').toUpperCase(),
            telefono: telGuardado.replace('+51 ', ''),
            email: cli.email || '',
            motivo_cambio_documento: '',
            estado: Boolean(cli.estado ?? true)
        });
        setModalClienteOpen(true);
    };

    // Consulta automática a SUNAT / RENIEC
    const consultarApi = async () => {
        const codSunat = String(selectedDocumentType?.codigo_sunat);
        if (!['1', '6'].includes(codSunat)) return;

        if (!data.num_documento) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'DOCUMENTO VACÍO',
                mensaje: 'INGRESA EL NÚMERO DE DOCUMENTO PARA REALIZAR LA CONSULTA.'
            });
            return;
        }

        const longRequerida = Number(selectedDocumentType?.longitud_exacta);
        if (longRequerida && data.num_documento.length !== longRequerida) {
            setDialogo({
                tipo: 'alerta',
                titulo: `${selectedDocumentType.nombre} INCOMPLETO`,
                mensaje: `EL DOCUMENTO DEBE CONTENER EXACTAMENTE ${longRequerida} DÍGITOS NUMÉRICOS.`
            });
            return;
        }

        const endpointLookup = window.route ? route('documentos.lookup') : '/api/documentos/consulta';
        const docTipoParam = codSunat === '6' ? 'ruc' : 'dni';

        setBuscandoInfo(true);
        try {
            const response = await axios.post(endpointLookup, {
                context: 'cliente',
                tipo: docTipoParam,
                documento: data.num_documento,
            });

            const docData = response.data?.data || response.data;
            const nombreObtenido = docData?.nombre || docData?.razon_social || docData?.nombre_razon_social || docData?.nombres;
            const direccionObtenida = docData?.direccion || docData?.direccion_fiscal;

            if (nombreObtenido) {
                const nombreUpper = String(nombreObtenido).toUpperCase();
                const direccionUpper = direccionObtenida ? String(direccionObtenida).toUpperCase() : data.direccion_fiscal;

                setData((prev) => ({
                    ...prev,
                    nombre_razon_social: nombreUpper,
                    direccion_fiscal: direccionUpper,
                    tipo_persona: codSunat === '6' && data.num_documento.startsWith('20') ? 'JURIDICA' : 'NATURAL'
                }));

                setDialogo({
                    tipo: 'exito',
                    titulo: codSunat === '6' ? 'RAZÓN SOCIAL OBTENIDA (SUNAT)' : 'IDENTIDAD OBTENIDA (RENIEC)',
                    mensaje: `SE RECUPERÓ LA INFORMACIÓN DE "${nombreUpper}". COMPLETA LOS DATOS Y GUARDA EL REGISTRO.`
                });
            } else {
                setDialogo({
                    tipo: 'info',
                    titulo: 'SIN RESULTADOS EN EL PADRÓN',
                    mensaje: 'NO SE ENCONTRÓ INFORMACIÓN AUTOMÁTICA. PUEDES INGRESAR EL NOMBRE O RAZÓN SOCIAL MANUALMENTE.'
                });
            }
        } catch (error) {
            const msg = error.response?.data?.message || 'NO SE PUDO CONECTAR CON EL SERVICIO DE CONSULTA DE PADRÓN.';
            setDialogo({
                tipo: 'error',
                titulo: 'ERROR EN CONSULTA',
                mensaje: String(msg).toUpperCase()
            });
        } finally {
            setBuscandoInfo(false);
        }
    };

    // Formateador de teléfono
    const handleTelefonoChange = (e) => setData('telefono', limpiarTelefono(tipoTelefono, e.target.value));

    // Validación estricta y envío del formulario
    const handleSubmit = (e) => {
        e.preventDefault();

        const numDoc = data.num_documento.trim();
        const codSunat = String(selectedDocumentType?.codigo_sunat);

        // Validación estricta RUC vs Persona
        if (codSunat === '6') {
            if (numDoc.length !== 11) {
                setDialogo({
                    tipo: 'alerta',
                    titulo: 'RUC INVÁLIDO',
                    mensaje: 'EL RUC DEBE TENER EXACTAMENTE 11 DÍGITOS NUMÉRICOS.'
                });
                return;
            }

            if (data.tipo_persona === 'JURIDICA' && !numDoc.startsWith('20')) {
                setDialogo({
                    tipo: 'alerta',
                    titulo: 'PREFIJO DE RUC JURÍDICO INCORRECTO',
                    mensaje: 'TODO RUC DE PERSONA JURÍDICA (EMPRESAS, S.A.C., S.R.L.) DEBE INICIAR OBLIGATORIAMENTE CON EL NÚMERO "20".'
                });
                return;
            }

            if (data.tipo_persona === 'NATURAL' && !['10', '15', '17'].some((pref) => numDoc.startsWith(pref))) {
                setDialogo({
                    tipo: 'alerta',
                    titulo: 'PREFIJO DE RUC NATURAL INCORRECTO',
                    mensaje: 'EL RUC DE PERSONA NATURAL CON NEGOCIO DEBE COMENZAR CON "10", "15" O "17".'
                });
                return;
            }
        }

        // Validación DNI
        if (codSunat === '1') {
            if (numDoc.length !== 8) {
                setDialogo({
                    tipo: 'alerta',
                    titulo: 'DNI INVÁLIDO',
                    mensaje: 'EL DOCUMENTO NACIONAL DE IDENTIDAD (DNI) DEBE TENER EXACTAMENTE 8 DÍGITOS.'
                });
                return;
            }
            if (data.tipo_persona !== 'NATURAL') {
                setDialogo({
                    tipo: 'alerta',
                    titulo: 'INCONSISTENCIA DE PERSONA',
                    mensaje: 'UN TITULAR CON DNI SOLO PUEDE REGISTRARSE COMO PERSONA NATURAL.'
                });
                return;
            }
        }

        // Validación de Nombre / Razón Social
        const nombreLimpio = data.nombre_razon_social.trim().toUpperCase();
        if (nombreLimpio.length < 3) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'NOMBRE O RAZÓN SOCIAL REQUERIDA',
                mensaje: 'DEBE INGRESAR LA DENOMINACIÓN DEL CLIENTE CON AL MENOS 3 CARACTERES.'
            });
            return;
        }

        // Validación de Teléfono según el tipo seleccionado
        const tel = data.telefono?.trim();
        if (tel && tel.length > 0) {
            if (tipoTelefono === 'movil_pe' && (tel.length !== 9 || !tel.startsWith('9'))) {
                setDialogo({
                    tipo: 'alerta',
                    titulo: 'CELULAR PERUANO INVÁLIDO',
                    mensaje: 'EL NÚMERO MÓVIL EN PERÚ DEBE TENER 9 DÍGITOS Y EMPEZAR CON 9 (EJ. 964112233).'
                });
                return;
            }
            if (tipoTelefono === 'fijo_pe') {
                const soloDigitos = tel.replace(/\D/g, '');
                if (soloDigitos.length < 7 || soloDigitos.length > 9) {
                    setDialogo({
                        tipo: 'alerta',
                        titulo: 'TELÉFONO FIJO INVÁLIDO',
                        mensaje: 'EL NÚMERO FIJO DEBE CONTENER ENTRE 7 Y 9 DÍGITOS (EJ. 064-241122).'
                    });
                    return;
                }
            }
        }

        const endpoint = editando
            ? (window.route ? route('clientes.update', idActual) : `/clientes/${idActual}`)
            : (window.route ? route('clientes.store') : '/clientes');

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setModalClienteOpen(false);
                reset();
                setDialogo({
                    tipo: 'exito',
                    titulo: editando ? 'CLIENTE ACTUALIZADO' : 'CLIENTE REGISTRADO',
                    mensaje: `LOS DATOS DE "${nombreLimpio}" SE REGISTRARON EXITOSAMENTE EN LA CARTERA COMERCIAL.`
                });
            },
            onError: (errs) => {
                const primerError = Object.values(errs)[0] || 'VERIFICA LOS DATOS OBLIGATORIOS DEL FORMULARIO.';
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

    // Ejecutar desactivación
    const ejecutarEliminacion = () => {
        if (!clienteAEliminar) return;
        const cli = clienteAEliminar;

        const endpoint = window.route ? route('clientes.destroy', cli.id_cliente) : `/clientes/${cli.id_cliente}`;

        router.delete(endpoint, {
            preserveScroll: true,
            onSuccess: () => {
                setClienteAEliminar(null);
                setDialogo({
                    tipo: 'exito',
                    titulo: 'CLIENTE DESACTIVADO',
                    mensaje: `"${cli.nombre_razon_social.toUpperCase()}" FUE RETIRADO DE LAS BÚSQUEDAS RÁPIDAS SIN AFECTAR COMPROBANTES EMITIDOS.`
                });
            },
            onError: () => {
                setClienteAEliminar(null);
                setDialogo({
                    tipo: 'error',
                    titulo: 'OPERACIÓN RESTRINGIDA',
                    mensaje: 'NO SE PUEDE DESACTIVAR ESTE CLIENTE EN ESTE MOMENTO.'
                });
            }
        });
    };

    return (
        <AuthenticatedLayout header="Clientes">
            <Head title="Clientes" />

            <div className="space-y-4">
                <PageHeader
                    breadcrumbs={[{ label: 'Gestión' }, { label: 'Clientes' }]}
                    title="Clientes"
                    description="Cartera de clientes con consulta a SUNAT y RENIEC para la emisión electrónica."
                    actions={<Button icon={UserPlus} onClick={abrirModalCrear}>Nuevo cliente</Button>}
                />

                <div className="bg-white rounded-2xl overflow-hidden shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 p-3">
                        <SearchInput
                            value={busqueda}
                            onChange={(valor) => setBusqueda(valor.toUpperCase())}
                            placeholder="Buscar por DNI, RUC o nombre"
                            className="w-full sm:max-w-xs"
                        />
                        <Select
                            aria-label="Filtrar por tipo de persona"
                            value={filtroTipoPersona}
                            onChange={(e) => setFiltroTipoPersona(e.target.value)}
                            className="w-auto"
                        >
                            <option value="TODOS">Todas las personas</option>
                            <option value="NATURAL">Persona natural</option>
                            <option value="JURIDICA">Persona jurídica</option>
                        </Select>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b border-slate-100 bg-white text-xs font-medium text-slate-500">
                                    <th className="py-2.5 px-4 font-medium">Documento</th>
                                    <th className="py-2.5 px-4 font-medium">Cliente / razón social</th>
                                    <th className="py-2.5 px-4 text-center font-medium">Tipo persona</th>
                                    <th className="py-2.5 px-4 font-medium">Contacto</th>
                                    <th className="py-2.5 px-4 text-center font-medium">Estado</th>
                                    <th className="py-2.5 px-4 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 text-sm text-slate-700">
                                {clientesFiltrados.length > 0 ? (
                                    clientesFiltrados.map((cli) => {
                                        const tipoPers = (cli.tipo_persona || 'NATURAL').toUpperCase();
                                        const estaActivo = cli.estado !== false;

                                        return (
                                            <tr key={cli.id_cliente} className="hover:bg-brand-soft/40 transition-colors">
                                                <td className="py-2.5 px-4">
                                                    <span className="inline-block px-2 py-0.5 rounded-md text-xs font-mono font-semibold mb-1 bg-slate-100 text-slate-700 border border-slate-200/60 uppercase">
                                                        {cli.tipo_documento?.nombre || cli.tipo_documento_nombre || 'DOC'}
                                                    </span>
                                                    <p className="font-mono font-semibold text-slate-900 text-xs">
                                                        {cli.num_documento}
                                                    </p>
                                                </td>

                                                <td className="py-2.5 px-4">
                                                    <p className="font-semibold text-slate-900">{cli.nombre_razon_social}</p>
                                                    {cli.direccion_fiscal ? (
                                                        <div className="flex items-center gap-1 text-slate-400 text-xs font-semibold mt-0.5 truncate max-w-sm uppercase">
                                                            <MapPin className="w-3 h-3 shrink-0 text-slate-400" />
                                                            <span className="truncate">{cli.direccion_fiscal}</span>
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-slate-400 italic uppercase">Sin dirección fiscal</span>
                                                    )}
                                                </td>

                                                <td className="py-2.5 px-4 text-center">
                                                    {tipoPers === 'JURIDICA' ? (
                                                        <span className="inline-flex items-center gap-1 text-xs font-semibold text-purple-700 bg-purple-50 border border-purple-200 px-2.5 py-0.5 rounded-full uppercase">
                                                            <Building2 className="w-3 h-3" />
                                                            Jurídica
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 text-xs font-semibold text-brand-ink bg-brand-soft border border-brand-border px-2.5 py-0.5 rounded-full uppercase">
                                                            <UserCheck className="w-3 h-3" />
                                                            Natural
                                                        </span>
                                                    )}
                                                </td>

                                                <td className="py-2.5 px-4 space-y-0.5">
                                                    {cli.telefono && (
                                                        <div className="flex items-center gap-1.5 text-slate-800 font-mono text-xs font-bold">
                                                            <Phone className="w-3 h-3 text-slate-400 shrink-0" />
                                                            <span>{cli.telefono}</span>
                                                        </div>
                                                    )}
                                                    {cli.email && (
                                                        <div className="flex items-center gap-1.5 text-slate-500 font-medium text-xs">
                                                            <Mail className="w-3 h-3 text-slate-400 shrink-0" />
                                                            <span className="truncate max-w-[170px] lowercase">{cli.email}</span>
                                                        </div>
                                                    )}
                                                    {!cli.telefono && !cli.email && (
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
                                                            onClick={() => abrirModalEditar(cli)}
                                                            className="p-1.5 text-slate-400 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition cursor-pointer"
                                                            title="Editar datos del cliente"
                                                        >
                                                            <Edit3 className="w-4 h-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => setClienteAEliminar(cli)}
                                                            className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                                                            title="Desactivar cliente"
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
                                        <td colSpan="6" className="py-12 text-center text-slate-400">
                                            <Users className="w-8 h-8 mx-auto mb-2 text-slate-300" />
                                            <p className="font-semibold text-xs text-slate-600">No se encontraron clientes</p>
                                            <p className="text-xs text-slate-400 mt-0.5">
                                                Prueba con otro término de búsqueda o registra uno nuevo.
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
                open={modalClienteOpen}
                onClose={() => setModalClienteOpen(false)}
                size="xl"
                title={editando ? 'Editar cliente' : 'Nuevo cliente'}
                description="Identidad tributaria y datos comerciales."
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setModalClienteOpen(false)} disabled={processing}>Cancelar</Button>
                        <Button type="submit" form="formulario-cliente" loading={processing}>{editando ? 'Guardar cambios' : 'Registrar cliente'}</Button>
                    </>
                }
            >
                <form id="formulario-cliente" onSubmit={handleSubmit} className="space-y-4 p-5" noValidate>
                            {/* Selector de Tipo Persona y Documento */}
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 mb-1">
                                        Tipo de Persona *
                                    </label>
                                    <select
                                        value={data.tipo_persona}
                                        onChange={(e) => handleCambioTipoPersona(e.target.value)}
                                        disabled={editando && (!puedeEditarDocumento || tieneVentas)}
                                        className="w-full text-xs font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-3 transition uppercase cursor-pointer disabled:bg-slate-100 disabled:text-slate-400"
                                    >
                                        <option value="NATURAL">Persona natural (DNI / RUC 10)</option>
                                        <option value="JURIDICA">Persona jurídica (RUC 20)</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 mb-1">
                                        Tipo de Documento *
                                    </label>
                                    <select
                                        value={data.tipo_documento}
                                        onChange={(e) => {
                                            const doc = documentTypes.find((d) => String(d.codigo_sunat) === e.target.value);
                                            setData((prev) => ({
                                                ...prev,
                                                tipo_documento: e.target.value,
                                                num_documento: '',
                                                tipo_persona: doc?.tipo_persona || prev.tipo_persona
                                            }));
                                        }}
                                        disabled={editando && (!puedeEditarDocumento || tieneVentas)}
                                        className="w-full text-xs font-semibold text-slate-800 bg-slate-50 border border-slate-200 rounded-xl py-2 px-3 outline-none focus:bg-white focus:border-slate-800 transition uppercase cursor-pointer disabled:bg-slate-100 disabled:text-slate-400"
                                    >
                                        {documentTypes.map((doc) => (
                                            <option key={doc.id_tipo_documento} value={doc.codigo_sunat}>
                                                {doc.nombre} {doc.longitud_exacta ? `(${doc.longitud_exacta} DÍGITOS)` : ''}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {/* Número de documento + botón SUNAT / RENIEC */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Número de Documento *
                                </label>
                                <div className="flex rounded-xl bg-slate-50 border border-slate-200 overflow-hidden focus-within:bg-white transition">
                                    <input
                                        type="text"
                                        inputMode="numeric"
                                        value={data.num_documento}
                                        onChange={handleNumDocumentoChange}
                                        maxLength={Number(selectedDocumentType?.longitud_exacta) || 15}
                                        placeholder={
                                            String(data.tipo_documento) === '6'
                                                ? (data.tipo_persona === 'JURIDICA' ? '20XXXXXXXXX (11 DÍGITOS)' : '10XXXXXXXXX (11 DÍGITOS)')
                                                : '8 DÍGITOS PARA DNI'
                                        }
                                        disabled={editando && (!puedeEditarDocumento || tieneVentas)}
                                        className="flex-1 text-xs font-mono font-semibold text-slate-800 bg-transparent py-2.5 px-3 disabled:bg-slate-100 disabled:text-slate-400"
                                        required
                                        autoFocus
                                    />

                                    <button
                                        type="button"
                                        onClick={consultarApi}
                                        disabled={buscandoInfo || !['1', '6'].includes(String(data.tipo_documento)) || !data.num_documento}
                                        className="volumen-marca px-3.5 text-on-brand flex items-center justify-center transition disabled:opacity-40 disabled:bg-slate-300 cursor-pointer hover:brightness-105"
                                        title="Consultar identidad en padrón oficial"
                                    >
                                        {buscandoInfo ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Search className="w-3.5 h-3.5" />}
                                    </button>
                                </div>
                                {errors.num_documento && (
                                    <p className="text-xs font-bold text-rose-600 mt-1">{errors.num_documento}</p>
                                )}
                            </div>

                            {/* Nombres y Apellidos / Razón Social */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Nombres y Apellidos / Razón Social *
                                </label>
                                <input
                                    type="text"
                                    placeholder="EJ. COMERCIALIZADORA DEL CENTRO S.A.C."
                                    value={data.nombre_razon_social}
                                    onChange={(e) => setData('nombre_razon_social', e.target.value.toUpperCase())}
                                    className="w-full text-xs font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-3 uppercase transition"
                                    required
                                />
                                {errors.nombre_razon_social && (
                                    <p className="text-xs font-bold text-rose-600 mt-1">{errors.nombre_razon_social}</p>
                                )}
                            </div>

                            {/* Dirección Fiscal */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Dirección Fiscal (Opcional)
                                </label>
                                <input
                                    type="text"
                                    placeholder="EJ. AV. FERROCARRIL 890, EL TAMBO"
                                    value={data.direccion_fiscal}
                                    onChange={(e) => setData('direccion_fiscal', e.target.value.toUpperCase())}
                                    className="w-full text-xs font-bold text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-3 uppercase transition"
                                />
                            </div>

                            <CampoTelefono
                                tipo={tipoTelefono}
                                onTipo={(tipo) => { setTipoTelefono(tipo); setData('telefono', ''); }}
                                value={data.telefono}
                                onChange={handleTelefonoChange}
                                error={errors.telefono}
                                ejemploFijo="064-241122"
                                ejemploInternacional="+54 9 11 4321-5678"
                            />

                            {/* Email */}
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Correo Electrónico (Opcional)
                                </label>
                                <div className="relative">
                                    <Mail className="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5 pointer-events-none" />
                                    <input
                                        type="email"
                                        placeholder="contacto@empresa.pe"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value.toLowerCase())}
                                        className="w-full pl-9 pr-3 py-2 text-xs font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl transition lowercase"
                                    />
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
                                            <p className="text-xs font-semibold text-slate-800">Cliente Activo</p>
                                            <p className="text-xs text-slate-400 font-medium">
                                                Habilitado para selección rápida en ventas y facturación.
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            )}

                </form>
            </Dialog>

            <Dialog open={processing || buscandoInfo} onClose={() => {}} dismissible={false} size="sm">
                <div className="flex flex-col items-center gap-3 px-6 py-8 text-center" role="status">
                    <Loader2 className="h-7 w-7 animate-spin text-brand-ink" aria-hidden="true" />
                    <p className="text-sm font-medium text-slate-900">{buscandoInfo ? 'Consultando el padrón de SUNAT o RENIEC…' : 'Guardando…'}</p>
                </div>
            </Dialog>

            <ConfirmDialog
                open={Boolean(clienteAEliminar)}
                onClose={() => setClienteAEliminar(null)}
                onConfirm={ejecutarEliminacion}
                title="¿Desactivar cliente?"
                confirmLabel="Desactivar"
            >
                {clienteAEliminar && <><strong>{clienteAEliminar.nombre_razon_social}</strong> se retirará de la cartera activa. Los comprobantes ya emitidos conservan su validez tributaria.</>}
            </ConfirmDialog>

            <NoticeDialog
                notice={dialogo && { tone: { exito: 'success', error: 'danger', alerta: 'warning' }[dialogo.tipo] ?? 'info', title: dialogo.titulo, message: dialogo.mensaje }}
                onClose={() => setDialogo(null)}
            />
        </AuthenticatedLayout>
    );
}