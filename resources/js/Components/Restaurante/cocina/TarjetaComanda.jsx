import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AlarmClock, Clock3, Flame, MessageSquareText } from 'lucide-react';
import { Button, cx, toast } from '@/Components/ui';
import { EstadoImpresion, ReimprimirComanda } from '../ComandasPedido';
import { cantidad, hora, primerError, tiempoDesde, tituloCuenta } from '../formato';

/** Transiciones que ofrece cada estado; las valida el servidor. */
export const ACCIONES_COMANDA = {
    PENDIENTE: [['EN_PREPARACION', 'Iniciar'], ['LISTA', 'Marcar lista']],
    EN_PREPARACION: [['LISTA', 'Marcar lista']],
    LISTA: [['ENTREGADA', 'Entregar'], ['EN_PREPARACION', 'Volver a preparación']],
    ENTREGADA: [],
};

/*
 * Referencias visuales de espera para la cocina (no son reglas del negocio): a partir de
 * estos minutos la tarjeta se marca como "demorando" o "demorada".
 */
const MINUTOS_AVISO = 15;
const MINUTOS_DEMORA = 25;

function urgencia(comanda, ahora) {
    if (comanda.estado === 'ENTREGADA' || !comanda.enviada_en) return null;
    const minutos = (ahora - Date.parse(comanda.enviada_en)) / 60000;
    if (minutos >= MINUTOS_DEMORA) return { etiqueta: 'Demorada', icono: Flame, clases: 'bg-rose-600 text-white', borde: 'ring-rose-300' };
    if (minutos >= MINUTOS_AVISO) return { etiqueta: 'Demorando', icono: AlarmClock, clases: 'bg-amber-100 text-amber-900', borde: 'ring-amber-300' };
    return null;
}

/** Comanda en el tablero de cocina: pensada para leerse de lejos y operarse con el dedo. */
export default function TarjetaComanda({ comanda, puedeGestionar, ahora }) {
    const [enviando, setEnviando] = useState(false);
    const aviso = urgencia(comanda, ahora);
    const fin = comanda.entregada_en ? Date.parse(comanda.entregada_en) : ahora;

    const cambiar = (estado) => router.put(`/cocina/comandas/${comanda.id_comanda}/estado`, { estado }, {
        preserveScroll: true,
        onStart: () => setEnviando(true),
        onFinish: () => setEnviando(false),
        onError: (errores) => toast.error('No se cambió el estado', primerError(errores)),
    });

    return (
        <article className={cx('overflow-hidden rounded-xl bg-white shadow-[var(--shadow-tarjeta)] ring-1', aviso?.borde ?? 'ring-slate-200')}>
            <header className="flex items-start justify-between gap-3 px-3.5 pt-3">
                <div className="min-w-0">
                    <h3 className="truncate text-lg leading-tight font-semibold text-slate-900">{tituloCuenta(comanda.pedido)}</h3>
                    <p className="mt-0.5 truncate text-xs text-slate-500">
                        Comanda #{comanda.numero} · {comanda.estacion}{comanda.mozo && ` · ${comanda.mozo}`}
                    </p>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1">
                    <span className="inline-flex items-center gap-1 font-mono text-sm font-semibold text-slate-700 tabular-nums" title={`Enviada a las ${hora(comanda.enviada_en)}`}>
                        <Clock3 className="h-3.5 w-3.5 text-slate-400" aria-hidden="true" />
                        {tiempoDesde(comanda.enviada_en, fin)}
                    </span>
                    {aviso && (
                        <span className={cx('inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.6875rem] font-semibold', aviso.clases)}>
                            <aviso.icono className="h-3 w-3" aria-hidden="true" />{aviso.etiqueta}
                        </span>
                    )}
                </div>
            </header>

            {comanda.pedido.observacion && (
                <p className="mx-3.5 mt-2 flex gap-1.5 rounded-lg bg-amber-50 px-2.5 py-1.5 text-sm text-amber-900">
                    <MessageSquareText className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />{comanda.pedido.observacion}
                </p>
            )}

            <ul className="mt-2 divide-y divide-slate-100 border-y border-slate-100">
                {comanda.items.map((item) => (
                    <li key={item.id_detalle_pedido} className="flex gap-3 px-3.5 py-2">
                        <span className={cx('grid h-7 min-w-7 shrink-0 place-items-center rounded-md px-1 font-mono text-sm font-bold tabular-nums', item.anulado ? 'bg-rose-50 text-rose-700' : 'bg-slate-900 text-white')}>
                            {cantidad(item.cantidad)}
                        </span>
                        <div className="min-w-0 flex-1 text-sm">
                            <p className={item.anulado ? 'text-rose-700 line-through' : 'font-medium text-slate-900'}>{item.nombre}</p>
                            {item.anulado && <span className="text-xs font-semibold text-rose-700">Anulado</span>}
                            {item.cuenta && <p className="text-xs font-medium text-brand-ink">{tituloCuenta(item.cuenta)}</p>}
                            {item.opciones.length > 0 && <p className="text-[0.8125rem] text-slate-700">{item.opciones.join(' · ')}</p>}
                            {item.observacion && <p className="text-[0.8125rem] font-medium text-amber-800">“{item.observacion}”</p>}
                        </div>
                    </li>
                ))}
            </ul>

            <div className="space-y-2 px-3.5 py-3">
                <EstadoImpresion impresion={comanda.impresion} />
                {puedeGestionar && (
                    <div className="flex flex-wrap gap-2">
                        {ACCIONES_COMANDA[comanda.estado]?.map(([estado, texto], indice) => (
                            <Button key={estado} size="lg" variant={indice ? 'secondary' : 'primary'} disabled={enviando} loading={enviando && !indice} onClick={() => cambiar(estado)} className={indice ? '' : 'flex-1'}>
                                {texto}
                            </Button>
                        ))}
                        <ReimprimirComanda id={comanda.id_comanda} impresion={comanda.impresion} disabled={enviando} />
                    </div>
                )}
            </div>
        </article>
    );
}
