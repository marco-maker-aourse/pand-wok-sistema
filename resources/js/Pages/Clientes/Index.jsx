import { useEffect, useMemo, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { Building2, Loader2, Lock, Mail, MapPin, Pencil, Phone, Search, Trash2, UserPlus, UserRound, Users } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge, Button, Card, CheckboxField, ConfirmDialog, Dialog, EmptyState, Field, IconButton, Input, PageHeader, PaginacionServidor, SearchInput, Select, Table, TBody, Td, Th, THead, Tr, Textarea, toast } from '@/Components/ui';
import CampoTelefono, { limpiarTelefono } from '@/Components/Contactos/CampoTelefono';
import { CLIENTE_VACIO, formularioDesdeCliente, tipoPersonaDe, validarCliente } from '@/Components/Clientes/formularioCliente';
import { tipoTelefonoGuardado } from '@/Components/Proveedores/formularioProveedor';
import useBusquedaServidor from '@/hooks/useBusquedaServidor';

function TipoPersona({ cliente }) {
    return tipoPersonaDe(cliente) === 'JURIDICA'
        ? <Badge icon={Building2}>Empresa</Badge>
        : <Badge icon={UserRound}>Persona</Badge>;
}

function FormularioCliente({ cliente, abierto, onCerrar, tiposDocumento, puedeEditarDocumento, tieneVentas }) {
    const editando = Boolean(cliente);
    const [tipoTelefono, setTipoTelefono] = useState('movil_pe');
    const [consultando, setConsultando] = useState(false);
    const form = useForm(CLIENTE_VACIO);
    const { data, setData, errors, setError, clearErrors, processing } = form;
    const tipoDocumento = tiposDocumento.find((d) => String(d.codigo_sunat) === String(data.tipo_documento));
    const documentoBloqueado = editando && (!puedeEditarDocumento || tieneVentas);
    const documentoCambiado = editando && data.num_documento !== String(cliente.num_documento ?? '');
    const consultable = ['1', '6'].includes(String(data.tipo_documento));

    useEffect(() => {
        if (!abierto) return;
        clearErrors();
        if (cliente) {
            setData(formularioDesdeCliente(cliente));
        } else {
            const dni = tiposDocumento.find((d) => String(d.codigo_sunat) === '1') ?? tiposDocumento[0];
            setData({ ...CLIENTE_VACIO, tipo_documento: dni ? String(dni.codigo_sunat) : '' });
        }
        setTipoTelefono(tipoTelefonoGuardado(cliente?.telefono));
    }, [abierto, cliente]); // eslint-disable-line react-hooks/exhaustive-deps

    // El tipo de persona se deduce del documento (RUC 20 = empresa), como lo valida el servidor.
    const cambiarDocumento = (valor) => {
        const numero = valor.replace(/\D/g, '').slice(0, Number(tipoDocumento?.longitud_exacta) || 15);
        setData((prev) => ({ ...prev, num_documento: numero, tipo_persona: tipoPersonaDe({ tipo_documento: prev.tipo_documento, num_documento: numero }) }));
    };

    const consultar = async () => {
        const error = validarCliente({ ...data, nombre_razon_social: 'xxx' }, tipoDocumento, tipoTelefono).num_documento;
        if (error) {
            setError('num_documento', error);
            return;
        }
        setConsultando(true);
        try {
            const endpoint = window.route ? route('documentos.lookup') : '/api/documentos/consulta';
            const { data: respuesta } = await axios.post(endpoint, { context: 'cliente', tipo: String(data.tipo_documento) === '6' ? 'ruc' : 'dni', documento: data.num_documento });
            const doc = respuesta?.data || respuesta;
            const nombre = doc?.nombre || doc?.razon_social || doc?.nombre_razon_social || doc?.nombres;
            const direccion = doc?.direccion || doc?.direccion_fiscal;
            if (nombre) {
                setData((prev) => ({ ...prev, nombre_razon_social: String(nombre).toUpperCase(), direccion_fiscal: direccion ? String(direccion).toUpperCase() : prev.direccion_fiscal }));
                clearErrors('num_documento', 'nombre_razon_social');
                toast.success(String(data.tipo_documento) === '6' ? 'Datos obtenidos de SUNAT' : 'Datos obtenidos de RENIEC', String(nombre));
            } else {
                toast.info('No se encontraron datos', 'Escribe el nombre a mano.');
            }
        } catch (err) {
            toast.error('No se pudo consultar el documento', err.response?.data?.message || 'Escribe los datos a mano.');
        } finally {
            setConsultando(false);
        }
    };

    const guardar = (e) => {
        e.preventDefault();
        const errores = validarCliente(data, tipoDocumento, tipoTelefono);
        if (documentoCambiado && String(data.motivo_cambio_documento).trim().length < 10) errores.motivo_cambio_documento = 'Explica el cambio de documento (mínimo 10 caracteres).';
        clearErrors();
        if (Object.keys(errores).length) {
            setError(errores);
            return;
        }
        const opciones = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(editando ? 'Cliente actualizado' : 'Cliente registrado', data.nombre_razon_social);
                onCerrar();
            },
            onError: () => toast.error('Revisa los datos del cliente'),
        };
        if (editando) form.put(window.route ? route('clientes.update', cliente.id_cliente) : `/clientes/${cliente.id_cliente}`, opciones);
        else form.post(window.route ? route('clientes.store') : '/clientes', opciones);
    };

    return (
        <Dialog open={abierto} onClose={processing ? () => {} : onCerrar} size="lg" title={editando ? 'Editar cliente' : 'Nuevo cliente'} description="Datos para emitir boletas y facturas."
            footer={<><Button variant="secondary" onClick={onCerrar} disabled={processing}>Cancelar</Button><Button type="submit" form="formulario-cliente" loading={processing}>{editando ? 'Guardar cambios' : 'Registrar cliente'}</Button></>}>
            <form id="formulario-cliente" onSubmit={guardar} className="space-y-4 p-5" noValidate>
                <div className="grid gap-3 sm:grid-cols-[11rem_minmax(0,1fr)]">
                    <Field label="Documento">
                        <Select value={data.tipo_documento} disabled={documentoBloqueado}
                            onChange={(e) => setData((prev) => ({ ...prev, tipo_documento: e.target.value, num_documento: '', tipo_persona: 'NATURAL' }))}>
                            {tiposDocumento.map((d) => <option key={d.id_tipo_documento} value={d.codigo_sunat}>{d.nombre}</option>)}
                        </Select>
                    </Field>
                    <Field label="Número" required error={errors.num_documento} htmlFor="num-documento"
                        hint={documentoBloqueado ? (tieneVentas ? 'No se puede cambiar: tiene comprobantes emitidos.' : 'Solo un administrador puede cambiar el documento.') : data.tipo_persona === 'JURIDICA' ? 'RUC de empresa.' : undefined}>
                        <div className="flex gap-2">
                            <Input id="num-documento" inputMode="numeric" className="font-mono" autoFocus={!editando} value={data.num_documento} disabled={documentoBloqueado}
                                placeholder={String(data.tipo_documento) === '6' ? '20XXXXXXXXX' : '8 dígitos'} aria-invalid={errors.num_documento ? true : undefined}
                                onChange={(e) => cambiarDocumento(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter' && consultable) { e.preventDefault(); consultar(); } }} />
                            {consultable && (
                                <Button variant="secondary" icon={documentoBloqueado ? Lock : Search} loading={consultando} disabled={documentoBloqueado || !data.num_documento} onClick={consultar}>
                                    {String(data.tipo_documento) === '6' ? 'SUNAT' : 'RENIEC'}
                                </Button>
                            )}
                        </div>
                    </Field>
                </div>
                {documentoCambiado && (
                    <Field label="Motivo del cambio de documento" required error={errors.motivo_cambio_documento}>
                        <Textarea rows={2} maxLength={255} value={data.motivo_cambio_documento} onChange={(e) => setData('motivo_cambio_documento', e.target.value)} />
                    </Field>
                )}
                {errors.tipo_persona && <p className="text-xs font-medium text-rose-600" role="alert">{errors.tipo_persona}</p>}
                <Field label={data.tipo_persona === 'JURIDICA' ? 'Razón social' : 'Nombres y apellidos'} required error={errors.nombre_razon_social}>
                    <Input className="uppercase" value={data.nombre_razon_social} onChange={(e) => setData('nombre_razon_social', e.target.value.toUpperCase())} />
                </Field>
                <Field label="Dirección fiscal" error={errors.direccion_fiscal}>
                    <Input className="uppercase" value={data.direccion_fiscal} onChange={(e) => setData('direccion_fiscal', e.target.value.toUpperCase())} />
                </Field>
                <CampoTelefono tipo={tipoTelefono} onTipo={(tipo) => { setTipoTelefono(tipo); setData('telefono', ''); }} value={data.telefono}
                    onChange={(e) => setData('telefono', limpiarTelefono(tipoTelefono, e.target.value))} error={errors.telefono} />
                <Field label="Correo" error={errors.email}>
                    <Input type="email" placeholder="contacto@empresa.pe" value={data.email} onChange={(e) => setData('email', e.target.value.toLowerCase())} />
                </Field>
                {editando && <CheckboxField label="Cliente activo" description="Aparece al buscar clientes en el cobro." checked={data.estado} onChange={(valor) => setData('estado', valor)} />}
            </form>
        </Dialog>
    );
}

/** Clientes para boletas y facturas: búsqueda en toda la cartera y alta con consulta SUNAT/RENIEC. */
export default function Index({ auth, clientes = [], tiposDocumento = [], clientes_con_ventas: conVentas = [], filters = {} }) {
    const lista = Array.isArray(clientes) ? clientes : clientes?.data ?? [];
    const tipos = Array.isArray(tiposDocumento) ? tiposDocumento : [];
    const { busqueda, setBusqueda, buscando } = useBusquedaServidor('/clientes', filters.buscar, ['clientes', 'filters']);
    const [persona, setPersona] = useState('TODOS');
    const [formulario, setFormulario] = useState({ abierto: false, cliente: null });
    const [aDesactivar, setADesactivar] = useState(null);
    const [desactivando, setDesactivando] = useState(false);
    const puedeEditarDocumento = auth?.user?.permissions?.includes('clientes.editar_documento');

    const visibles = useMemo(() => (persona === 'TODOS' ? lista : lista.filter((c) => tipoPersonaDe(c) === persona)), [lista, persona]);

    const desactivar = () => router.delete(window.route ? route('clientes.destroy', aDesactivar.id_cliente) : `/clientes/${aDesactivar.id_cliente}`, {
        preserveScroll: true,
        onStart: () => setDesactivando(true),
        onFinish: () => setDesactivando(false),
        onSuccess: () => { toast.success('Cliente desactivado', aDesactivar.nombre_razon_social); setADesactivar(null); },
        onError: () => toast.error('No se pudo desactivar el cliente'),
    });

    const abrir = (cliente = null) => setFormulario({ abierto: true, cliente });
    const total = Number(clientes?.total ?? lista.length);

    return (
        <AuthenticatedLayout header="Clientes">
            <Head title="Clientes" />
            <div className="space-y-4">
                <PageHeader title="Clientes" description={`Personas y empresas para boletas y facturas. ${total.toLocaleString('es-PE')} registrados.`} icon={Users}
                    actions={<Button icon={UserPlus} onClick={() => abrir()}>Nuevo cliente</Button>} />

                <Card className="overflow-hidden">
                    <div className="flex flex-col gap-3 border-b border-slate-100 p-3 sm:flex-row sm:items-center sm:p-4">
                        <div className="flex flex-1 items-center gap-2">
                            <SearchInput value={busqueda} onChange={setBusqueda} placeholder="DNI, RUC o nombre…" label="Buscar cliente" className="w-full sm:w-80" />
                            {buscando && <Loader2 className="h-4 w-4 animate-spin text-slate-400" aria-label="Buscando" />}
                        </div>
                        <Select aria-label="Filtrar por tipo" value={persona} onChange={(e) => setPersona(e.target.value)} className="sm:w-48">
                            <option value="TODOS">Personas y empresas</option>
                            <option value="NATURAL">Solo personas</option>
                            <option value="JURIDICA">Solo empresas</option>
                        </Select>
                    </div>

                    {visibles.length === 0 ? (
                        <EmptyState icon={Users} title={busqueda || persona !== 'TODOS' ? 'Ningún cliente coincide' : 'Aún no hay clientes'} description={busqueda ? 'Prueba con otro documento o nombre.' : 'Los clientes también se registran desde el cobro.'}
                            action={!busqueda && persona === 'TODOS' && <Button icon={UserPlus} onClick={() => abrir()}>Nuevo cliente</Button>} />
                    ) : (
                        <>
                            <ul className="divide-y divide-slate-100 md:hidden">
                                {visibles.map((c) => (
                                    <li key={c.id_cliente}>
                                        <button type="button" onClick={() => abrir(c)} className="flex w-full items-start gap-3 px-4 py-3 text-left active:bg-slate-50">
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium text-slate-900">{c.nombre_razon_social}</p>
                                                <p className="text-xs text-slate-500">{c.tipo_documento?.nombre ?? 'Doc.'} <span className="font-mono">{c.num_documento}</span></p>
                                            </div>
                                            {c.estado === false ? <Badge>Inactivo</Badge> : <TipoPersona cliente={c} />}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                            <Table className="hidden md:block" caption="Clientes">
                                <THead><Th>Documento</Th><Th>Cliente</Th><Th>Tipo</Th><Th>Contacto</Th><Th>Estado</Th><Th align="right"><span className="sr-only">Acciones</span></Th></THead>
                                <TBody>
                                    {visibles.map((c) => (
                                        <Tr key={c.id_cliente}>
                                            <Td><p className="text-xs text-slate-500">{c.tipo_documento?.nombre ?? 'Doc.'}</p><p className="font-mono text-xs font-semibold text-slate-900">{c.num_documento}</p></Td>
                                            <Td>
                                                <p className="font-medium text-slate-900">{c.nombre_razon_social}</p>
                                                {c.direccion_fiscal && <p className="flex max-w-sm items-center gap-1 truncate text-xs text-slate-500"><MapPin className="h-3 w-3 shrink-0" aria-hidden="true" /><span className="truncate">{c.direccion_fiscal}</span></p>}
                                            </Td>
                                            <Td><TipoPersona cliente={c} /></Td>
                                            <Td className="space-y-0.5 text-xs">
                                                {c.telefono && <p className="flex items-center gap-1.5 font-mono text-slate-700"><Phone className="h-3 w-3 text-slate-400" aria-hidden="true" />{c.telefono}</p>}
                                                {c.email && <p className="flex max-w-[14rem] items-center gap-1.5 truncate text-slate-500"><Mail className="h-3 w-3 shrink-0 text-slate-400" aria-hidden="true" /><span className="truncate">{c.email}</span></p>}
                                                {!c.telefono && !c.email && <span className="text-slate-400">—</span>}
                                            </Td>
                                            <Td><Badge tone={c.estado === false ? 'neutral' : 'success'} dot>{c.estado === false ? 'Inactivo' : 'Activo'}</Badge></Td>
                                            <Td align="right">
                                                <div className="flex justify-end gap-1">
                                                    <IconButton icon={Pencil} label={`Editar ${c.nombre_razon_social}`} onClick={() => abrir(c)} />
                                                    {c.estado !== false && <IconButton icon={Trash2} tone="danger" label={`Desactivar ${c.nombre_razon_social}`} onClick={() => setADesactivar(c)} />}
                                                </div>
                                            </Td>
                                        </Tr>
                                    ))}
                                </TBody>
                            </Table>
                        </>
                    )}
                    <PaginacionServidor paginador={clientes} entidad="clientes" />
                </Card>
            </div>

            <FormularioCliente
                abierto={formulario.abierto}
                cliente={formulario.cliente}
                onCerrar={() => setFormulario({ abierto: false, cliente: null })}
                tiposDocumento={tipos}
                puedeEditarDocumento={puedeEditarDocumento}
                tieneVentas={formulario.cliente ? conVentas.includes(formulario.cliente.id_cliente) : false}
            />

            <ConfirmDialog open={Boolean(aDesactivar)} onClose={() => setADesactivar(null)} onConfirm={desactivar} loading={desactivando} title="¿Desactivar cliente?" confirmLabel="Desactivar">
                {aDesactivar && <><strong>{aDesactivar.nombre_razon_social}</strong> dejará de aparecer al buscar clientes. Sus comprobantes emitidos no cambian.</>}
            </ConfirmDialog>
        </AuthenticatedLayout>
    );
}
