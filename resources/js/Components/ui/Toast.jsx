import { useEffect, useSyncExternalStore } from 'react';
import { AlertTriangle, CheckCircle2, Info, X, XCircle } from 'lucide-react';
import { cx } from './cx';

/*
 * Avisos no bloqueantes. `toast.success(...)` puede llamarse desde cualquier
 * módulo; <Toaster /> vive en el layout y los anuncia a lectores de pantalla.
 */
let avisos = [];
let siguienteId = 1;
const suscriptores = new Set();

function emitir() {
    suscriptores.forEach((fn) => fn());
}

function quitar(id) {
    avisos = avisos.filter((aviso) => aviso.id !== id);
    emitir();
}

function agregar(tone, title, message) {
    const id = siguienteId++;
    avisos = [...avisos.slice(-3), { id, tone, title, message }];
    emitir();
    return id;
}

export const toast = {
    success: (title, message) => agregar('success', title, message),
    error: (title, message) => agregar('danger', title, message),
    warning: (title, message) => agregar('warning', title, message),
    info: (title, message) => agregar('info', title, message),
    dismiss: quitar,
};

const TONOS = {
    success: { icon: CheckCircle2, color: 'text-emerald-600' },
    danger: { icon: XCircle, color: 'text-rose-600' },
    warning: { icon: AlertTriangle, color: 'text-amber-600' },
    info: { icon: Info, color: 'text-sky-600' },
};

function Aviso({ aviso }) {
    const { icon: Icon, color } = TONOS[aviso.tone] ?? TONOS.info;

    useEffect(() => {
        const espera = aviso.tone === 'danger' ? 8000 : 4500;
        const timer = setTimeout(() => quitar(aviso.id), espera);
        return () => clearTimeout(timer);
    }, [aviso.id, aviso.tone]);

    return (
        <div role={aviso.tone === 'danger' ? 'alert' : 'status'} className="pointer-events-auto flex w-full gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-lg">
            <Icon className={cx('mt-0.5 h-4 w-4 shrink-0', color)} aria-hidden="true" />
            <div className="min-w-0 flex-1 text-sm">
                <p className="font-medium text-slate-900">{aviso.title}</p>
                {aviso.message && <p className="mt-0.5 text-slate-600">{aviso.message}</p>}
            </div>
            <button type="button" onClick={() => quitar(aviso.id)} aria-label="Cerrar aviso" className="grid h-6 w-6 shrink-0 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                <X className="h-3.5 w-3.5" aria-hidden="true" />
            </button>
        </div>
    );
}

const suscribir = (fn) => {
    suscriptores.add(fn);
    return () => suscriptores.delete(fn);
};
const leer = () => avisos;

export function Toaster() {
    const lista = useSyncExternalStore(suscribir, leer, leer);

    return (
        <div aria-live="polite" className="pointer-events-none fixed top-[max(1rem,env(safe-area-inset-top))] right-4 lg:top-auto lg:bottom-4 z-[70] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2">
            {lista.map((aviso) => <Aviso key={aviso.id} aviso={aviso} />)}
        </div>
    );
}
