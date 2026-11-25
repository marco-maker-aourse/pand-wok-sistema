import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, cx, toast } from '@/Components/ui';
import CartaPedido from '@/Components/Restaurante/CartaPedido';
import DialogoCobroPos from '@/Components/Restaurante/DialogoCobroPos';
import DialogoMotivo from '@/Components/Restaurante/DialogoMotivo';
import DialogoPlato from '@/Components/Restaurante/DialogoPlato';
import SelectorMesa from '@/Components/Restaurante/SelectorMesa';
import BarraTipoVenta, { AvisoRequisitosCobro } from '@/Components/Restaurante/pos/BarraTipoVenta';
import { DialogoContacto, DialogoCuentasAbiertas, DialogoVentaRegistrada } from '@/Components/Restaurante/pos/DialogosPos';
import TicketPos, { centimos } from '@/Components/Restaurante/pos/TicketPos';
import { cambiarCantidadLinea, nuevaClave, primerError, soles, tituloCuenta } from '@/Components/Restaurante/formato';
import useAvisoBorrador from '@/hooks/useAvisoBorrador';
import useImprimirTicket from '@/hooks/useImprimirTicket';

/**
 * Punto de venta único del restaurante: elegir tipo (mostrador, mesa, delivery), tomar el
 * pedido de la carta, guardarlo, enviarlo a cocina cuando se solicite y cobrarlo.
 */
