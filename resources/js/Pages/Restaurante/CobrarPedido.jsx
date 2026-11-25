import { useRef, useState } from 'react';
import { Head, router, usePage, usePoll } from '@inertiajs/react';
import { ReceiptText } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, Field, Input, toast } from '@/Components/ui';
import PanelPagos from '@/Components/Ventas/PanelPagos';
import DialogoConfirmarCobro from '@/Components/Ventas/pos/DialogoConfirmarCobro';
import ClienteCobro from '@/Components/Restaurante/ClienteCobro';
import SelectorComprobante, { explicarComprobante } from '@/Components/Restaurante/SelectorComprobante';
import { nuevaClave, primerError, soles, tituloCuenta } from '@/Components/Restaurante/formato';
import { montoDePago, motivoPagos, pagosParaEnviar, saldoPagos } from '@/Components/Restaurante/pagos';

const centimos = (cantidad, precio) => Math.round(Math.round(cantidad * 1000) * Math.round(precio * 10000) / 100000);
const disponible = (item) => Number((item.cantidad - item.cantidad_cobrada).toFixed(3));
const pagoVacio = (id = '') => ({ id_metodo: id, monto: '', monto_recibido: '', nro_referencia: '' });

export default function CobrarPedido({ pedido, items, cobros, clienteInicial, metodosPago, turno, puedeDescontar, puedeVerPedido }) {
    const { flash } = usePage().props;
    const [seleccion, setSeleccion] = useState(null);
    const [tipo, setTipo] = useState('BOLETA');
    const [cliente, setCliente] = useState(clienteInicial);
    const [descuento, setDescuento] = useState('');
    const [pagos, setPagos] = useState(() => [pagoVacio(metodosPago[0]?.id_metodo)]);
    const [confirmar, setConfirmar] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const intento = useRef(null);
    usePoll(8000, { only: ['cobros'] });

    const cobrables = items.filter((item) => disponible(item) > 0);
    const cantidades = seleccion ?? Object.fromEntries(cobrables.map((item) => [item.id_detalle_pedido, disponible(item)]));
    const lineas = cobrables.map((item) => ({
        id_detalle_pedido: item.id_detalle_pedido,
        cantidad: Number(cantidades[item.id_detalle_pedido] || 0),
        cobrada: Number(item.cantidad_cobrada),
    })).filter((item) => item.cantidad > 0);
    const bruto = cobrables.reduce((total, item) => {
        const cobrar = Number(cantidades[item.id_detalle_pedido] || 0);
        const cobrada = Number(item.cantidad_cobrada);
        return total + centimos(cobrada + cobrar, item.precio_unitario) - centimos(cobrada, item.precio_unitario);
    }, 0);
    const total = (bruto - Math.round(Number(descuento || 0) * 100)) / 100;
    const saldo = saldoPagos(pagos, total) / 100;
    const metodoDePago = (pago) => metodosPago.find((metodo) => metodo.id_metodo === Number(pago.id_metodo));
    const actualizarPago = (indice, campo, valor) => setPagos((actuales) => actuales.map((pago, i) => i === indice ? { ...pago, [campo]: valor } : pago));
    const seleccionarMetodo = (indice, metodo) => setPagos((actuales) => actuales.map((pago, i) => i === indice ? { ...pagoVacio(metodo.id_metodo), monto: pago.monto } : pago));
    const agregarPago = () => {
        const libre = metodosPago.find((metodo) => !pagos.some((pago) => Number(pago.id_metodo) === metodo.id_metodo));
        if (libre) setPagos([...pagos, pagoVacio(libre.id_metodo)]);
    };
    const retirarPago = (indice) => setPagos((actuales) => actuales.filter((_, i) => i !== indice));
    const montosValidos = cobrables.every((item) => {
        const valor = Number(cantidades[item.id_detalle_pedido] || 0);
        return Number.isFinite(valor) && valor >= 0 && valor <= disponible(item) && valor === Number(valor.toFixed(3));
    });

    // Nunca un botón muerto: se explica qué falta para cobrar.
    const motivoCobro = !turno ? 'Abre un turno de caja para cobrar.'
        : !cliente ? 'Elige el cliente.'
        : !montosValidos ? 'Revisa las cantidades por cobrar.'
        : lineas.length === 0 ? 'Elige al menos un ítem para cobrar.'
        : tipo === 'FACTURA' && cliente.num_documento?.length !== 11 ? 'La factura necesita un cliente con RUC (11 dígitos).'
        : motivoPagos(pagos, metodosPago, total);

    const registrar = () => {
        if (procesando) return;
        const datos = {
            tipo_comprobante: tipo, id_cliente: cliente?.id_cliente ?? null,
            descuento_global: Number(descuento || 0).toFixed(2),
            items: lineas,
            pagos: pagosParaEnviar(pagos, metodosPago, total),
        };
        const firma = JSON.stringify(datos);
        if (intento.current?.firma !== firma) intento.current = { firma, clave: nuevaClave() };
        router.post(`/pedidos/${pedido.id_pedido}/cobrar`, { ...datos, clave_cobro: intento.current.clave }, {
            preserveScroll: true,
            onStart: () => setProcesando(true),
            onFinish: () => setProcesando(false),
            onError: (errores) => toast.error('No se registró el cobro', primerError(errores)),
            onSuccess: () => {
                intento.current = null;
                setConfirmar(false);
                setSeleccion(null);
                setDescuento('');
                setPagos([pagoVacio(metodosPago[0]?.id_metodo)]);
            },
        });
    };

    return (
        <AppLayout title="Cobrar cuenta">
            <Head title="Cobrar cuenta" />
            <header className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h1 className="text-xl font-semibold">{tituloCuenta(pedido)} · #{pedido.numero}</h1>
                    <p className="text-sm text-slate-600">{pedido.estado === 'CERRADO' ? 'Cuenta cerrada' : 'Cobro de pedido'}</p>
                </div>
                <Button variant="secondary" href={puedeVerPedido ? `/pedidos/${pedido.id_pedido}` : '/salon'}>{puedeVerPedido ? 'Ver pedido' : 'Volver al salón'}</Button>
            </header>
            {flash?.success && <p role="status" className="mb-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p>}
            {pedido.estado === 'ABIERTO' && (
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="space-y-3 p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="font-semibold">Dividir cuenta</h2>
                            <Button variant="secondary" onClick={() => setSeleccion(null)}>Cobrar todo</Button>
                        </div>
                        <ul className="divide-y">
                            {cobrables.map((item) => <li key={item.id_detalle_pedido} className="flex min-h-14 items-center gap-3 py-2">
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium">{item.nombre_producto}</p>
                                    <p className="text-xs text-slate-600">{disponible(item)} por cobrar · {soles(item.precio_unitario)} c/u</p>
                                </div>
                                <Input aria-label={`Cantidad por cobrar de ${item.nombre_producto}`} className="w-24" type="number" inputMode="decimal" min={0} max={disponible(item)} step={0.001}
                                    value={cantidades[item.id_detalle_pedido] ?? ''}
                                    onChange={(e) => setSeleccion({ ...cantidades, [item.id_detalle_pedido]: e.target.value })} />
                            </li>)}
                        </ul>
                        {!cobrables.length && <p className="text-sm text-slate-500">Agrega ítems al pedido para habilitar el cobro.</p>}
                    </Card>
                    <Card className="space-y-4 p-4">
                        <h2 className="font-semibold">Comprobante y pago</h2>
                        {!turno && <div className="flex flex-wrap items-center gap-2"><p className="text-sm text-amber-800">Abre un turno para cobrar.</p><Button href="/caja" variant="secondary">Ir a caja</Button></div>}
                        <SelectorComprobante valor={tipo} onCambiar={setTipo} />
                        <p className="text-xs text-slate-500">{explicarComprobante(tipo, !cliente || /^0+$/.test(cliente.num_documento ?? ''))}</p>
                        <ClienteCobro cliente={cliente} cambiar={setCliente} />
                        {puedeDescontar && <Field label="Descuento (S/)"><Input type="number" min={0} step={0.01} value={descuento} onChange={(e) => setDescuento(e.target.value)} /></Field>}
                        <PanelPagos pagos={pagos} metodosPago={metodosPago} totalVenta={total} saldoPendiente={saldo} metodoDePago={metodoDePago}
                            onAgregar={agregarPago} onSeleccionarMetodo={seleccionarMetodo} onActualizar={actualizarPago} onQuitar={retirarPago} detalle />
                        <p className="flex items-baseline justify-between font-medium"><span>Total a cobrar</span><span className="text-xl tabular-nums">{soles(total)}</span></p>
                        {motivoCobro && <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800" role="status">{motivoCobro}</p>}
                        <Button block icon={ReceiptText} onClick={() => setConfirmar(true)} disabled={motivoCobro !== null}>
                            Revisar cobro
                        </Button>
                    </Card>
                </div>
            )}
            {cobros.length > 0 && (
                <Card className="mt-4 p-4">
                    <h2 className="font-semibold">Comprobantes del pedido</h2>
                    <ul className="divide-y">
                        {cobros.map((cobro) => <li key={cobro.id_venta} className="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                            <span>{cobro.serie}-{cobro.correlativo} · {soles(cobro.total_venta)} · {cobro.estado_sunat}</span>
                            {cobro.estado_sunat === 'ACEPTADO' && <a className="font-medium text-brand" href={`/ventas/${cobro.id_venta}/archivo/pdf`}>Descargar PDF</a>}
                        </li>)}
                    </ul>
                </Card>
            )}
            <DialogoConfirmarCobro open={confirmar} onClose={() => !procesando && setConfirmar(false)} onConfirmar={registrar}
                tipoComprobante={tipo} cliente={cliente} total={total} pagos={pagos.map((pago) => ({ ...pago, monto: montoDePago(pago, pagos, total) / 100 }))}
                mediosPago={pagos.map((pago) => metodoDePago(pago)?.nombre).filter(Boolean).join(', ')} metodoDePago={metodoDePago}
                onActualizarPago={actualizarPago} procesando={procesando} />
        </AppLayout>
    );
}
