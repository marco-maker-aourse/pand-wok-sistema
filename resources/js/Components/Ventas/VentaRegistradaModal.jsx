import { useState } from 'react';
import { router } from '@inertiajs/react';
import { CheckCircle2, Clock, Printer, X } from 'lucide-react';
import ComprobanteTermico from '@/Components/Comprobantes/ComprobanteTermico';
import { Badge, Button } from '@/Components/ui';
import useImprimirTicket from '@/hooks/useImprimirTicket';

/**
 * Impresión según la terminal de la caja (el servidor envía `impresion` con el CPE aceptado):
 * automática ya encolada → aviso; manual → botón «Imprimir ticket»; sin SUNAT → nada que imprimir.
 */
function ImpresionTicket({ venta }) {
    const { imprimir, imprimiendo } = useImprimirTicket();
    const [impreso, setImpreso] = useState(false);

    if (venta.estado_sunat !== 'ACEPTADO') {
        return <Badge tone="neutral" icon={Clock}>Se imprimirá cuando SUNAT acepte el comprobante</Badge>;
    }

    const impresion = venta.impresion ?? { puede_imprimir: true, encolada: false };
    if (impresion.encolada) {
        return <Badge tone="success" icon={Printer}>Ticket enviado a la impresora automáticamente</Badge>;
    }
    if (!impresion.puede_imprimir) {
        return <Badge tone="warning">Esta caja no tiene impresora de tickets activa</Badge>;
    }

    return (
        <Button size="lg" block icon={Printer} loading={imprimiendo} onClick={async () => setImpreso((await imprimir(venta.id_venta)) || impreso)}>
            {impreso ? 'Imprimir otra vez' : 'Imprimir ticket'}
        </Button>
    );
}

/** Muestra el ticket operativo sin obligar al cajero a abandonar el POS. */
export default function VentaRegistradaModal({ venta, onClose }) {
    if (!venta) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/40 p-4">
            <div className="relative my-auto mx-auto w-full max-w-md space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-xl sm:p-5">
                <button type="button" onClick={onClose} className="absolute right-3 top-3 z-10 grid h-8 w-8 place-items-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Cerrar y continuar vendiendo" title="Cerrar y continuar vendiendo"><X className="h-4 w-4" /></button>
                <div className="pr-9 text-center"><Badge tone="success" icon={CheckCircle2}>Venta registrada</Badge></div>
                <ComprobanteTermico idVenta={venta.id_venta} estadoSunat={venta.estado_sunat} mensajeSunat={venta.mensaje_sunat} />
                <div className="flex justify-center"><ImpresionTicket venta={venta} /></div>
                <div className="grid grid-cols-2 gap-2">
                    <Button variant="secondary" onClick={onClose}>Cerrar y vender</Button>
                    <Button variant="secondary" onClick={() => { onClose(); router.visit('/ventas'); }}>Ver historial</Button>
                </div>
            </div>
        </div>
    );
}
