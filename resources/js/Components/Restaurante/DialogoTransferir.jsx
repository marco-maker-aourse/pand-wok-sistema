import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { ArrowRightLeft, Combine, Split } from 'lucide-react';
import { Button, Dialog, cx } from '@/Components/ui';
import { primerError, soles, tituloCuenta } from './formato';

const OPERACIONES = [
    { clave: 'mesa', etiqueta: 'Cambiar de mesa', icono: ArrowRightLeft, ayuda: 'Lleva toda la cuenta a una mesa libre.' },
    { clave: 'unir', etiqueta: 'Unir cuentas', icono: Combine, ayuda: 'Pasa toda esta cuenta a otra cuenta abierta. Esta cuenta se cierra.' },
    { clave: 'mover', etiqueta: 'Separar ítems', icono: Split, ayuda: 'Pasa los ítems elegidos a otra cuenta abierta.' },
];

/** Cambiar de mesa, unir con otra cuenta o pasar ítems a otra cuenta. */
export default function DialogoTransferir({ abierto, onCerrar, pedido, destinos, items }) {
    const [operacion, setOperacion] = useState('mesa');
    const [destino, setDestino] = useState(null);
    const [seleccion, setSeleccion] = useState([]);
    const [error, setError] = useState(null);
    const [enviando, setEnviando] = useState(false);

    const disponibles = OPERACIONES.filter((op) => op.clave !== 'mesa' || pedido.tipo === 'MESA');

    useEffect(() => {
        if (abierto) {
            setOperacion(pedido.tipo === 'MESA' ? 'mesa' : 'unir');
            setDestino(null);
            setSeleccion([]);
            setError(null);
        }
    }, [abierto, pedido.tipo]);

    const opciones = operacion === 'mesa'
        ? destinos.mesasLibres.map((m) => ({ id: m.id_mesa, titulo: `Mesa ${m.codigo}`, detalle: `${m.area ?? ''} · ${m.capacidad} pers.${m.estado === 'RESERVADA' ? ' · reservada' : ''}` }))
        : destinos.otrasCuentas.map((c) => ({ id: c.id_pedido, titulo: tituloCuenta(c), detalle: `#${c.numero} · ${soles(c.total)}` }));

    const listo = destino && (operacion !== 'mover' || seleccion.length > 0);

    const enviar = () => {
        router.post(`/pedidos/${pedido.id_pedido}/transferir`, {
            operacion,
            id_mesa: operacion === 'mesa' ? destino : null,
            id_pedido_destino: operacion === 'mesa' ? null : destino,
            detalles: operacion === 'mover' ? seleccion : null,
        }, {
            preserveScroll: true,
            onStart: () => setEnviando(true),
            onFinish: () => setEnviando(false),
            onSuccess: onCerrar,
            onError: (errores) => setError(primerError(errores)),
        });
    };

    return (
        <Dialog
            open={abierto}
            onClose={enviando ? () => {} : onCerrar}
            title="Transferir cuenta"
            size="xl"
            footer={(
                <>
                    <Button variant="secondary" onClick={onCerrar} disabled={enviando}>Cancelar</Button>
                    <Button onClick={enviar} disabled={!listo} loading={enviando}>Confirmar</Button>
                </>
            )}
        >
            <div className="space-y-5 p-5">
                <div className="grid gap-2 sm:grid-cols-3" role="radiogroup" aria-label="Operación">
                    {disponibles.map((op) => (
                        <button
                            key={op.clave}
                            type="button"
                            role="radio"
                            aria-checked={operacion === op.clave}
                            onClick={() => { setOperacion(op.clave); setDestino(null); }}
                            className={cx('flex items-start gap-2.5 rounded-xl p-3 text-left ring-1', operacion === op.clave ? 'bg-brand-soft ring-2 ring-brand' : 'ring-slate-200 hover:bg-slate-50')}
                        >
                            <op.icono className="mt-0.5 h-4 w-4 shrink-0 text-brand-ink" aria-hidden="true" />
                            <span>
                                <span className="block text-sm font-medium text-slate-900">{op.etiqueta}</span>
                                <span className="block text-xs text-slate-500">{op.ayuda}</span>
                            </span>
                        </button>
                    ))}
                </div>

                {operacion === 'mover' && (
                    <fieldset>
                        <legend className="mb-2 text-sm font-medium text-slate-800">Ítems a mover</legend>
                        <div className="max-h-48 space-y-1 overflow-y-auto rounded-xl ring-1 ring-slate-200">
                            {items.map((item) => (
                                <label key={item.id_detalle_pedido} className="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-slate-50">
                                    <input
                                        type="checkbox"
                                        checked={seleccion.includes(item.id_detalle_pedido)}
                                        onChange={(e) => setSeleccion((s) => (e.target.checked ? [...s, item.id_detalle_pedido] : s.filter((id) => id !== item.id_detalle_pedido)))}
                                    />
                                    <span className="flex-1 text-sm">{item.cantidad} × {item.nombre_producto}</span>
                                    <span className="font-mono text-sm tabular-nums">{soles(item.subtotal)}</span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                )}

                <fieldset>
                    <legend className="mb-2 text-sm font-medium text-slate-800">{operacion === 'mesa' ? 'Mesa de destino' : 'Cuenta de destino'}</legend>
                    {opciones.length === 0 ? (
                        <p className="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                            {operacion === 'mesa' ? 'No hay mesas libres.' : 'No hay otras cuentas abiertas.'}
                        </p>
                    ) : (
                        <div className="grid max-h-56 grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3">
                            {opciones.map((opcion) => (
                                <button
                                    key={opcion.id}
                                    type="button"
                                    aria-pressed={destino === opcion.id}
                                    onClick={() => setDestino(opcion.id)}
                                    className={cx('rounded-xl p-3 text-left ring-1', destino === opcion.id ? 'bg-brand-soft ring-2 ring-brand' : 'ring-slate-200 hover:bg-slate-50')}
                                >
                                    <span className="block text-sm font-medium text-slate-900">{opcion.titulo}</span>
                                    <span className="block text-xs text-slate-500">{opcion.detalle}</span>
                                </button>
                            ))}
                        </div>
                    )}
                </fieldset>

                {error && <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700" role="alert">{error}</p>}
            </div>
        </Dialog>
    );
}