export default function Pos({ carta, sugerencias = null, salon, cuenta, inicio, metodosPago, turno, clienteInicial, requisitosCobro = [], puede, ultimaVenta }) {
    const { flash = {} } = usePage().props;
    const [tipo, setTipo] = useState(cuenta?.tipo ?? inicio?.tipo ?? 'PARA_LLEVAR');
    const [mesa, setMesa] = useState(inicio?.mesa ?? null);
    const [contacto, setContacto] = useState({ nombre_contacto: '', telefono_contacto: '', direccion_entrega: '' });
    const [borrador, setBorrador] = useState([]);
    const [platoAbierto, setPlatoAbierto] = useState(null);
    const [ultimoElegido, setUltimoElegido] = useState(null);
    const [recien, setRecien] = useState(null);
    const [dialogo, setDialogo] = useState(null); // 'mesas' | 'cuentas' | 'contacto' | 'cobro'
    const [ocupado, setOcupado] = useState(false);
    const [errorCobro, setErrorCobro] = useState(null);
    const [anulacion, setAnulacion] = useState(null);
    const [errorAnulacion, setErrorAnulacion] = useState(null);
    const [vista, setVista] = useState('carta');
    const [venta, setVenta] = useState(null);
    const operacion = useRef(null);
    const { imprimir, imprimiendo } = useImprimirTicket();
    useAvisoBorrador(borrador.length > 0, 'Hay platos sin guardar en este pedido. Si sales ahora se perderán.');

    useEffect(() => { if (flash.success) toast.success(flash.success); }, [flash.success]);
    // La comanda salió a la pantalla de cocina, pero su estación no tiene impresora lista.
    useEffect(() => { if (flash.warning) toast.warning('No se imprimió la comanda', flash.warning); }, [flash.warning]);
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
    const enTicket = useMemo(() => borrador.reduce((mapa, l) => (l.id_producto ? { ...mapa, [l.id_producto]: (mapa[l.id_producto] ?? 0) + l.cantidad } : mapa), {}), [borrador]);
    const hayPendientes = borrador.length > 0 || vigentes.some((d) => d.estado === 'PENDIENTE' && Number(d.cantidad_cobrada) === 0);
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
        agregar({ id_producto: producto.id_producto, id_precio: p.id_precio, cantidad: 1, selecciones: [], observacion: null, nombre: producto.nombre, distintivo: producto.distintivo ?? null, detalle: [], precio_estimado: p.precio_venta });
    };

    const agregar = (linea) => {
        // Confirmación táctil y visual: el plato tocado parpadea y suma su contador.
        try { navigator.vibrate?.(12); } catch { /* sin vibración */ }
        setRecien(linea.id_producto ?? null);
        setTimeout(() => setRecien(null), 450);
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

    const registrarPedido = (accion) => {
        if (!validarNuevo()) return;
        const datos = base();
        router.post(`/pos/${accion}`, { ...datos, clave: claveDe(accion, datos) }, {
            preserveScroll: true,
            onStart: () => setOcupado(true),
            onFinish: () => setOcupado(false),
            onSuccess: () => { operacion.current = null; setBorrador([]); },
            onError: (errores) => toast.error(accion === 'enviar' ? 'No se pudo enviar a cocina' : 'No se pudo guardar', primerError(errores)),
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
                setVista('carta');
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
    const elegirTipo = (clave) => { setTipo(clave); if (clave === 'MESA' && !mesa) abrirMesas(); };
    const irACuenta = (c) => { setDialogo(null); router.get('/pos', { cuenta: c.id_pedido }); };

    const tituloTicket = cuenta
        ? `${tituloCuenta(cuenta)} · #${cuenta.numero}`
        : tipo === 'MESA' ? (mesa ? `Mesa ${mesa.codigo}` : 'Mesa sin elegir') : tipo === 'DELIVERY' ? 'Delivery' : 'Venta de mostrador';

    const panel = 'flex h-[calc(100dvh-10.5rem)] min-h-[24rem] flex-col overflow-hidden lg:h-[calc(100dvh-13rem)] lg:min-h-[28rem]';

    return (
        <AppLayout title="Punto de venta" sinBarraInferior>
            <Head title="Punto de venta" />

            <BarraTipoVenta
                tipo={tipo}
                cuenta={cuenta}
                mesa={mesa}
                contacto={contacto}
                cantidadCuentas={cuentasAbiertas.length}
                onTipo={elegirTipo}
                onMesas={abrirMesas}
                onContacto={() => setDialogo('contacto')}
                onCuentas={abrirCuentas}
                onNueva={nuevaVenta}
            />

            {puede.cobrar && <AvisoRequisitosCobro requisitos={requisitosCobro} />}

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_25rem]">
                <Card className={cx(panel, vista !== 'carta' && 'hidden lg:flex')}>
                    <CartaPedido carta={carta} onElegir={elegir} sugerencias={sugerencias} relacionados={sugerencias?.acompanan?.[ultimoElegido] ?? []} enTicket={enTicket} recien={recien} />
                </Card>

                <Card className={cx(panel, vista !== 'ticket' && 'hidden lg:flex')}>
                    <TicketPos
                        titulo={tituloTicket}
                        tipo={tipo}
                        cuenta={cuenta}
                        vigentes={vigentes}
                        borrador={borrador}
                        porCobrar={porCobrar}
                        ocupado={ocupado}
                        puede={puede}
                        requisitosCobro={puede.cobrar ? requisitosCobro : []}
                        hayPendientes={hayPendientes}
                        onCambiarBorrador={cambiarBorrador}
                        onDescartar={() => setBorrador([])}
                        onGuardar={() => registrarPedido('guardar')}
                        onEnviar={() => registrarPedido('enviar')}
                        onCobrar={abrirCobro}
                        onCantidadCuenta={cambiarCantidad}
                        onQuitarCuenta={quitar}
                        onVolver={() => setVista('carta')}
                    />
                </Card>
            </div>

            {/* Acceso rápido al pedido en celular */}
            {vista === 'carta' && (unidades > 0 || vigentes.length > 0) && (
                <button type="button" onClick={() => setVista('ticket')} className="volumen-marca fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-20 flex h-16 items-center justify-between rounded-2xl px-5 text-base font-semibold text-on-brand shadow-[var(--shadow-flotante)] active:scale-[0.99] lg:hidden">
                    <span className="flex items-center gap-2"><span className="grid h-8 min-w-8 place-items-center rounded-full bg-white/20 px-2 font-mono tabular-nums">{unidades + vigentes.reduce((n, d) => n + Number(d.cantidad), 0)}</span>Ver pedido</span>
                    <span className="flex items-center gap-1 font-mono tabular-nums">{soles(porCobrar)}<ChevronRight className="h-5 w-5" aria-hidden="true" /></span>
                </button>
            )}

            <DialogoPlato producto={platoAbierto} grupos={carta.grupos} onCerrar={() => setPlatoAbierto(null)} onAgregar={agregar} />

            <SelectorMesa abierto={dialogo === 'mesas'} onCerrar={() => setDialogo(null)} areas={salon?.areas ?? []} onElegir={elegirMesa} />

            <DialogoCuentasAbiertas abierto={dialogo === 'cuentas'} onCerrar={() => setDialogo(null)} cuentas={cuentasAbiertas} onElegir={irACuenta} />

            <DialogoContacto abierto={dialogo === 'contacto'} onCerrar={() => setDialogo(null)} tipo={tipo} contacto={contacto} onCambiar={setContacto} />

            <DialogoCobroPos
                abierto={dialogo === 'cobro'}
                onCerrar={() => setDialogo(null)}
                total={porCobrar}
                metodosPago={metodosPago}
                clienteInicial={clienteInicial}
                puedeDescontar={puede.descontar}
                puedeEnviar={puede.enviar && hayPendientes}
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

            <DialogoVentaRegistrada venta={venta} onNueva={nuevaVenta} onImprimir={imprimir} imprimiendo={imprimiendo} />
        </AppLayout>
    );
}
