import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { Button, CheckboxField, Dialog, Field, Input, Select, toast } from '@/Components/ui';
import SucursalesAdicionales from './SucursalesAdicionales';

const vacio = (roles, sucursales, tiposDocumento) => ({
    nombres: '', email: '', password: '', password_confirmation: '', pin_cajero: '', pin_cajero_confirmation: '',
    id_rol: roles[0]?.id_rol ?? '', id_sucursal: sucursales[0]?.id_sucursal ?? '', sucursales_adicionales: [],
    id_tipo_documento: tiposDocumento[0]?.id_tipo_documento ?? '', num_documento: '', estado: true,
});

/** Validación previa al envío (el servidor vuelve a validar). Devuelve { campo: mensaje }. */
function validar(data, editando, tipoDocumento) {
    const errores = {};
    if (data.nombres.trim().length < 3) errores.nombres = 'Escribe nombres y apellidos (mínimo 3 caracteres).';
    if (!data.email.trim()) errores.email = 'Escribe el correo de acceso.';
    if (!data.id_rol) errores.id_rol = 'Elige el rol.';
    if (!data.id_sucursal) errores.id_sucursal = 'Elige la sede.';
    const documento = String(data.num_documento || '').trim();
    if (!documento) errores.num_documento = 'Escribe el número de documento.';
    else if (tipoDocumento?.longitud_exacta && documento.length !== Number(tipoDocumento.longitud_exacta)) errores.num_documento = `Debe tener ${tipoDocumento.longitud_exacta} caracteres.`;
    if (!editando || data.password) {
        if (data.password.length < 8) errores.password = 'Mínimo 8 caracteres.';
        else if (data.password !== data.password_confirmation) errores.password_confirmation = 'No coincide con la contraseña.';
    }
    if (data.pin_cajero) {
        if (!/^\d{4,6}$/.test(data.pin_cajero)) errores.pin_cajero = 'De 4 a 6 dígitos.';
        else if (data.pin_cajero !== data.pin_cajero_confirmation) errores.pin_cajero_confirmation = 'No coincide con el PIN.';
    }
    return errores;
}

