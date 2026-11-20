import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bike, ChevronRight, LayoutGrid, ListOrdered, Minus, Plus, Printer, ReceiptText, Send, ShoppingBag, Trash2, UserRound, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, Dialog, Field, IconButton, Input, Textarea, cx, toast } from '@/Components/ui';
import CartaPedido from '@/Components/Restaurante/CartaPedido';
import DialogoCobroPos from '@/Components/Restaurante/DialogoCobroPos';
import DialogoMotivo from '@/Components/Restaurante/DialogoMotivo';
import DialogoPlato from '@/Components/Restaurante/DialogoPlato';
import LineaCuenta from '@/Components/Restaurante/LineaCuenta';
import SelectorMesa from '@/Components/Restaurante/SelectorMesa';
import { cambiarCantidadLinea, nuevaClave, primerError, soles, tituloCuenta } from '@/Components/Restaurante/formato';
import useImprimirTicket from '@/hooks/useImprimirTicket';

const TIPOS = [
    { clave: 'PARA_LLEVAR', etiqueta: 'Mostrador', icono: ShoppingBag },
    { clave: 'MESA', etiqueta: 'Mesa', icono: LayoutGrid },
    { clave: 'DELIVERY', etiqueta: 'Delivery', icono: Bike },
];

// Mismo redondeo que el servidor: subtotal = round(cantidad × precio, 2), en céntimos.
const centimos = (cantidad, precio) => Math.round(Math.round(cantidad * 1000) * Math.round(precio * 10000) / 100000);

/**
 * Punto de venta único del restaurante: elegir tipo (mostrador, mesa, delivery), tomar el
 * pedido de la carta completa (platos y bebidas), enviarlo a cocina y cobrar, en una pantalla.
 * Una mesa queda como cuenta abierta; mostrador y delivery se cobran en un paso.
 */
