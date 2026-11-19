import { useEffect, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowRight, Clock, Eye, EyeOff, Loader2, Store } from 'lucide-react';
import FormasFlotantes, { FONDO_ACCESO, RejillaPuntos } from '@/Components/Acceso/FormasFlotantes';
import { NOMBRE_PRODUCTO } from '@/Utils/producto';

const campo = 'block h-11 w-full !rounded-xl !border-slate-200 !bg-white px-3.5 text-sm !text-slate-900 shadow-[0_1px_2px_rgb(15_23_42/0.04)] placeholder:!text-slate-400';
function Aviso({ tono, icono: Icono, children }) {
    const tonos = { error: 'bg-rose-50 text-rose-800 ring-rose-200', espera: 'bg-amber-50 text-amber-900 ring-amber-200', ok: 'bg-emerald-50 text-emerald-800 ring-emerald-200' };
    return (
        <p className={`flex items-start gap-2 rounded-xl px-3 py-2.5 text-sm ring-1 ${tonos[tono]}`} role={tono === 'ok' ? 'status' : 'alert'}>
            {Icono && <Icono className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />}
            <span>{children}</span>
        </p>
    );
}

/**
 * Acceso sobre fondo claro con retícula de puntos; el color de Datos Generales vive en las
 * formas 3D vectoriales, el logo y el botón. Solo ofrece lo que el backend soporta (POST /login).
 */