/** Alta y edición de una cuenta: identidad, rol, sede, accesos a otras sucursales, contraseña y PIN de caja. */
export default function FormularioUsuario({ usuario, abierto, onCerrar, roles, sucursales, tiposDocumento, puedeGestionarPin, puedeAsignarSucursales }) {
    const editando = Boolean(usuario);
    const form = useForm(vacio(roles, sucursales, tiposDocumento));
    const { data, setData, errors, setError, clearErrors, processing } = form;
    const tipoDocumento = tiposDocumento.find((t) => String(t.id_tipo_documento) === String(data.id_tipo_documento));
    const soloNumeros = ['1', '6'].includes(String(tipoDocumento?.codigo_sunat));

    useEffect(() => {
        if (!abierto) return;
        clearErrors();
        setData(usuario ? {
            ...vacio(roles, sucursales, tiposDocumento),
            nombres: (usuario.nombres || '').toUpperCase(),
            email: usuario.email || '',
            id_rol: usuario.id_rol ?? roles[0]?.id_rol ?? '',
            id_sucursal: usuario.id_sucursal ?? '',
            sucursales_adicionales: (usuario.sucursales_adicionales ?? []).map((s) => s.id_sucursal),
            id_tipo_documento: usuario.id_tipo_documento ?? '',
            num_documento: usuario.num_documento ?? '',
            estado: Boolean(usuario.estado ?? true),
        } : vacio(roles, sucursales, tiposDocumento));
    }, [abierto, usuario]); // eslint-disable-line react-hooks/exhaustive-deps

    const guardar = (e) => {
        e.preventDefault();
        const errores = validar(data, editando, tipoDocumento);
        clearErrors();
        if (Object.keys(errores).length) {
            setError(errores);
            return;
        }
        // Solo un administrador envía accesos a sucursales; para el resto el servidor los conserva.
        form.transform(({ sucursales_adicionales: adicionales, ...datos }) => (puedeAsignarSucursales
            ? { ...datos, sucursales_adicionales: adicionales.filter((id) => String(id) !== String(datos.id_sucursal)) }
            : datos));
        const opciones = {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(editando ? 'Usuario actualizado' : 'Usuario creado', data.nombres);
                onCerrar();
            },
            onError: () => toast.error('Revisa los datos del usuario'),
        };
        if (editando) form.put(window.route ? route('usuarios.update', usuario.id_usuario) : `/usuarios/${usuario.id_usuario}`, opciones);
        else form.post(window.route ? route('usuarios.store') : '/usuarios', opciones);
    };

    const errorAdicionales = errors.sucursales_adicionales ?? Object.entries(errors).find(([campo]) => campo.startsWith('sucursales_adicionales.'))?.[1];

    return (
        <Dialog open={abierto} onClose={processing ? () => {} : onCerrar} size="lg" title={editando ? 'Editar usuario' : 'Nuevo usuario'} description="Acceso, rol y sede de trabajo."
            footer={<><Button variant="secondary" onClick={onCerrar} disabled={processing}>Cancelar</Button><Button type="submit" form="formulario-usuario" loading={processing}>{editando ? 'Guardar cambios' : 'Crear usuario'}</Button></>}>
            <form id="formulario-usuario" onSubmit={guardar} className="space-y-4 p-5" noValidate>
                <Field label="Nombres y apellidos" required error={errors.nombres}>
                    <Input className="uppercase" autoFocus={!editando} value={data.nombres} onChange={(e) => setData('nombres', e.target.value.toUpperCase())} />
                </Field>
                <Field label="Correo de acceso" required error={errors.email}>
                    <Input type="email" autoComplete="off" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                </Field>
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="Rol" required error={errors.id_rol}>
                        <Select value={data.id_rol} onChange={(e) => setData('id_rol', e.target.value)}>
                            {roles.map((r) => <option key={r.id_rol} value={r.id_rol}>{r.nombre}</option>)}
                        </Select>
                    </Field>
                    <Field label="Sede" required error={errors.id_sucursal}>
                        <Select value={data.id_sucursal} onChange={(e) => setData('id_sucursal', e.target.value)}>
                            {sucursales.map((s) => <option key={s.id_sucursal} value={s.id_sucursal}>{s.nombre}</option>)}
                        </Select>
                    </Field>
                </div>
                {puedeAsignarSucursales && sucursales.length > 1 && (
                    <SucursalesAdicionales sucursales={sucursales} idSede={data.id_sucursal} seleccionadas={data.sucursales_adicionales}
                        onCambiar={(ids) => setData('sucursales_adicionales', ids)} error={errorAdicionales} />
                )}
                <div className="grid gap-3 sm:grid-cols-[11rem_minmax(0,1fr)]">
                    <Field label="Documento" error={errors.id_tipo_documento}>
                        <Select value={data.id_tipo_documento} onChange={(e) => setData('id_tipo_documento', e.target.value)}>
                            {tiposDocumento.map((d) => <option key={d.id_tipo_documento} value={d.id_tipo_documento}>{d.nombre}</option>)}
                        </Select>
                    </Field>
                    <Field label="Número" required error={errors.num_documento} hint={tipoDocumento?.longitud_exacta ? `${tipoDocumento.longitud_exacta} caracteres.` : undefined}>
                        <Input className="font-mono" inputMode={soloNumeros ? 'numeric' : 'text'} maxLength={tipoDocumento?.longitud_exacta || 15} value={data.num_documento}
                            onChange={(e) => setData('num_documento', soloNumeros ? e.target.value.replace(/\D/g, '') : e.target.value.toUpperCase())} />
                    </Field>
                </div>

                <fieldset className="grid gap-3 rounded-xl bg-slate-50 p-3.5 ring-1 ring-slate-200 sm:grid-cols-2">
                    <legend className="sr-only">Contraseña</legend>
                    <Field label={editando ? 'Nueva contraseña' : 'Contraseña'} required={!editando} error={errors.password} hint={editando ? 'Déjala vacía para no cambiarla.' : 'Mínimo 8 caracteres.'}>
                        <Input type="password" autoComplete="new-password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                    </Field>
                    <Field label="Confirmar contraseña" required={!editando || Boolean(data.password)} error={errors.password_confirmation}>
                        <Input type="password" autoComplete="new-password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                    </Field>
                </fieldset>

                {puedeGestionarPin && (
                    <fieldset className="space-y-3 rounded-xl bg-amber-50 p-3.5 ring-1 ring-amber-200">
                        <legend className="sr-only">PIN de caja</legend>
                        <p className="flex items-start gap-2 text-xs text-amber-900">
                            <KeyRound className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                            <span><b>PIN de caja</b> para abrir, cerrar y autorizar movimientos. {editando ? 'Déjalo vacío para conservar el actual.' : 'Opcional.'} No se puede ver después de guardarlo.</span>
                        </p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="PIN" error={errors.pin_cajero}>
                                <Input type="password" inputMode="numeric" autoComplete="new-password" className="font-mono" placeholder="4 a 6 dígitos" value={data.pin_cajero}
                                    onChange={(e) => setData('pin_cajero', e.target.value.replace(/\D/g, '').slice(0, 6))} />
                            </Field>
                            <Field label="Confirmar PIN" error={errors.pin_cajero_confirmation}>
                                <Input type="password" inputMode="numeric" autoComplete="new-password" className="font-mono" value={data.pin_cajero_confirmation}
                                    onChange={(e) => setData('pin_cajero_confirmation', e.target.value.replace(/\D/g, '').slice(0, 6))} />
                            </Field>
                        </div>
                    </fieldset>
                )}

                {editando && <CheckboxField label="Cuenta activa" description="Puede iniciar sesión en el sistema." checked={data.estado} onChange={(valor) => setData('estado', valor)} />}
            </form>
        </Dialog>
    );
}
