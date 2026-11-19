import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Badge, Button, confirmar, toast } from '@/Components/ui';
import { ESTADO_COMANDA } from './estados';
import { hora, nuevaClave, primerError } from './formato';

const ESTADO_IMPRESION = {
    PENDIENTE: 'Impresión en cola',
    ENTREGADO_AGENTE: 'Enviando a impresora',
    ENVIADO_SO: 'Enviada a impresora',
    CONFIRMADO: 'Impresión confirmada',
    FALLIDO: 'Error de impresión',
    REQUIERE_REVISION: 'Impresión por revisar',
    CANCELADO: 'Impresión cancelada',
};

export function EstadoImpresion({ impresion }) {
    if (!impresion) return null;

    const error = ['FALLIDO', 'REQUIERE_REVISION'].includes(impresion.estado);

    return (
        <p className={error ? 'text-xs text-rose-700' : 'text-xs text-slate-600'} role={error ? 'status' : undefined}>
            {ESTADO_IMPRESION[impresion.estado]}{impresion.error && ` · ${impresion.error}`}
        </p>
    );
}

export function ReimprimirComanda({ id, impresion, disabled = false }) {
    const [enviando, setEnviando] = useState(false);
    const solicitud = useRef(null);
    const enCola = ['PENDIENTE', 'ENTREGADO_AGENTE'].includes(impresion?.estado);
    const reimprimir = async () => {
        if (enviando || enCola) return;
        if (['REQUIERE_REVISION', 'ENVIADO_SO', 'FALLIDO', 'CONFIRMADO'].includes(impresion?.estado)) {
            const aceptada = await confirmar({
                titulo: '¿Reimprimir la comanda?',
                mensaje: 'Revisa si la impresora ya entregó una copia antes de continuar.',
                tono: 'warning',
                confirmar: 'Reimprimir',
            });
            if (!aceptada) return;
        }
        solicitud.current ??= nuevaClave();
        router.post(`/cocina/comandas/${id}/reimprimir`, { clave_envio: solicitud.current }, {
            preserveScroll: true,
            onStart: () => setEnviando(true),
            onFinish: () => setEnviando(false),
            onSuccess: () => { solicitud.current = null; toast.success('Reimpresión en cola'); },
            onError: (errores) => toast.error('No se pudo reimprimir', primerError(errores)),
        });
    };

    return <Button variant="secondary" icon={Printer} disabled={disabled || enCola} loading={enviando} onClick={reimprimir}>Reimprimir</Button>;
}

export default function ComandasPedido({ comandas, puedeReimprimir, disabled }) {
    if (!comandas.length) return null;

    return (
        <section className="border-t border-slate-200 p-4" aria-label="Comandas">
            <h2 className="mb-2 text-sm font-semibold text-slate-900">Comandas</h2>
            <ul className="space-y-2">
                {comandas.map((comanda) => {
                    const estado = ESTADO_COMANDA[comanda.estado];

                    return (
                        <li key={comanda.id_comanda} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 p-3">
                            <div>
                                <p className="text-sm font-medium">#{comanda.numero} · {comanda.estacion} · {hora(comanda.enviada_en)}</p>
                                <Badge tone={estado.tono}>{estado.etiqueta}</Badge>
                                <EstadoImpresion impresion={comanda.impresion} />
                            </div>
                            {puedeReimprimir && comanda.estado !== 'ANULADA' && <ReimprimirComanda id={comanda.id_comanda} impresion={comanda.impresion} disabled={disabled} />}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
