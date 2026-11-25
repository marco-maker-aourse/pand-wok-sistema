import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRightLeft, Ban, History, Minus, Plus, ReceiptText, Save, Send, Trash2, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, IconButton, cx, toast } from '@/Components/ui';
import CartaPedido from '@/Components/Restaurante/CartaPedido';
import ComandasPedido from '@/Components/Restaurante/ComandasPedido';
import DialogoMotivo from '@/Components/Restaurante/DialogoMotivo';
import DialogoPlato from '@/Components/Restaurante/DialogoPlato';
import DialogoTransferir from '@/Components/Restaurante/DialogoTransferir';
import LineaCuenta from '@/Components/Restaurante/LineaCuenta';
import { ESTADO_PEDIDO, describirEvento } from '@/Components/Restaurante/estados';
import { cambiarCantidadLinea, hora, nuevaClave, primerError, soles, tiempoDesde, tituloCuenta } from '@/Components/Restaurante/formato';
import Distintivo from '@/Components/Restaurante/Distintivo';
import useAvisoBorrador from '@/hooks/useAvisoBorrador';

/**
 * Toma de pedido. Los platos elegidos van primero a un borrador local (rápido en tablet,
 * sin una petición por toque) y se guardan juntos; cada línea lleva su clave de
 * idempotencia, así un reintento nunca duplica ítems.
 */
