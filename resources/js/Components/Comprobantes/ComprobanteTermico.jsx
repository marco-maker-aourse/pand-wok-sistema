import { useEffect, useState } from 'react';
import axios from 'axios';
import { AlertTriangle, CheckCircle2, Clock, Loader2, Printer, XCircle } from 'lucide-react';
import { Button, cx } from '@/Components/ui';
import TicketTermico, { imprimirTicketEnNavegador, useTicketTermico } from './TicketTermico';

const ESTADOS = {
    ACEPTADO: { tono: 'border-emerald-200 bg-emerald-50 text-emerald-800', Icon: CheckCircle2, titulo: 'Aceptado por SUNAT', texto: 'Representación impresa definitiva.' },
    RECHAZADO: { tono: 'border-rose-200 bg-rose-50 text-rose-800', Icon: XCircle, titulo: 'Rechazado por SUNAT', texto: 'No es un comprobante válido. Vista de referencia sin QR ni hash.' },
    ERROR: { tono: 'border-amber-200 bg-amber-50 text-amber-900', Icon: AlertTriangle, titulo: 'Envío a SUNAT con error', texto: 'Aún no es válido: se muestra sin QR ni hash hasta que SUNAT lo acepte.' },
    PENDIENTE: { tono: 'border-amber-200 bg-amber-50 text-amber-900', Icon: Clock, titulo: 'Pendiente de SUNAT', texto: 'Vista previa sin validez fiscal: el QR y el hash aparecen cuando SUNAT lo acepte.' },
};

/** Estado SUNAT siempre fuera del papel: nunca se mezcla con la representación fiscal. */
function EstadoSunat({ estado, mensaje }) {
    const info = ESTADOS[estado] ?? ESTADOS.PENDIENTE;
    const { Icon } = info;
    return (
        <div className={cx('flex items-start gap-2 rounded-lg border px-3 py-2 text-xs', info.tono)} role="status">
            <Icon className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
            <div className="min-w-0">
                <p className="font-semibold">{info.titulo}</p>
                <p>{info.texto}</p>
                {mensaje && <p className="mt-0.5 break-words font-medium">Motivo: {mensaje}</p>}
            </div>
        </div>
    );
}

function SelectorAncho({ valor, onChange }) {
    return (
        <div className="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 text-xs font-semibold" role="radiogroup" aria-label="Ancho de papel">
            {[58, 80].map((ancho) => (
                <button
                    key={ancho}
                    type="button"
                    role="radio"
                    aria-checked={valor === ancho}
                    onClick={() => onChange(ancho)}
                    className={cx('rounded-md px-2.5 py-1 transition', valor === ancho ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100')}
                >
                    {ancho} mm
                </button>
            ))}
        </div>
    );
}

/**
 * Vista previa fiel del ticket térmico de una venta. Los datos llegan del backend
 * (PresentarComprobanteService), los mismos que recibe el agente de impresión; aquí no se
 * calcula ningún importe ni dato fiscal. `acciones` se dibuja debajo, fuera del papel.
 */
export default function ComprobanteTermico({ idVenta, estadoSunat, mensajeSunat = '', acciones = null }) {
    const [datos, setDatos] = useState(null);
    const [error, setError] = useState('');
    const [ancho, setAncho] = useState(null);

    useEffect(() => {
        let cancelado = false;
        setError('');
        axios.get(`/ventas/${idVenta}/representacion`)
            .then(({ data }) => {
                if (cancelado) return;
                setDatos(data);
                setAncho((actual) => actual ?? data.ancho_papel_mm ?? 80);
            })
            .catch((falla) => { if (!cancelado) setError(falla.response?.data?.message || 'No se pudo cargar el comprobante.'); });
        return () => { cancelado = true; };
        // El estado SUNAT cambia al reenviar: se vuelve a pedir la representación.
    }, [idVenta, estadoSunat]);

    const estado = datos?.comprobante?.estado_sunat ?? estadoSunat ?? 'PENDIENTE';
    const fiscal = estado === 'ACEPTADO';
    const anchoPapel = ancho ?? 80;
    const { modelo, html, listo } = useTicketTermico(datos?.comprobante ?? null, { anchoPapelMm: anchoPapel, fiscal });

    return (
        <div className="space-y-3">
            <EstadoSunat estado={estado} mensaje={estado === 'RECHAZADO' || estado === 'ERROR' ? mensajeSunat : ''} />

            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs font-semibold text-slate-500">Vista previa del ticket</p>
                <SelectorAncho valor={anchoPapel} onChange={setAncho} />
            </div>

            {/* Mesa gris + papel blanco: el papel se desplaza en horizontal si la pantalla es angosta. */}
            <div className="overflow-x-auto rounded-xl bg-slate-100 px-2 py-4 [scrollbar-width:thin]">
                {error ? (
                    <p className="py-8 text-center text-xs font-semibold text-rose-600">{error}</p>
                ) : !datos ? (
                    <p className="flex items-center justify-center gap-2 py-8 text-xs font-semibold text-slate-500"><Loader2 className="h-4 w-4 animate-spin" /> Cargando comprobante…</p>
                ) : (
                    <TicketTermico
                        html={html}
                        className="mx-auto w-fit bg-white shadow-sm ring-1 ring-slate-200"
                        // Margen no imprimible real del papel: (80 - 72) / 2 = 4 mm; (58 - 48) / 2 = 5 mm.
                        style={{ paddingInline: `${(anchoPapel - (modelo?.anchoMm ?? 72)) / 2}mm` }}
                    />
                )}
            </div>

            <div className="flex flex-wrap gap-2">
                {fiscal && (
                    <Button variant="secondary" icon={Printer} disabled={!listo} onClick={() => imprimirTicketEnNavegador(html, anchoPapel)}>
                        Imprimir desde el navegador
                    </Button>
                )}
                {acciones}
            </div>
        </div>
    );
}