export default function Pos({ carta, sugerencias = null, salon, cuenta, inicio, metodosPago, turno, clienteInicial, puede, ultimaVenta }) {
    const { flash = {} } = usePage().props;
    const [tipo, setTipo] = useState(cuenta?.tipo ?? inicio?.tipo ?? 'PARA_LLEVAR');
    const [mesa, setMesa] = useState(inicio?.mesa ?? null);
    const [contacto, setContacto] = useState({ nombre_contacto: '', telefono_contacto: '', direccion_entrega: '' });
    const [borrador, setBorrador] = useState([]);
    const [platoAbierto, setPlatoAbierto] = useState(null);
    const [ultimoElegido, setUltimoElegido] = useState(null);
    const [dialogo, setDialogo] = useState(null); // 'mesas' | 'cuentas' | 'contacto' | 'cobro'
    const [ocupado, setOcupado] = useState(false);
    const [errorCobro, setErrorCobro] = useState(null);
    const [anulacion, setAnulacion] = useState(null);
    const [errorAnulacion, setErrorAnulacion] = useState(null);
    const [vista, setVista] = useState('carta');
    const [venta, setVenta] = useState(null);
    const operacion = useRef(null);
    const { imprimir, imprimiendo } = useImprimirTicket();

    useEffect(() => { if (flash.success) toast.success(flash.success); }, [flash.success]);
    useEffect(() => { if (ultimaVenta) setVenta(ultimaVenta); }, [ultimaVenta]);
    // Al cambiar de cuenta, el ticket nuevo se descarta y se toma el tipo de la cuenta.
    useEffect(() => {
        setBorrador([]);
        setMesa(inicio?.mesa ?? null);
        setTipo(cuenta?.tipo ?? inicio?.tipo ?? 'PARA_LLEVAR');
        operacion.current = null;
    }, [cuenta?.id_pedido, inicio?.mesa?.id_mesa, inicio?.tipo]); // eslint-disable-line react-hooks/exhaustive-deps

    const vigentes = cuenta ? cuenta.detalles.filter((d) => d.estado !== 'ANULADO') : [];
    const porCobrarCuenta = vigentes.reduce((suma, d) => suma + centimos(d.cantidad, d.precio_unitario) - centimos(d.cantidad_cobrada, d.precio_unitario), 0);
    const totalBorrador = useMemo(() => borrador.reduce((suma, l) => suma + centimos(l.cantidad, l.precio_estimado), 0), [borrador]);
    const porCobrar = (porCobrarCuenta + totalBorrador) / 100;
    const unidades = borrador.reduce((suma, l) => suma + l.cantidad, 0);
    const hayPendientes = borrador.length > 0 || vigentes.some((d) => d.estado === 'PENDIENTE');
    const cuentasAbiertas = useMemo(() => [
        ...(salon?.areas ?? []).flatMap((a) => a.mesas.filter((m) => m.cuenta).map((m) => ({ ...m.cuenta, mesa: m.codigo }))),
        ...(salon?.cuentasSinMesa ?? []),
    ], [salon]);

    // ---------- Ticket nuevo (borrador local) ----------
    const elegir = (producto) => {
        setUltimoElegido(producto.id_producto);
        if (producto.grupos.length > 0 || producto.presentaciones.length > 1) {
            setPlatoAbierto(producto);
            return;
        }
        const p = producto.presentaciones[0];
        agregar({ id_precio: p.id_precio, cantidad: 1, selecciones: [], observacion: null, nombre: producto.nombre, detalle: [], precio_estimado: p.precio_venta });
    };

    const agregar = (linea) => {
        setBorrador((actual) => {
            const igual = actual.find((l) => l.id_precio === linea.id_precio && !l.observacion && !linea.observacion && !l.selecciones.length && !linea.selecciones.length);
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

    // ---------- Operaciones con el servidor ----------
    const base = () => ({
        id_pedido: cuenta?.id_pedido ?? null,
        tipo: cuenta ? null : tipo,
        id_mesa: !cuenta && tipo === 'MESA' ? mesa?.id_mesa : null,
        ...(!cuenta && tipo !== 'MESA' ? contacto : {}),
        items: borrador.map(({ clave, id_precio, cantidad, observacion, selecciones }) => ({ clave, id_precio, cantidad, observacion, selecciones })),
    });

    /** La misma operación reintentada (doble toque, red) reutiliza su clave: el servidor no duplica. */
    const claveDe = (accion, datos) => {
        const firma = JSON.stringify([accion, datos]);
        if (operacion.current?.firma !== firma) operacion.current = { firma, clave: nuevaClave() };
        return operacion.current.clave;
    };

    const validarNuevo = () => {
        if (cuenta) return true;
        if (tipo === 'MESA' && !mesa) { setDialogo('mesas'); return false; }
        if (tipo === 'DELIVERY' && !contacto.direccion_entrega.trim()) { setDialogo('contacto'); return false; }
        return true;
    };

    const enviarCocina = () => {
        if (!validarNuevo()) return;
        const datos = base();
        router.post('/pos/enviar', { ...datos, clave: claveDe('enviar', datos) }, {
            preserveScroll: true,
            onStart: () => setOcupado(true),
            onFinish: () => setOcupado(false),
            onSuccess: () => { operacion.current = null; setBorrador([]); },
            onError: (errores) => toast.error('No se pudo enviar', primerError(errores)),
        });
    };

    const abrirCobro = () => {
        if (!validarNuevo()) return;
        setErrorCobro(null);
        setDialogo('cobro');
    };

    const cobrar = (pago) => {
        const datos = { ...base(), ...pago };
        router.post('/pos/cobrar', { ...datos, clave: claveDe('cobrar', datos) }, {
            preserveScroll: true,
            onStart: () => setOcupado(true),
            onFinish: () => setOcupado(false),
            onSuccess: () => {
                operacion.current = null;
                setBorrador([]);
                setDialogo(null);
                setContacto({ nombre_contacto: '', telefono_contacto: '', direccion_entrega: '' });
            },
            onError: (errores) => setErrorCobro(primerError(errores)),
        });
    };

    const cambiarCantidad = (item, cantidad) => router.put(`/pedidos/items/${item.id_detalle_pedido}`, { cantidad }, {
        preserveScroll: true,
        onStart: () => setOcupado(true),
        onFinish: () => setOcupado(false),
        onError: (errores) => toast.error('No se cambió la cantidad', primerError(errores)),
    });

    const quitar = (item) => {
        setErrorAnulacion(null);
        if (item.estado === 'PENDIENTE') return anular(item, null);
        if (!puede.anular_enviados) {
            toast.warning('Necesitas autorización', `${item.nombre_producto} ya salió a cocina. Pide a un supervisor que lo anule.`);
            return undefined;
        }
        return setAnulacion(item);
    };

    const anular = (item, motivo, recuperar = false) => router.post(`/pedidos/items/${item.id_detalle_pedido}/anular`, { motivo, recuperar_insumos: recuperar }, {
        preserveScroll: true,
        onStart: () => setOcupado(true),
        onFinish: () => setOcupado(false),
        onSuccess: () => setAnulacion(null),
        onError: (errores) => (anulacion ? setErrorAnulacion(primerError(errores)) : toast.error('No se pudo quitar', primerError(errores))),
    });

    const abrirMesas = () => { router.reload({ only: ['salon'] }); setDialogo('mesas'); };
    const abrirCuentas = () => { router.reload({ only: ['salon'] }); setDialogo('cuentas'); };
    const elegirMesa = (m) => {
        setDialogo(null);
        if (m.cuenta) { router.get('/pos', { cuenta: m.cuenta.id_pedido }); return; }
        setTipo('MESA');
        setMesa(m);
    };
    const nuevaVenta = () => { setVenta(null); router.get('/pos'); };

    const tituloTicket = cuenta
        ? `${tituloCuenta(cuenta)} · #${cuenta.numero}`
        : tipo === 'MESA' ? (mesa ? `Mesa ${mesa.codigo}` : 'Mesa sin elegir') : tipo === 'DELIVERY' ? 'Delivery' : 'Venta de mostrador';

    return (
        <AppLayout title="Punto de venta">
            <Head title="Punto de venta" />

            {/* Barra superior: tipo de venta, mesa y cuentas abiertas */}
            <div className="mb-3 flex flex-wrap items-center gap-2">
                <div className="grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1" role="radiogroup" aria-label="Tipo de venta">
                    {TIPOS.map(({ clave, etiqueta, icono: Icono }) => (
                        <button
                            key={clave}
                            type="button"
                            role="radio"
                            aria-checked={(cuenta?.tipo ?? tipo) === clave}
                            disabled={Boolean(cuenta)}
                            onClick={() => { setTipo(clave); if (clave === 'MESA' && !mesa) abrirMesas(); }}
                            className={cx('flex h-10 items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-medium disabled:cursor-not-allowed',
                                (cuenta?.tipo ?? tipo) === clave ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900')}
                        >
                            <Icono className="h-4 w-4" aria-hidden="true" />{etiqueta}
                        </button>
                    ))}
                </div>
                {!cuenta && tipo === 'MESA' && <Button variant="secondary" icon={LayoutGrid} onClick={abrirMesas}>{mesa ? `Mesa ${mesa.codigo}` : 'Elegir mesa'}</Button>}
                {!cuenta && tipo !== 'MESA' && (
                    <Button variant="secondary" icon={UserRound} onClick={() => setDialogo('contacto')}>
                        {contacto.nombre_contacto || (tipo === 'DELIVERY' ? 'Datos de entrega' : 'Cliente (opcional)')}
                    </Button>
                )}
                <div className="ml-auto flex gap-2">
                    {cuenta && <Button variant="secondary" onClick={nuevaVenta}>Nueva venta</Button>}
                    <Button variant="secondary" icon={ListOrdered} onClick={abrirCuentas}>Cuentas abiertas</Button>
                </div>
            </div>

            {puede.cobrar && !turno && (
                <p className="mb-3 flex flex-wrap items-center gap-2 rounded-xl bg-amber-50 px-4 py-2.5 text-sm text-amber-800">
                    Abre la caja para poder cobrar. <Link href="/caja" className="font-semibold underline">Ir a caja</Link>
                </p>
            )}

            <div className="mb-3 grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 lg:hidden" role="tablist">
                {[['carta', 'Carta'], ['ticket', `Ticket (${vigentes.length + borrador.length})`]].map(([clave, etiqueta]) => (
                    <button key={clave} type="button" role="tab" aria-selected={vista === clave} onClick={() => setVista(clave)}
                        className={cx('h-10 rounded-lg text-sm font-medium', vista === clave ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600')}>
                        {etiqueta}
                    </button>
                ))}
            </div>

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_25rem]">
                <Card className={cx('flex h-[calc(100dvh-13rem)] min-h-[28rem] flex-col overflow-hidden', vista !== 'carta' && 'hidden lg:flex')}>
                    <CartaPedido carta={carta} onElegir={elegir} sugerencias={sugerencias} relacionados={sugerencias?.acompanan?.[ultimoElegido] ?? []} />
                </Card>

                <Card className={cx('flex flex-col overflow-hidden lg:h-[calc(100dvh-13rem)] lg:min-h-[28rem]', vista !== 'ticket' && 'hidden lg:flex')}>
                    <header className="border-b border-slate-100 px-4 py-3">
                        <h2 className="text-base font-semibold text-slate-900">{tituloTicket}</h2>
                        {cuenta?.mozo && <p className="text-xs text-slate-500">{cuenta.mozo}</p>}
                    </header>

                    <div className="flex-1 overflow-y-auto">
                        {vigentes.length > 0 && (
                            <ul className="divide-y divide-slate-100">
                                {vigentes.map((item) => (
                                    <LineaCuenta key={item.id_detalle_pedido} item={item} editable ocupado={ocupado} onCantidad={cambiarCantidad} onQuitar={quitar} />
                                ))}
                            </ul>
                        )}
                        {borrador.length > 0 && (
                            <section aria-label="Nuevos" className="border-t border-amber-200 bg-amber-50/60">
                                <p className="px-4 pt-3 text-xs font-semibold tracking-wide text-amber-800 uppercase">Nuevos</p>
                                <ul className="divide-y divide-amber-100">
                                    {borrador.map((l) => (
                                        <li key={l.clave} className="flex items-center gap-3 px-4 py-2.5">
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium text-slate-900">{l.nombre}</p>
                                                {l.detalle.length > 0 && <p className="text-xs text-slate-600">{l.detalle.join(' · ')}</p>}
                                                {l.observacion && <p className="text-xs text-amber-700 italic">“{l.observacion}”</p>}
                                                <p className="font-mono text-xs text-slate-500 tabular-nums">{soles(centimos(l.cantidad, l.precio_estimado) / 100)}</p>
                                            </div>
                                            <IconButton icon={l.cantidad === 1 ? X : Minus} label={l.cantidad === 1 ? 'Quitar' : 'Uno menos'} onClick={() => cambiarBorrador(l.clave, -1)} className="h-10 w-10 bg-white ring-1 ring-slate-200" />
                                            <span className="w-6 text-center font-mono text-sm font-semibold tabular-nums">{l.cantidad}</span>
                                            <IconButton icon={Plus} label="Uno más" onClick={() => cambiarBorrador(l.clave, 1)} className="h-10 w-10 bg-white ring-1 ring-slate-200" />
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                        {vigentes.length === 0 && borrador.length === 0 && (
                            <p className="px-6 py-12 text-center text-sm text-slate-500">Toca los platos y bebidas de la carta para agregarlos.</p>
                        )}
                    </div>

                    <footer className="space-y-3 border-t border-slate-200 bg-slate-50 p-4">
                        <div className="flex items-baseline justify-between">
                            <span className="text-sm text-slate-600">Por cobrar</span>
                            <span className="font-mono text-2xl font-semibold tabular-nums">{soles(porCobrar)}</span>
                        </div>
                        <div className="grid grid-cols-[auto_1fr_1fr] gap-2">
                            <Button variant="secondary" size="lg" icon={Trash2} onClick={() => setBorrador([])} disabled={ocupado || !borrador.length} aria-label="Descartar nuevos" />
                            {puede.enviar && (
                                <Button variant="secondary" size="lg" icon={Send} onClick={enviarCocina} disabled={ocupado || !hayPendientes} block>
                                    {cuenta || tipo === 'MESA' ? 'Enviar' : 'Guardar'}
                                </Button>
                            )}
                            {puede.cobrar && (
                                <Button size="lg" icon={ReceiptText} onClick={abrirCobro} disabled={ocupado || porCobrar <= 0 || !turno} block className={cx(!puede.enviar && 'col-span-2')}>
                                    Cobrar
                                </Button>
                            )}
                        </div>
                        {cuenta && <Link href={`/pedidos/${cuenta.id_pedido}/cobrar`} className="block text-center text-xs font-medium text-slate-500 hover:text-slate-800">Dividir cuenta</Link>}
                    </footer>
                </Card>
            </div>

            {/* Acceso rápido al ticket en celular */}
            {vista === 'carta' && unidades > 0 && (
                <button type="button" onClick={() => setVista('ticket')} className="fixed inset-x-4 bottom-4 z-20 flex h-14 items-center justify-between rounded-2xl bg-brand px-5 font-semibold text-on-brand shadow-lg lg:hidden">
                    <span>Ver ticket · {unidades}</span><span className="flex items-center gap-1">{soles(porCobrar)}<ChevronRight className="h-4 w-4" /></span>
                </button>
            )}

            <DialogoPlato producto={platoAbierto} grupos={carta.grupos} onCerrar={() => setPlatoAbierto(null)} onAgregar={agregar} />

            <SelectorMesa abierto={dialogo === 'mesas'} onCerrar={() => setDialogo(null)} areas={salon?.areas ?? []} onElegir={elegirMesa} />

            <Dialog open={dialogo === 'cuentas'} onClose={() => setDialogo(null)} title="Cuentas abiertas" size="md">
                {cuentasAbiertas.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-slate-500">No hay cuentas abiertas.</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {cuentasAbiertas.map((c) => (
                            <li key={c.id_pedido}>
                                <button type="button" onClick={() => { setDialogo(null); router.get('/pos', { cuenta: c.id_pedido }); }} className="flex w-full items-center gap-3 px-5 py-3 text-left hover:bg-slate-50">
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-medium">{tituloCuenta(c)}</span>
                                        <span className="block text-xs text-slate-500">#{c.numero} · {c.items} ítems{c.mozo ? ` · ${c.mozo}` : ''}</span>
                                    </span>
                                    <span className="font-mono text-sm font-semibold tabular-nums">{soles(c.total)}</span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </Dialog>

            <Dialog open={dialogo === 'contacto'} onClose={() => setDialogo(null)} title={tipo === 'DELIVERY' ? 'Datos de entrega' : 'Cliente'} size="sm"
                footer={<Button onClick={() => setDialogo(null)}>Listo</Button>}>
                <div className="space-y-4 p-5">
                    <Field label="Nombre"><Input value={contacto.nombre_contacto} maxLength={120} onChange={(e) => setContacto({ ...contacto, nombre_contacto: e.target.value })} autoFocus /></Field>
                    <Field label="Teléfono"><Input value={contacto.telefono_contacto} maxLength={20} inputMode="tel" onChange={(e) => setContacto({ ...contacto, telefono_contacto: e.target.value })} /></Field>
                    {tipo === 'DELIVERY' && (
                        <Field label="Dirección de entrega" required><Textarea rows={2} maxLength={255} value={contacto.direccion_entrega} onChange={(e) => setContacto({ ...contacto, direccion_entrega: e.target.value })} /></Field>
                    )}
                </div>
            </Dialog>

            <DialogoCobroPos
                abierto={dialogo === 'cobro'}
                onCerrar={() => setDialogo(null)}
                total={porCobrar}
                metodosPago={metodosPago}
                clienteInicial={clienteInicial}
                puedeDescontar={puede.descontar}
                procesando={ocupado}
                error={errorCobro}
                onCobrar={cobrar}
            />

            <DialogoMotivo
                abierto={Boolean(anulacion)}
                titulo={`Anular ${anulacion?.nombre_producto ?? ''}`}
                descripcion="Este ítem ya se envió a cocina."
                confirmar="Anular ítem"
                enviando={ocupado}
                error={errorAnulacion}
                puedeRecuperar={puede.anular_enviados && anulacion?.estado === 'ENVIADO'}
                onCerrar={() => setAnulacion(null)}
                onConfirmar={(motivo, recuperar) => anular(anulacion, motivo, recuperar)}
            />

            <Dialog open={Boolean(venta)} onClose={nuevaVenta} title="Venta registrada" size="sm"
                footer={(
                    <>
                        <Button variant="secondary" icon={Printer} loading={imprimiendo} onClick={() => imprimir(venta.id_venta)}>Imprimir ticket</Button>
                        <Button onClick={nuevaVenta}>Nueva venta</Button>
                    </>
                )}>
                {venta && (
                    <div className="space-y-2 p-5 text-center">
                        <Badge tone="success">{venta.comprobante}</Badge>
                        <p className="font-mono text-3xl font-semibold tabular-nums">{soles(venta.total)}</p>
                        {venta.vuelto > 0 && <p className="text-lg">Vuelto: <span className="font-mono font-semibold">{soles(venta.vuelto)}</span></p>}
                        <p className="text-xs text-slate-500">Cuenta #{venta.cuenta}. El comprobante se envía a SUNAT en segundo plano.</p>
                    </div>
                )}
            </Dialog>
        </AppLayout>
    );
}
