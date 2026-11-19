import { Store } from 'lucide-react';
import { Dialog, FormaTubo } from '@/Components/ui';
import { FONDO_ACCESO, RejillaPuntos } from '@/Components/Acceso/FormasFlotantes';

/** Miniatura del acceso real (fondo claro, formas 3D en color de marca y tarjeta blanca) con los datos aún no guardados. */
export default function VistaPreviaLogin({ open, onClose, nombre, titulo, descripcion, logoUrl }) {
    return (
        <Dialog open={open} onClose={onClose} title="Vista previa del acceso" description="Así verán los usuarios la pantalla de inicio de sesión." size="xl">
            <div className="relative flex min-h-[26rem] items-center justify-center overflow-hidden rounded-xl px-6 py-10 text-slate-900" style={FONDO_ACCESO}>
                <RejillaPuntos />
                <FormaTubo tono="marca" d="M150 40 A70 70 0 1 0 190 150" viewBox="0 0 240 240" ancho={30} className="pointer-events-none absolute top-4 left-6 w-24" />
                <FormaTubo tono="marca" d="M30 150 L90 80 L120 130 L190 50" viewBox="0 0 220 200" ancho={34} className="pointer-events-none absolute bottom-6 left-10 w-28" />
                <FormaTubo tono="marca" d="M20 60 Q45 30 70 60 T120 60 T170 60" viewBox="0 0 190 110" ancho={18} className="pointer-events-none absolute top-16 right-8 w-24" />

                <div className="relative w-full max-w-[17rem] rounded-3xl bg-white/80 p-5 shadow-[0_24px_60px_-24px_rgb(15_23_42/0.25)] ring-1 ring-slate-900/[0.06] backdrop-blur-xl">
                    <div className="flex flex-col items-center text-center">
                        {logoUrl ? (
                            <img src={logoUrl} alt="" className="h-11 w-11 rounded-xl bg-white object-contain p-1.5 ring-1 ring-slate-900/[0.06]" />
                        ) : (
                            <span className="volumen-marca grid h-11 w-11 place-items-center rounded-xl text-on-brand"><Store className="h-5 w-5" aria-hidden="true" /></span>
                        )}
                        <p className="mt-2 text-sm font-semibold">{nombre || 'Nombre de la empresa'}</p>
                    </div>
                    <p className="mt-4 text-lg font-semibold">{titulo || 'Iniciar sesión'}</p>
                    <p className="mt-1 text-xs text-slate-500">{descripcion}</p>
                    <div className="pointer-events-none mt-4 space-y-2.5" aria-hidden="true">
                        <div className="h-9 rounded-xl bg-white ring-1 ring-slate-200" />
                        <div className="h-9 rounded-xl bg-white ring-1 ring-slate-200" />
                        <div className="volumen-marca grid h-9 place-items-center rounded-xl text-xs font-semibold text-on-brand">Iniciar sesión</div>
                    </div>
                </div>
            </div>
        </Dialog>
    );
}