export default function Pedido({ pedido, carta, destinos, puede }) {
    const { flash = {} } = usePage().props;
    const claveEnvio = useRef(null);
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
    useEffect(() => {
        if (flash.warning) toast.warning('No se imprimió la comanda', flash.warning);
    }, [flash.warning]);

    useAvisoBorrador(borrador.length > 0, 'Hay platos sin guardar en la cuenta. Si sales ahora se perderán.');

    const vigentes = pedido.detalles.filter((d) => d.estado !== 'ANULADO');
    const anulados = pedido.detalles.filter((d) => d.estado === 'ANULADO');
    const totalBorrador = useMemo(() => borrador.reduce((s, l) => s + l.precio_estimado * l.cantidad, 0), [borrador]);
    const editable = puede.editar;
    const tienePendientes = vigentes.some((item) => item.estado === 'PENDIENTE' && Number(item.cantidad_cobrada) === 0);
    const estadoPedido = ESTADO_PEDIDO[pedido.estado] ?? ESTADO_PEDIDO.ABIERTO;

    const elegir = (producto) => {
        if (producto.grupos.length > 0 || producto.presentaciones.length > 1) {
            setPlatoAbierto(producto);
            return;
        }
        // Plato simple: se suma directo al borrador (o aumenta la línea igual).
        const presentacion = producto.presentaciones[0];
        agregarAlBorrador({ id_precio: presentacion.id_precio, cantidad: 1, selecciones: [], observacion: null, nombre: producto.nombre, distintivo: producto.distintivo ?? null, detalle: [], precio_estimado: presentacion.precio_venta });
    };

    const agregarAlBorrador = (linea) => {
        setBorrador((actual) => {
            const igual = actual.find((l) => l.id_precio === linea.id_precio && !l.observacion && !linea.observacion && l.selecciones.length === 0 && linea.selecciones.length === 0);
            if (igual) return actual.map((l) => (l === igual ? { ...l, cantidad: l.cantidad + linea.cantidad } : l));
            return [...actual, { ...linea, clave: nuevaClave() }];
        });
        setPlatoAbierto(null);
    };

    const cambiarBorrador = (clave, delta) => setBorrador((actual) => actual
        .map((l) => {
            if (l.clave !== clave) return l;
            const { linea, repartida } = cambiarCantidadLinea(l, delta);
            if (repartida) toast.error('Opciones repartidas', 'Quita la línea y vuelve a elegir el plato para cambiar la cantidad.');
            return linea;
        })
        .filter((l) => l.cantidad > 0));

    const guardar = (accion) => {
        const enviar = accion !== 'items';
        const datos = {};
        if (accion !== 'enviar-cocina') {
            datos.items = borrador.map(({ clave, id_precio, cantidad, observacion, selecciones }) => ({ clave, id_precio, cantidad, observacion, selecciones }));
        }
        if (enviar) {
            const firma = JSON.stringify([pedido.id_pedido, accion, datos.items]);
            if (claveEnvio.current?.firma !== firma) {
                claveEnvio.current = { firma, clave: nuevaClave() };
            }
            datos.clave_envio = claveEnvio.current.clave;
        }
        router.post(`/pedidos/${pedido.id_pedido}/${accion}`, datos, {
            preserveScroll: true,
            onStart: () => setOcupado(true),
            onFinish: () => setOcupado(false),
            onSuccess: () => {
                if (enviar) claveEnvio.current = null;
                if (accion !== 'enviar-cocina') setBorrador([]);
                setVista('cuenta');
            },
            onError: (errores) => {
                if (enviar) claveEnvio.current = null;
                toast.error(enviar ? 'No se pudo enviar a cocina' : 'No se guardó el pedido', primerError(errores));
            },
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

    const enviarAnulacion = (objetivo, motivo, recuperar = false) => {
        const url = objetivo.item ? `/pedidos/items/${objetivo.item.id_detalle_pedido}/anular` : `/pedidos/${pedido.id_pedido}/anular`;
        router.post(url, { motivo, recuperar_insumos: recuperar }, {
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
                {puede.cobrar && <Button icon={ReceiptText} href={`/pedidos/${pedido.id_pedido}/cobrar`}>{editable ? 'Cobrar cuenta' : 'Ver cobros'}</Button>}
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
                                                <p className="flex flex-wrap items-center gap-x-1.5 text-sm font-medium text-slate-900">{linea.nombre}<Distintivo texto={linea.distintivo} /></p>
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

                        <ComandasPedido comandas={pedido.comandas} puedeReimprimir={puede.reimprimir} disabled={ocupado} />
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
                        {pedido.saldo !== pedido.total && (
                            <p className="flex justify-between text-sm text-slate-700"><span>Saldo por cobrar</span><strong>{soles(pedido.saldo)}</strong></p>
                        )}
                        {borrador.length > 0 && (
                            <>
                                <p className="text-right text-xs text-slate-500">+ {soles(totalBorrador)} por guardar (estimado)</p>
                                <div className="grid grid-cols-[auto_1fr] gap-2">
                                    <Button variant="secondary" size="lg" icon={Trash2} onClick={() => setBorrador([])} disabled={ocupado} aria-label="Descartar lo no guardado" />
                                    <Button size="lg" icon={Save} onClick={() => guardar('items')} loading={ocupado} block>Guardar {borrador.reduce((s, l) => s + l.cantidad, 0)} en la cuenta</Button>
                                </div>
                            </>
                        )}
                        {puede.enviar && borrador.length > 0 && (
                            <Button size="lg" icon={Send} onClick={() => guardar('guardar-y-enviar')} disabled={ocupado} block>Guardar y enviar</Button>
                        )}
                        {puede.enviar && tienePendientes && (
                            <Button size="lg" variant="secondary" icon={Send} onClick={() => guardar('enviar-cocina')} disabled={ocupado} block>Enviar a cocina</Button>
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
                puedeRecuperar={puede.anular_enviados && (anulacion?.pedido ? vigentes.some((item) => item.estado === 'ENVIADO') : anulacion?.item?.estado === 'ENVIADO')}
                onConfirmar={(motivo, recuperar) => enviarAnulacion(anulacion, motivo, recuperar)}
            />

            {destinos && (
                <DialogoTransferir abierto={transferir} onCerrar={() => setTransferir(false)} pedido={pedido} destinos={destinos} items={vigentes.filter((i) => i.cantidad_cobrada === 0)} />
            )}
        </AppLayout>
    );
}
