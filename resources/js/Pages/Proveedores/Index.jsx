import { useEffect, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Loader2, Lock, Mail, MapPin, Pencil, Phone, Plus, Search, Trash2, Truck } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Badge, Button, Card, CheckboxField, ConfirmDialog, Dialog, EmptyState, Field, IconButton, Input, PageHeader, PaginacionServidor, SearchInput, Table, TBody, Td, Th, THead, Tr, Textarea, toast } from '@/Components/ui';
import CampoTelefono, { limpiarTelefono } from '@/Components/Contactos/CampoTelefono';
import { PROVEEDOR_VACIO, formularioDesdeProveedor, tipoTelefonoGuardado, validarProveedor } from '@/Components/Proveedores/formularioProveedor';
import useBusquedaServidor from '@/hooks/useBusquedaServidor';

const rucDe = (p) => p.num_documento ?? p.ruc ?? '';
const direccionDe = (p) => p.direccion_fiscal ?? p.direccion ?? '';

function FormularioProveedor({ proveedor, abierto, onCerrar, puedeEditarDocumento, tieneIngresos }) {
    const editando = Boolean(proveedor);
    const [tipoTelefono, setTipoTelefono] = useState('movil_pe');
    const [buscandoRuc, setBuscandoRuc] = useState(false);
    const form = useForm(PROVEEDOR_VACIO);
    const { data, setData, errors, setError, clearErrors, processing } = form;
    const rucBloqueado = editando && (!puedeEditarDocumento || tieneIngresos);
    const rucCambiado = editando && data.ruc !== rucDe(proveedor);

    useEffect(() => {
        if (!abierto) return;
        clearErrors();
        setData(proveedor ? formularioDesdeProveedor(proveedor) : PROVEEDOR_VACIO);
        setTipoTelefono(tipoTelefonoGuardado(proveedor?.telefono));
    }, [abierto, proveedor]); // eslint-disable-line react-hooks/exhaustive-deps

    const consultarRuc = async () => {
        const errorRuc = validarProveedor({ ...data, razon_social: 'xxx' }, tipoTelefono).ruc;
        if (errorRuc) {
            setError('ruc', errorRuc);
            return;
        }
        setBuscandoRuc(true);
        try {
            const endpoint = window.route ? route('documentos.lookup') : '/api/documentos/consulta';
            const { data: respuesta } = await axios.post(endpoint, { context: 'proveedor', tipo: 'ruc', documento: data.ruc.trim() }, { headers: { Accept: 'application/json' } });
            const doc = respuesta?.data || respuesta;
            const razon = doc?.nombre || doc?.razon_social || doc?.nombre_razon_social;
            const direccion = doc?.direccion || doc?.direccion_fiscal;
            if (razon) {
                setData((prev) => ({ ...prev, razon_social: String(razon).toUpperCase(), direccion: direccion ? String(direccion).toUpperCase() : prev.direccion }));
                clearErrors('ruc', 'razon_social');
                toast.success('Datos obtenidos de SUNAT', String(razon));
            } else {
                toast.info('SUNAT no devolvió la razón social', 'Escríbela a mano.');
            }
        } catch (error) {
            const estado = error.response?.status;
            if (estado === 401 || estado === 403) toast.error('No tienes permiso para consultar SUNAT');
            else if (estado === 502 || estado === 503) toast.error('La consulta de SUNAT no responde', 'Escribe la razón social a mano.');
            else toast.error('No se pudo consultar el RUC', error.response?.data?.message || 'Inténtalo de nuevo.');
        } finally {
            setBuscandoRuc(false);
        }
    };

    const guardar = (e) => {
        e.preventDefault();
        const errores = validarProveedor(data, tipoTelefono);
        if (rucCambiado && String(data.motivo_cambio_documento).trim().length < 10) errores.motivo_cambio_documento = 'Explica el cambio de RUC (mínimo 10 caracteres).';
        clearErrors();
        if (Object.keys(errores).length) {
            setError(errores);
            return;
        }
        const opciones = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(editando ? 'Proveedor actualizado' : 'Proveedor registrado', data.razon_social);
                onCerrar();
            },
            onError: () => toast.error('Revisa los datos del proveedor'),
        };
        if (editando) form.put(window.route ? route('proveedores.update', proveedor.id_proveedor) : `/proveedores/${proveedor.id_proveedor}`, opciones);
        else form.post(window.route ? route('proveedores.store') : '/proveedores', opciones);
    };

    return (
        <Dialog open={abierto} onClose={processing ? () => {} : onCerrar} size="lg" title={editando ? 'Editar proveedor' : 'Nuevo proveedor'} description="Datos fiscales y de contacto."
            footer={<><Button variant="secondary" onClick={onCerrar} disabled={processing}>Cancelar</Button><Button type="submit" form="formulario-proveedor" loading={processing}>{editando ? 'Guardar cambios' : 'Registrar proveedor'}</Button></>}>
            <form id="formulario-proveedor" onSubmit={guardar} className="space-y-4 p-5" noValidate>
                <Field label="RUC" required error={errors.ruc} htmlFor="ruc-proveedor"
                    hint={rucBloqueado ? (tieneIngresos ? 'No se puede cambiar: tiene compras registradas.' : 'Solo un administrador puede cambiar el RUC.') : 'Consulta SUNAT para completar la razón social.'}>
                    <div className="flex gap-2">
                        <Input id="ruc-proveedor" inputMode="numeric" maxLength={11} className="font-mono" placeholder="20100055237" autoFocus={!editando}
                            value={data.ruc} disabled={rucBloqueado} aria-invalid={errors.ruc ? true : undefined}
                            onChange={(e) => setData('ruc', e.target.value.replace(/\D/g, '').slice(0, 11))}
                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); consultarRuc(); } }} />
                        <Button variant="secondary" icon={rucBloqueado ? Lock : Search} loading={buscandoRuc} disabled={rucBloqueado || data.ruc.length !== 11} onClick={consultarRuc}>SUNAT</Button>
                    </div>
                </Field>
                {rucCambiado && (
                    <Field label="Motivo del cambio de RUC" required error={errors.motivo_cambio_documento}>
                        <Textarea rows={2} maxLength={255} value={data.motivo_cambio_documento} onChange={(e) => setData('motivo_cambio_documento', e.target.value)} placeholder="Ej.: el RUC se registró con un dígito equivocado" />
                    </Field>
                )}
                <Field label="Razón social" required error={errors.razon_social}>
                    <Input className="uppercase" placeholder="ALICORP S.A.A." value={data.razon_social} onChange={(e) => setData('razon_social', e.target.value.toUpperCase())} />
                </Field>
                <Field label="Dirección fiscal" error={errors.direccion}>
                    <Input className="uppercase" placeholder="AV. ARGENTINA 4793, CALLAO" value={data.direccion} onChange={(e) => setData('direccion', e.target.value.toUpperCase())} />
                </Field>
                <CampoTelefono tipo={tipoTelefono} onTipo={(tipo) => { setTipoTelefono(tipo); setData('telefono', ''); }} value={data.telefono}
                    onChange={(e) => setData('telefono', limpiarTelefono(tipoTelefono, e.target.value))} error={errors.telefono} ejemploFijo="01-445566" ejemploInternacional="+1 800 555-0199" />
                <Field label="Correo" error={errors.email}>
                    <Input type="email" placeholder="pedidos@empresa.com" value={data.email} onChange={(e) => setData('email', e.target.value.toLowerCase())} />
                </Field>
                {editando && (
                    <CheckboxField label="Proveedor activo" description="Disponible para registrar entradas de insumos." checked={data.estado} onChange={(valor) => setData('estado', valor)} />
                )}
            </form>
        </Dialog>
    );
}