export default function Login({ status }) {
    const { empresa = {}, organization = {} } = usePage().props;
    const info = empresa?.id_datos_gen ? empresa : organization;

    const nombreEmpresa = info?.nombre_comercial || info?.razon_social || NOMBRE_PRODUCTO;
    const tituloLogin = info?.texto_login_titulo || 'Iniciar sesión';
    const descripcionLogin = info?.texto_login_descripcion || 'Ingresa con la cuenta que te asignó tu administrador.';
    const logoUrl = info?.logo_url || null;

    const [verPassword, setVerPassword] = useState(false);
    const [mayusculas, setMayusculas] = useState(false);
    // Segundos restantes cuando el servidor limita los intentos (HTTP 429, throttle de /login).
    const [esperaSegundos, setEsperaSegundos] = useState(0);

    useEffect(() => {
        if (esperaSegundos <= 0) return undefined;
        const timer = window.setTimeout(() => setEsperaSegundos((s) => s - 1), 1000);
        return () => window.clearTimeout(timer);
    }, [esperaSegundos]);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    useEffect(() => () => reset('password'), []);

    const submit = (e) => {
        e.preventDefault();
        if (processing || esperaSegundos > 0) return;
        post(route('login'), {
            onFinish: () => reset('password'),
            // Sin esto Inertia mostraría la página cruda "429 Too Many Requests" en una ventana.
            onHttpException: (response) => {
                if (response.status !== 429) return undefined;
                const cabeceras = response.headers ?? {};
                const reintento = Number(cabeceras['retry-after'] ?? cabeceras.get?.('retry-after'));
                setEsperaSegundos(Number.isFinite(reintento) && reintento > 0 ? Math.ceil(reintento) : 60);
                return false;
            },
        });
    };

    const detectarMayusculas = (e) => setMayusculas(Boolean(e.getModifierState?.('CapsLock')));
    // Laravel devuelve el fallo de credenciales en `email`; se muestra como aviso general.
    const errorGeneral = errors.email ?? null;
    const bloqueado = processing || esperaSegundos > 0;

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10 text-slate-900" style={FONDO_ACCESO}>
            <Head title="Iniciar sesión" />

            <RejillaPuntos />
            <FormasFlotantes />

            <main className="animate-entrada relative z-10 w-full max-w-[22rem]">
                <div className="rounded-[1.75rem] bg-white/80 p-7 shadow-[0_1px_2px_rgb(15_23_42/0.04),0_24px_60px_-24px_rgb(15_23_42/0.25)] ring-1 ring-slate-900/[0.06] backdrop-blur-xl sm:p-8">
                    <div className="flex flex-col items-center text-center">
                        {logoUrl ? (
                            <img src={logoUrl} alt={`Logo de ${nombreEmpresa}`} className="h-14 w-14 rounded-2xl bg-white object-contain p-2 shadow-[0_8px_20px_-10px_rgb(15_23_42/0.35)] ring-1 ring-slate-900/[0.06]" />
                        ) : (
                            <span className="volumen-marca grid h-14 w-14 place-items-center rounded-2xl text-on-brand">
                                <Store className="h-7 w-7" aria-hidden="true" />
                            </span>
                        )}
                        <p className="mt-3 text-base font-semibold tracking-[-0.01em]">{nombreEmpresa}</p>
                    </div>

                    <h1 className="mt-7 text-2xl font-semibold tracking-[-0.02em]">{tituloLogin}</h1>
                    <p className="mt-1.5 text-sm leading-relaxed text-slate-500">{descripcionLogin}</p>

                    {(status || esperaSegundos > 0 || errorGeneral) && (
                        <div className="mt-5 space-y-3">
                            {status && <Aviso tono="ok">{status}</Aviso>}
                            {esperaSegundos > 0 && (
                                <Aviso tono="espera" icono={Clock}>
                                    Demasiados intentos seguidos. Espera <strong className="font-semibold tabular-nums">{esperaSegundos} s</strong> para volver a intentar.
                                </Aviso>
                            )}
                            {errorGeneral && esperaSegundos === 0 && <Aviso tono="error" icono={AlertCircle}>{errorGeneral}</Aviso>}
                        </div>
                    )}

                    <form onSubmit={submit} className="mt-6 space-y-4" noValidate>
                        <div>
                            <label htmlFor="email" className="mb-1.5 block text-xs font-medium text-slate-700">Correo electrónico</label>
                            <input
                                id="email"
                                type="email"
                                autoComplete="username"
                                inputMode="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                aria-invalid={errors.email ? true : undefined}
                                className={campo}
                                placeholder="nombre@correo.com"
                                autoFocus
                                required
                            />
                        </div>

                        <div>
                            <label htmlFor="password" className="mb-1.5 block text-xs font-medium text-slate-700">Contraseña</label>
                            <div className="relative">
                                <input
                                    id="password"
                                    type={verPassword ? 'text' : 'password'}
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    onKeyUp={detectarMayusculas}
                                    onKeyDown={detectarMayusculas}
                                    aria-invalid={errors.password ? true : undefined}
                                    aria-describedby={[errors.password && 'password-error', mayusculas && 'aviso-mayusculas'].filter(Boolean).join(' ') || undefined}
                                    className={`${campo} pr-11`}
                                    placeholder="Contraseña"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setVerPassword((valor) => !valor)}
                                    aria-label={verPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'}
                                    aria-pressed={verPassword}
                                    className="absolute top-1/2 right-1.5 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition-colors hover:text-slate-700"
                                >
                                    {verPassword ? <EyeOff className="h-4 w-4" aria-hidden="true" /> : <Eye className="h-4 w-4" aria-hidden="true" />}
                                </button>
                            </div>
                            {errors.password && <p id="password-error" className="mt-1.5 rounded-md bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700" role="alert">{errors.password}</p>}
                            {mayusculas && <p id="aviso-mayusculas" className="mt-1.5 text-xs font-medium text-amber-700">Bloq Mayús está activado.</p>}
                        </div>

                        <label className="flex items-center gap-2.5 text-sm text-slate-600">
                            <input type="checkbox" className="h-4 w-4 rounded border-slate-300" checked={data.remember} onChange={(e) => setData('remember', e.target.checked)} />
                            Mantener la sesión iniciada
                        </label>

                        <button
                            type="submit"
                            disabled={bloqueado}
                            aria-busy={processing || undefined}
                            className="volumen-marca group flex h-11 w-full items-center justify-center gap-2 rounded-xl text-sm font-semibold text-on-brand transition-[filter,transform] hover:brightness-110 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing && <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />}
                            {processing ? 'Verificando…' : esperaSegundos > 0 ? `Espera ${esperaSegundos} s` : 'Iniciar sesión'}
                            {!bloqueado && <ArrowRight className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5" aria-hidden="true" />}
                        </button>
                    </form>
                </div>

                <p className="mt-6 text-center text-xs text-slate-500">
                    © {new Date().getFullYear()} {nombreEmpresa}
                    {info?.razon_social && info.razon_social !== nombreEmpresa ? ` · ${info.razon_social}` : ''}
                </p>
            </main>
        </div>
    );
}
