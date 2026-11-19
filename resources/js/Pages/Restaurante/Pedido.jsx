import { useEffect, useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRightLeft, Ban, History, Minus, Plus, Save, Trash2, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, IconButton, cx, toast } from '@/Components/ui';
import CartaPedido from '@/Components/Restaurante/CartaPedido';
import DialogoMotivo from '@/Components/Restaurante/DialogoMotivo';
import DialogoPlato from '@/Components/Restaurante/DialogoPlato';
import DialogoTransferir from '@/Components/Restaurante/DialogoTransferir';
import LineaCuenta from '@/Components/Restaurante/LineaCuenta';
import { ESTADO_PEDIDO, describirEvento } from '@/Components/Restaurante/estados';
import { hora, nuevaClave, primerError, soles, tiempoDesde, tituloCuenta } from '@/Components/Restaurante/formato';

/**
 * Toma de pedido. Los platos elegidos van primero a un borrador local (rápido en tablet,
 * sin una petición por toque) y se guardan juntos; cada línea lleva su clave de
 * idempotencia, así un reintento nunca duplica ítems.
 */
export default function Pedido({ pedido, carta, destinos, puede }) {
    const { flash = {} } = usePage().props;
    const [borrador, setBorrador] = useState([]);
    const [platoAbierto, setPlatoAbierto] = useState(null);
    const [ocupado, setOcupado] = useState(false);
    const [anulacion, setAnulacion] = useState(null);
    const [errorAnulacion, setErrorAnulacion] = useState(null);
    const [transferir, setTransferir] = useState(false);
    const [verHistorial, setVerHistorial] = useState(false);
    const [vista, setVista] = useState('carta');

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
    }, [flash.success]);

    // Evita perder el borrador al salir por accidente.
    useEffect(() => {
        if (borrador.length === 0) return undefined;
        const avisar = (e) => { e.preventDefault(); e.returnValue = ''; };
        window.addEventListener('beforeunload', avisar);
        const quitar = router.on('before', (evento) => {
            const metodo = evento.detail.visit.method;
            if (metodo === 'get' && !window.confirm('Hay platos sin guardar en la cuenta. ¿Salir de todos modos?')) evento.preventDefault();
        });
        return () => { window.removeEventListener('beforeunload', avisar); quitar(); };
    }, [borrador.length]);

    const vigentes = pedido.detalles.filter((d) => d.estado !== 'ANULADO');
    const anulados = pedido.detalles.filter((d) => d.estado === 'ANULADO');
    const totalBorrador = useMemo(() => borrador.reduce((s, l) => s + l.precio_estimado * l.cantidad, 0), [borrador]);
    const editable = puede.editar;
    const estadoPedido = ESTADO_PEDIDO[pedido.estado] ?? ESTADO_PEDIDO.ABIERTO;

    const elegir = (producto) => {
        if (producto.grupos.length > 0 || producto.presentaciones.length > 1) {
            setPlatoAbierto(producto);
            return;
        }
        // Plato simple: se suma directo al borrador (o aumenta la línea igual).
        const presentacion = producto.presentaciones[0];
        agregarAlBorrador({ id_precio: presentacion.id_precio, cantidad: 1, opciones: [], observacion: null, nombre: producto.nombre, detalle: [], precio_estimado: presentacion.precio_venta });
    };

    const agregarAlBorrador = (linea) => {
        setBorrador((actual) => {
            const igual = actual.find((l) => l.id_precio === linea.id_precio && !l.observacion && !linea.observacion && l.opciones.length === 0 && linea.opciones.length === 0);
            if (igual) return actual.map((l) => (l === igual ? { ...l, cantidad: l.cantidad + linea.cantidad } : l));
            return [...actual, { ...linea, clave: nuevaClave() }];
        });
        setPlatoAbierto(null);
    };

    const cambiarBorrador = (clave, delta) => setBorrador((actual) => actual
        .map((l) => (l.clave === clave ? { ...l, cantidad: l.cantidad + delta } : l))
        .filter((l) => l.cantidad > 0));

    const guardar = () => {
        router.post(`/pedidos/${pedido.id_pedido}/items`, {
            items: borrador.map(({ clave, id_precio, cantidad, observacion, opciones }) => ({ clave, id_precio, cantidad, observacion, opciones })),
        }, {
            preserveScroll: true,
            onStart: () => setOcupado(true),
            onFinish: () => setOcupado(false),
            onSuccess: () => { setBorrador([]); setVista('cuenta'); },
            onError: (errores) => toast.error('No se guardó el pedido', primerError(errores)),
        });
    };

    const cambiarCantidad = (item, cantidad) => {
        router.put(`/pedidos/items/${item.id_detalle_pedido}`, { cantidad }, {
            preserveScroll: true,
            onStart: () => setOcupado(true),
            onFinish: () => setOcupado(false),
            onError: (errores) => toast.error('No se cambió la cantidad', primerError(errores)),
        });
    };

    const solicitarAnulacion = (item) => {
        setErrorAnulacion(null);
        if (item.estado === 'PENDIENTE') {
            enviarAnulacion({ item }, null);
            return;
        }
        if (!puede.anular_enviados) {
            toast.warning('Necesitas autorización', `${item.nombre_producto} ya salió a cocina. Pide a un supervisor que lo anule.`);
            return;
        }
        setAnulacion({ item });
    };

    const enviarAnulacion = (objetivo, motivo) => {
        const url = objetivo.item ? `/pedidos/items/${objetivo.item.id_detalle_pedido}/anular` : `/pedidos/${pedido.id_pedido}/anular`;
        router.post(url, { motivo }, {
            preserveScroll: true,
            onStart: () => setOcupado(true),
            onFinish: () => setOcupado(false),
            onSuccess: () => setAnulacion(null),
            onError: (errores) => {
                if (anulacion) setErrorAnulacion(primerError(errores));
                else toast.error('No se pudo quitar', primerError(errores));
            },
        });
    };

    const subtitulo = [
        pedido.mesa?.area,
        pedido.comensales && `${pedido.comensales} comensales`,
        pedido.mozo,
        `abierta hace ${tiempoDesde(pedido.abierto_en)}`,
    ].filter(Boolean).join(' · ');

    return (
        <AppLayout title={tituloCuenta(pedido)}>
            <Head title={`${tituloCuenta(pedido)} · #${pedido.numero}`} />

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <IconButton icon={ArrowLeft} label="Volver al salón" onClick={() => router.visit('/salon')} className="h-10 w-10 ring-1 ring-slate-200" />
                <div className="min-w-0 flex-1">
                    <h1 className="flex flex-wrap items-center gap-2 text-xl font-semibold tracking-tight text-slate-900">
                        {tituloCuenta(pedido)}
                        <span className="font-mono text-sm font-normal text-slate-500">#{pedido.numero}</span>
                        <Badge tone={estadoPedido.tono} dot>{estadoPedido.etiqueta}</Badge>
                    </h1>
                    <p className="text-sm text-slate-500">{subtitulo}</p>
                    {pedido.direccion_entrega && <p className="text-sm text-slate-600">Entrega: {pedido.direccion_entrega}{pedido.telefono_contacto && ` · ${pedido.telefono_contacto}`}</p>}
                    {pedido.observacion && <p className="text-sm text-amber-700">Nota: {pedido.observacion}</p>}
                </div>
                {editable && (
                    <div className="flex flex-wrap gap-2">
                        {puede.transferir && destinos && <Button variant="secondary" icon={ArrowRightLeft} onClick={() => setTransferir(true)}>Transferir</Button>}
                        <Button variant="danger-ghost" icon={Ban} onClick={() => { setErrorAnulacion(null); setAnulacion({ pedido: true }); }}>Anular cuenta</Button>
                    </div>
                )}
            </div>

            {!editable && (
                <p className="mb-4 rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-700">
                    Esta cuenta está {estadoPedido.etiqueta.toLowerCase()}{pedido.motivo_anulacion && `: ${pedido.motivo_anulacion}`}. Solo lectura.
                </p>
            )}

            {/* En pantallas angostas, alternar entre carta y cuenta. */}
            {editable && (
                <div className="mb-3 grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 lg:hidden" role="tablist">
                    {[['carta', 'Carta'], ['cuenta', `Cuenta (${vigentes.length + borrador.length})`]].map(([clave, etiqueta]) => (
                        <button key={clave} type="button" role="tab" aria-selected={vista === clave} onClick={() => setVista(clave)} className={cx('h-10 rounded-lg text-sm font-medium', vista === clave ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600')}>
                            {etiqueta}
                        </button>
                    ))}
                </div>
            )}

            <div className={cx('grid gap-4', editable && 'lg:grid-cols-[minmax(0,1fr)_24rem]')}>
                {editable && carta && (
                    <Card className={cx('flex h-[calc(100dvh-14rem)] min-h-[28rem] flex-col overflow-hidden', vista !== 'carta' && 'hidden lg:flex')}>
                        <CartaPedido carta={carta} onElegir={elegir} />
                    </Card>
                )}

                <Card className={cx('flex flex-col overflow-hidden', editable && 'lg:h-[calc(100dvh-14rem)] lg:min-h-[28rem]', editable && vista !== 'cuenta' && 'hidden lg:flex')}>
                    <div className="flex-1 overflow-y-auto">
                        {borrador.length > 0 && (
                            <section aria-label="Por guardar" className="border-b border-amber-200 bg-amber-50/60">
                                <p className="px-4 pt-3 text-xs font-semibold tracking-wide text-amber-800 uppercase">Por guardar</p>
                                <ul className="divide-y divide-amber-100">
                                    {borrador.map((linea) => (
                                        <li key={linea.clave} className="flex items-center gap-3 px-4 py-2.5">
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium text-slate-900">{linea.nombre}</p>
                                                {linea.detalle.length > 0 && <p className="text-xs text-slate-600">{linea.detalle.join(' · ')}</p>}
                                                {linea.observacion && <p className="text-xs text-amber-700 italic">“{linea.observacion}”</p>}
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <IconButton icon={linea.cantidad === 1 ? X : Minus} label={linea.cantidad === 1 ? 'Quitar' : 'Uno menos'} onClick={() => cambiarBorrador(linea.clave, -1)} className="h-10 w-10 bg-white ring-1 ring-slate-200" />
                                                <span className="w-7 text-center font-mono text-sm font-semibold tabular-nums">{linea.cantidad}</span>
                                                <IconButton icon={Plus} label="Uno más" onClick={() => cambiarBorrador(linea.clave, 1)} className="h-10 w-10 bg-white ring-1 ring-slate-200" />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}

                        {vigentes.length === 0 && borrador.length === 0 ? (
                            <p className="px-6 py-12 text-center text-sm text-slate-500">
                                {editable ? 'La cuenta está vacía. Elige platos de la carta.' : 'Sin ítems.'}
                            </p>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {vigentes.map((item) => (
                                    <LineaCuenta key={item.id_detalle_pedido} item={item} editable={editable} ocupado={ocupado} onCantidad={cambiarCantidad} onQuitar={solicitarAnulacion} />
                                ))}
                            </ul>
                        )}

                        {anulados.length > 0 && (
                            <details className="border-t border-slate-100">
                                <summary className="cursor-pointer px-4 py-2.5 text-xs font-medium text-slate-500">{anulados.length} ítem(s) anulados</summary>
                                <ul className="divide-y divide-slate-100">
                                    {anulados.map((item) => <LineaCuenta key={item.id_detalle_pedido} item={item} editable={false} />)}
                                </ul>
                            </details>
                        )}
                    </div>

                    <div className="space-y-3 border-t border-slate-200 bg-slate-50 p-4">
                        <div className="flex items-baseline justify-between">
                            <span className="text-sm text-slate-600">Total de la cuenta</span>
                            <span className="font-mono text-2xl font-semibold tabular-nums text-slate-900">{soles(pedido.total)}</span>
                        </div>
                        {borrador.length > 0 && (
                            <>
                                <p className="text-right text-xs text-slate-500">+ {soles(totalBorrador)} por guardar (estimado)</p>
                                <div className="grid grid-cols-[auto_1fr] gap-2">
                                    <Button variant="secondary" size="lg" icon={Trash2} onClick={() => setBorrador([])} disabled={ocupado} aria-label="Descartar lo no guardado" />
                                    <Button size="lg" icon={Save} onClick={guardar} loading={ocupado} block>Guardar {borrador.reduce((s, l) => s + l.cantidad, 0)} en la cuenta</Button>
                                </div>
                            </>
                        )}
                        <button type="button" onClick={() => setVerHistorial((v) => !v)} className="flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-800" aria-expanded={verHistorial}>
                            <History className="h-3.5 w-3.5" aria-hidden="true" /> Historial
                        </button>
                        {verHistorial && (
                            <ol className="max-h-48 space-y-1.5 overflow-y-auto text-xs text-slate-600">
                                {pedido.eventos.map((evento) => (
                                    <li key={evento.id_evento} className="flex gap-2">
                                        <span className="shrink-0 font-mono text-slate-400 tabular-nums">{hora(evento.creado_en)}</span>
                                        <span>{describirEvento(evento)}{evento.usuario && <span className="text-slate-400"> · {evento.usuario}</span>}</span>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </div>
                </Card>
            </div>

            <DialogoPlato producto={platoAbierto} grupos={carta?.grupos ?? []} onCerrar={() => setPlatoAbierto(null)} onAgregar={agregarAlBorrador} />

            <DialogoMotivo
                abierto={Boolean(anulacion)}
                titulo={anulacion?.pedido ? `Anular cuenta #${pedido.numero}` : `Anular ${anulacion?.item?.nombre_producto ?? ''}`}
                descripcion={anulacion?.pedido ? 'Se anulan todos los ítems y la mesa queda libre.' : 'Este ítem ya se envió a cocina.'}
                confirmar={anulacion?.pedido ? 'Anular cuenta' : 'Anular ítem'}
                enviando={ocupado}
                error={errorAnulacion}
                onCerrar={() => setAnulacion(null)}
                onConfirmar={(motivo) => enviarAnulacion(anulacion, motivo)}
            />

            {destinos && (
                <DialogoTransferir abierto={transferir} onCerrar={() => setTransferir(false)} pedido={pedido} destinos={destinos} items={vigentes.filter((i) => i.cantidad_cobrada === 0)} />
            )}
        </AppLayout>
    );
}