/** Proveedores de insumos y mercadería: búsqueda en todo el catálogo, alta con consulta de RUC. */
export default function Index({ auth, proveedores = [], proveedores_con_ingresos: conIngresos = [], filters = {} }) {
    const { flash = {} } = usePage().props;
    const lista = Array.isArray(proveedores) ? proveedores : proveedores?.data ?? [];
    const { busqueda, setBusqueda, buscando } = useBusquedaServidor('/proveedores', filters.buscar, ['proveedores', 'filters']);
    const [formulario, setFormulario] = useState({ abierto: false, proveedor: null });
    const [aDesactivar, setADesactivar] = useState(null);
    const [desactivando, setDesactivando] = useState(false);
    const puedeEditarDocumento = auth?.user?.permissions?.includes('proveedores.editar_documento');

    useEffect(() => { if (flash.error) toast.error(flash.error); }, [flash.error]);

    const desactivar = () => router.delete(window.route ? route('proveedores.destroy', aDesactivar.id_proveedor) : `/proveedores/${aDesactivar.id_proveedor}`, {
        preserveScroll: true,
        onStart: () => setDesactivando(true),
        onFinish: () => setDesactivando(false),
        onSuccess: () => { toast.success('Proveedor desactivado', aDesactivar.razon_social); setADesactivar(null); },
        onError: () => toast.error('No se pudo desactivar el proveedor'),
    });

    const abrir = (proveedor = null) => setFormulario({ abierto: true, proveedor });
    const total = Number(proveedores?.total ?? lista.length);

    return (
        <AuthenticatedLayout header="Proveedores">
            <Head title="Proveedores" />
            <div className="space-y-4">
                <PageHeader title="Proveedores" description={`Empresas que abastecen insumos y bebidas. ${total.toLocaleString('es-PE')} registrados.`} icon={Truck}
                    actions={<Button icon={Plus} onClick={() => abrir()}>Nuevo proveedor</Button>} />

                <Card className="overflow-hidden">
                    <div className="flex items-center gap-3 border-b border-slate-100 p-3 sm:p-4">
                        <SearchInput value={busqueda} onChange={setBusqueda} placeholder="RUC o razón social…" label="Buscar proveedor" className="w-full sm:w-80" />
                        {buscando && <Loader2 className="h-4 w-4 animate-spin text-slate-400" aria-label="Buscando" />}
                    </div>

                    {lista.length === 0 ? (
                        <EmptyState icon={Truck} title={busqueda ? 'Ningún proveedor coincide' : 'Aún no hay proveedores'} description={busqueda ? 'Prueba con otro RUC o nombre.' : 'Registra el primero para poder anotar compras.'}
                            action={!busqueda && <Button icon={Plus} onClick={() => abrir()}>Nuevo proveedor</Button>} />
                    ) : (
                        <>
                            <ul className="divide-y divide-slate-100 md:hidden">
                                {lista.map((p) => (
                                    <li key={p.id_proveedor} className="flex items-start gap-3 px-4 py-3">
                                        <button type="button" onClick={() => abrir(p)} className="min-w-0 flex-1 text-left">
                                            <p className="truncate text-sm font-medium text-slate-900">{p.razon_social}</p>
                                            <p className="font-mono text-xs text-slate-500">{rucDe(p)}</p>
                                            {p.telefono && <p className="text-xs text-slate-500">{p.telefono}</p>}
                                        </button>
                                        {p.estado === false && <Badge>Inactivo</Badge>}
                                    </li>
                                ))}
                            </ul>
                            <Table className="hidden md:block" caption="Proveedores">
                                <THead><Th>RUC</Th><Th>Razón social</Th><Th>Contacto</Th><Th>Estado</Th><Th align="right"><span className="sr-only">Acciones</span></Th></THead>
                                <TBody>
                                    {lista.map((p) => (
                                        <Tr key={p.id_proveedor}>
                                            <Td className="font-mono text-xs font-semibold text-slate-900">{rucDe(p)}</Td>
                                            <Td>
                                                <p className="font-medium text-slate-900">{p.razon_social}</p>
                                                {direccionDe(p) && <p className="flex max-w-sm items-center gap-1 truncate text-xs text-slate-500"><MapPin className="h-3 w-3 shrink-0" aria-hidden="true" /><span className="truncate">{direccionDe(p)}</span></p>}
                                            </Td>
                                            <Td className="space-y-0.5 text-xs">
                                                {p.telefono && <p className="flex items-center gap-1.5 font-mono text-slate-700"><Phone className="h-3 w-3 text-slate-400" aria-hidden="true" />{p.telefono}</p>}
                                                {p.email && <p className="flex max-w-[14rem] items-center gap-1.5 truncate text-slate-500"><Mail className="h-3 w-3 shrink-0 text-slate-400" aria-hidden="true" /><span className="truncate">{p.email}</span></p>}
                                                {!p.telefono && !p.email && <span className="text-slate-400">—</span>}
                                            </Td>
                                            <Td><Badge tone={p.estado === false ? 'neutral' : 'success'} dot>{p.estado === false ? 'Inactivo' : 'Activo'}</Badge></Td>
                                            <Td align="right">
                                                <div className="flex justify-end gap-1">
                                                    <IconButton icon={Pencil} label={`Editar ${p.razon_social}`} onClick={() => abrir(p)} />
                                                    {p.estado !== false && <IconButton icon={Trash2} tone="danger" label={`Desactivar ${p.razon_social}`} onClick={() => setADesactivar(p)} />}
                                                </div>
                                            </Td>
                                        </Tr>
                                    ))}
                                </TBody>
                            </Table>
                        </>
                    )}
                    <PaginacionServidor paginador={proveedores} entidad="proveedores" />
                </Card>
            </div>

            <FormularioProveedor
                abierto={formulario.abierto}
                proveedor={formulario.proveedor}
                onCerrar={() => setFormulario({ abierto: false, proveedor: null })}
                puedeEditarDocumento={puedeEditarDocumento}
                tieneIngresos={formulario.proveedor ? conIngresos.includes(formulario.proveedor.id_proveedor) : false}
            />

            <ConfirmDialog open={Boolean(aDesactivar)} onClose={() => setADesactivar(null)} onConfirm={desactivar} loading={desactivando} title="¿Desactivar proveedor?" confirmLabel="Desactivar">
                {aDesactivar && <><strong>{aDesactivar.razon_social}</strong> dejará de aparecer al registrar entradas. Sus compras anteriores se conservan.</>}
            </ConfirmDialog>
        </AuthenticatedLayout>
    );
}
