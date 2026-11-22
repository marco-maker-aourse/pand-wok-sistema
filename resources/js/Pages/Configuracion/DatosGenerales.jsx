import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';
import { Building2, Eye, ImageIcon, MapPin, Save, Search, Upload } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, Card, CardBody, CardHeader, Field, Input, NoticeDialog, PageHeader, Textarea } from '@/Components/ui';
import { COLOR_MARCA_POR_DEFECTO, aplicarColorMarca } from '@/Utils/theme';
import SelectorColorMarca from './Partials/SelectorColorMarca';
import VistaPreviaLogin from './Partials/VistaPreviaLogin';

const soloDigitos = (valor, max) => valor.replace(/\D/g, '').slice(0, max);

export default function DatosGenerales({ datos = {}, sucursalActual = null, logoUrl = null }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        codigo_establecimiento: sucursalActual?.codigo_establecimiento ?? '',
        ruc_empresa: datos.ruc_empresa ?? '',
        razon_social: datos.razon_social ?? '',
        nombre_comercial: datos.nombre_comercial ?? '',
        direccion_fiscal: datos.direccion_fiscal ?? '',
        igv_porcentaje: datos.igv_porcentaje ?? '18.00',
        uit_valor: datos.uit_valor ?? '5350.00',
        serie_boleta: datos.serie_boleta ?? 'B001',
        serie_factura: datos.serie_factura ?? 'F001',
        serie_guia: datos.serie_guia ?? 'T001',
        pie_ticket: datos.pie_ticket ?? 'Gracias por su compra.',
        color_primario: datos.color_primario ?? COLOR_MARCA_POR_DEFECTO,
        color_secundario: datos.color_secundario ?? '#0f172a',
        texto_login_titulo: datos.texto_login_titulo ?? 'Sistema de Ventas e Inventario',
        texto_login_descripcion: datos.texto_login_descripcion ?? 'Acceso autorizado para facturación, caja y control de existencias.',
        logo: null,
        _method: 'put',
    });

    const [logoLocal, setLogoLocal] = useState(null);
    const [consultando, setConsultando] = useState(false);
    const [vistaPrevia, setVistaPrevia] = useState(false);
    const [aviso, setAviso] = useState(null);

    useEffect(() => {
        if (!data.logo) {
            setLogoLocal(null);
            return undefined;
        }
        const objectUrl = URL.createObjectURL(data.logo);
        setLogoLocal(objectUrl);
        return () => URL.revokeObjectURL(objectUrl);
    }, [data.logo]);

    const logoMostrado = logoLocal || logoUrl;

    // Vista previa inmediata. Si se sale sin guardar, la siguiente navegación
    // restablece el color guardado porque app.jsx lo vuelve a leer del servidor.
    const cambiarColor = (hex) => {
        setData('color_primario', hex);
        aplicarColorMarca(hex);
    };

    const consultarRuc = async () => {
        const documento = data.ruc_empresa.replace(/\D/g, '');
        if (![8, 11].includes(documento.length)) {
            setAviso({ tone: 'warning', title: 'Documento no válido', message: 'Ingresa un DNI de 8 dígitos o un RUC de 11 dígitos.' });
            return;
        }

        setConsultando(true);
        try {
            const { data: respuesta } = await axios.post('/api/documentos/consulta', {
                context: 'empresa',
                tipo: documento.length === 11 ? 'ruc' : 'dni',
                documento,
            });
            const resultado = respuesta.data;
            const razon = resultado.nombre || resultado.razon_social || '';
            const direccion = resultado.direccion || resultado.direccion_fiscal || '';

            setData((actual) => ({
                ...actual,
                ruc_empresa: documento,
                razon_social: razon || actual.razon_social,
                nombre_comercial: actual.nombre_comercial || razon,
                direccion_fiscal: direccion || actual.direccion_fiscal,
            }));
            setAviso({ tone: 'success', title: 'Datos recuperados', message: `Se completaron los datos de "${razon || 'la empresa'}". Guarda la configuración para conservarlos.` });
        } catch (error) {
            setAviso({
                tone: 'danger',
                title: 'No se pudo consultar el documento',
                message: error.response?.data?.message || 'No hubo respuesta del padrón. Puedes ingresar los datos manualmente.',
            });
        } finally {
            setConsultando(false);
        }
    };

    const guardar = (event) => {
        event.preventDefault();
        post('/datos-generales', {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                reset('logo');
                aplicarColorMarca(data.color_primario);
                setAviso({ tone: 'success', title: 'Configuración guardada', message: 'Los datos fiscales, el color de marca y los textos de acceso se aplicaron para todos los usuarios.' });
            },
            onError: () => {
                setAviso({ tone: 'danger', title: 'Revisa el formulario', message: 'Hay campos obligatorios vacíos o con un formato no válido.' });
            },
        });
    };

    const botonGuardar = (
        <Button type="submit" form="form-datos-generales" icon={Save} loading={processing}>
            Guardar configuración
        </Button>
    );

    return (
        <AuthenticatedLayout header="Datos generales">
            <Head title="Datos generales" />

            <div className="mx-auto max-w-6xl space-y-5">
                <PageHeader
                    breadcrumbs={[{ label: 'Configuración' }, { label: 'Datos generales' }]}
                    title="Datos generales"
                    description="Datos fiscales, series de comprobantes e identidad visual de la empresa."
                    meta={
                        <span className="inline-flex items-center gap-1.5">
                            <MapPin className="h-3.5 w-3.5" aria-hidden="true" />
                            {sucursalActual?.nombre ?? 'Sucursal principal'}
                        </span>
                    }
                    actions={botonGuardar}
                />

                <div role="status" className="flex items-start gap-2 rounded-xl border border-brand-border bg-brand-soft px-4 py-3 text-sm text-brand-ink">
                    <Building2 className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                    <p>
                        Los datos de la empresa (RUC, razón social, logo y colores) son los mismos en todas las sucursales.
                        El código de establecimiento y las series son propios de <strong>{sucursalActual?.nombre ?? 'esta sucursal'}</strong>.
                    </p>
                </div>

                <form id="form-datos-generales" onSubmit={guardar} className="grid grid-cols-1 items-start gap-5 lg:grid-cols-3">
                    <div className="space-y-5 lg:col-span-2">
                        <Card>
                            <CardHeader title="Identidad fiscal y comercial" description="Datos registrados ante SUNAT." />
                            <CardBody className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field htmlFor="ruc-empresa" label="RUC de la empresa" error={errors.ruc_empresa} required hint="Consulta el padrón para completar razón social y dirección.">
                                    <div className="flex gap-2">
                                        <Input
                                            id="ruc-empresa"
                                            aria-invalid={errors.ruc_empresa ? true : undefined}
                                            aria-describedby={errors.ruc_empresa ? 'ruc-empresa-error' : 'ruc-empresa-hint'}
                                            className="font-mono"
                                            maxLength={11}
                                            inputMode="numeric"
                                            value={data.ruc_empresa}
                                            onChange={(e) => setData('ruc_empresa', soloDigitos(e.target.value, 11))}
                                            placeholder="20XXXXXXXXX"
                                            required
                                        />
                                        <Button variant="secondary" icon={Search} loading={consultando} onClick={consultarRuc} disabled={![8, 11].includes(data.ruc_empresa.length)}>
                                            Consultar
                                        </Button>
                                    </div>
                                </Field>

                                <Field label="Código de establecimiento SUNAT" error={errors.codigo_establecimiento} hint="Código de 4 dígitos asignado a esta sede. Es obligatorio para emitir.">
                                    <Input
                                        className="font-mono"
                                        value={data.codigo_establecimiento}
                                        onChange={(e) => setData('codigo_establecimiento', soloDigitos(e.target.value, 4))}
                                        inputMode="numeric"
                                        maxLength={4}
                                        placeholder="0000"
                                    />
                                </Field>

                                <Field label="Razón social" error={errors.razon_social} required className="sm:col-span-2">
                                    <Input value={data.razon_social} onChange={(e) => setData('razon_social', e.target.value.toUpperCase())} placeholder="NOMBRE DE LA EMPRESA S.A.C." />
                                </Field>

                                <Field label="Nombre comercial" error={errors.nombre_comercial} hint="Se muestra en el menú lateral y en el acceso." className="sm:col-span-2">
                                    <Input value={data.nombre_comercial} onChange={(e) => setData('nombre_comercial', e.target.value)} placeholder="Ej. Comercial Los Andes" />
                                </Field>

                                <Field label="Dirección fiscal" error={errors.direccion_fiscal} hint="Domicilio fiscal de la empresa. La dirección de cada sucursal se registra en Configuración › Sucursales." className="sm:col-span-2">
                                    <Input value={data.direccion_fiscal} onChange={(e) => setData('direccion_fiscal', e.target.value)} placeholder="Av. Ferrocarril 123 - Huancayo" />
                                </Field>

                                <div className="sm:col-span-2">
                                    <p className="mb-1.5 text-xs font-medium text-slate-700">Logo</p>
                                    <div className="flex flex-wrap items-center gap-3">
                                        <span className="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-lg border border-slate-200 bg-white text-slate-400">
                                            {logoMostrado ? <img src={logoMostrado} alt="Logo actual" className="h-full w-full object-contain p-1" /> : <ImageIcon className="h-5 w-5" aria-hidden="true" />}
                                        </span>
                                        <label className="inline-flex h-9 cursor-pointer items-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-within:outline-2 focus-within:outline-brand">
                                            <Upload className="h-4 w-4" aria-hidden="true" />
                                            {data.logo ? data.logo.name : 'Subir logo'}
                                            <input type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(e) => setData('logo', e.target.files?.[0] ?? null)} />
                                        </label>
                                        <p className="text-xs text-slate-500">PNG, JPG o WebP. Se usa en el acceso, reportes y comprobantes.</p>
                                    </div>
                                    {errors.logo && <p className="mt-1.5 text-xs font-medium text-rose-600" role="alert">{errors.logo}</p>}
                                </div>
                            </CardBody>
                        </Card>

                        <Card>
                            <CardHeader
                                title="Identidad visual"
                                description="El color de marca se aplica a toda la aplicación para todos los usuarios."
                                actions={<Button variant="secondary" size="sm" icon={Eye} onClick={() => setVistaPrevia(true)}>Vista previa del acceso</Button>}
                            />
                            <CardBody className="space-y-5">
                                <SelectorColorMarca value={data.color_primario} onChange={cambiarColor} error={errors.color_primario} />
                                <div className="grid grid-cols-1 gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2">
                                    <Field label="Título de la pantalla de acceso" error={errors.texto_login_titulo}>
                                        <Input value={data.texto_login_titulo} maxLength={150} onChange={(e) => setData('texto_login_titulo', e.target.value)} placeholder="Ej. Sistema de Ventas e Inventario" />
                                    </Field>
                                    <Field label="Mensaje de acceso o soporte" error={errors.texto_login_descripcion}>
                                        <Input value={data.texto_login_descripcion} maxLength={255} onChange={(e) => setData('texto_login_descripcion', e.target.value)} placeholder="Ej. Acceso autorizado para facturación y caja." />
                                    </Field>
                                </div>
                            </CardBody>
                        </Card>
                    </div>

                    <aside className="space-y-5">
                        <Card>
                            <CardHeader
                                title="Series de comprobantes"
                                description={`Series que emite ${sucursalActual?.nombre ?? 'esta sucursal'}. Cada sucursal usa las suyas (p. ej. B001, B002).`}
                            />
                            <CardBody className="space-y-3">
                                {[
                                    ['serie_boleta', 'Serie de boleta'],
                                    ['serie_factura', 'Serie de factura'],
                                    ['serie_guia', 'Serie de guía de remisión'],
                                ].map(([campo, etiqueta]) => (
                                    <Field key={campo} label={etiqueta} error={errors[campo]}>
                                        <Input className="font-mono uppercase" maxLength={4} value={data[campo] ?? ''} onChange={(e) => setData(campo, e.target.value.toUpperCase())} />
                                    </Field>
                                ))}
                            </CardBody>
                        </Card>

                        <Card>
                            <CardHeader title="Parámetros tributarios" />
                            <CardBody className="grid grid-cols-2 gap-3">
                                <Field label="IGV (%)" error={errors.igv_porcentaje}>
                                    <Input type="number" step="0.01" className="tabular-nums" value={data.igv_porcentaje} onChange={(e) => setData('igv_porcentaje', e.target.value)} />
                                </Field>
                                <Field label="UIT vigente (S/)" error={errors.uit_valor}>
                                    <Input type="number" step="0.01" className="tabular-nums" value={data.uit_valor} onChange={(e) => setData('uit_valor', e.target.value)} />
                                </Field>
                            </CardBody>
                        </Card>

                        <Card>
                            <CardHeader title="Pie del ticket" description="Texto impreso al final del ticket térmico." />
                            <CardBody>
                                <Field label="Texto del pie" error={errors.pie_ticket}>
                                    <Textarea rows={3} value={data.pie_ticket} onChange={(e) => setData('pie_ticket', e.target.value)} />
                                </Field>
                            </CardBody>
                        </Card>

                        <Button type="submit" icon={Save} loading={processing} block size="lg">
                            Guardar configuración
                        </Button>
                    </aside>
                </form>
            </div>

            <VistaPreviaLogin
                open={vistaPrevia}
                onClose={() => setVistaPrevia(false)}
                nombre={data.nombre_comercial || data.razon_social}
                titulo={data.texto_login_titulo}
                descripcion={data.texto_login_descripcion}
                logoUrl={logoMostrado}
            />
            <NoticeDialog notice={aviso} onClose={() => setAviso(null)} />
        </AuthenticatedLayout>
    );
}
