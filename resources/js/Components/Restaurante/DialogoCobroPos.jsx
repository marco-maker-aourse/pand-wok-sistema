import { useEffect, useState } from 'react';
import { ReceiptText } from 'lucide-react';
import { Button, Dialog, Field, Input, cx } from '@/Components/ui';
import PanelPagos from '@/Components/Ventas/PanelPagos';
import ClienteCobro from './ClienteCobro';
import { soles } from './formato';

const pagoVacio = (id = '') => ({ id_metodo: id, monto: '', monto_recibido: '', nro_referencia: '' });

/**
 * Comprobante y pagos del POS. `total` es el importe por cobrar calculado con los mismos
 * precios del servidor; el servidor lo vuelve a calcular y rechaza si no coincide.
 */
export default function DialogoCobroPos({ abierto, onCerrar, total, metodosPago, clienteInicial, puedeDescontar, procesando, error, onCobrar }) {
    const [tipo, setTipo] = useState('BOLETA');
    const [cliente, setCliente] = useState(clienteInicial);
    const [descuento, setDescuento] = useState('');
    const [pagos, setPagos] = useState(() => [pagoVacio(metodosPago[0]?.id_metodo)]);

    useEffect(() => {
        if (abierto) {
            setTipo('BOLETA');
            setCliente(clienteInicial);
            setDescuento('');
            setPagos([pagoVacio(metodosPago[0]?.id_metodo)]);
        }
    }, [abierto]); // eslint-disable-line react-hooks/exhaustive-deps

    const totalFinal = Math.max(0, (Math.round(total * 100) - Math.round(Number(descuento || 0) * 100)) / 100);
    const montoPago = (pago) => (pago.monto === '' && pagos.length === 1 ? totalFinal : Number(pago.monto || 0));
    const saldo = Number((totalFinal - pagos.reduce((suma, pago) => suma + montoPago(pago), 0)).toFixed(2));
    const metodoDePago = (pago) => metodosPago.find((metodo) => metodo.id_metodo === Number(pago.id_metodo));
    const actualizarPago = (indice, campo, valor) => setPagos((actuales) => actuales.map((pago, i) => (i === indice ? { ...pago, [campo]: valor } : pago)));
    const seleccionarMetodo = (indice, metodo) => setPagos((actuales) => actuales.map((pago, i) => (i === indice ? { ...pagoVacio(metodo.id_metodo), monto: pago.monto } : pago)));
    const agregarPago = () => {
        const libre = metodosPago.find((metodo) => !pagos.some((pago) => Number(pago.id_metodo) === metodo.id_metodo));
        if (libre) setPagos([...pagos, pagoVacio(libre.id_metodo)]);
    };
    const retirarPago = (indice) => setPagos((actuales) => actuales.filter((_, i) => i !== indice));

    const valido = cliente && totalFinal > 0 && saldo === 0 && pagos.every((pago) => pago.id_metodo && montoPago(pago) > 0);

    const cobrar = () => onCobrar({
        tipo_comprobante: tipo,
        id_cliente: cliente?.id_cliente ?? null,
        descuento_global: Number(descuento || 0).toFixed(2),
        pagos: pagos.map((pago) => ({
            id_metodo: pago.id_metodo,
            monto: montoPago(pago).toFixed(2),
            monto_recibido: pago.monto_recibido || null,
            nro_referencia: pago.nro_referencia || null,
        })),
    });

    return (
        <Dialog
            open={abierto}
            onClose={procesando ? () => {} : onCerrar}
            title="Cobrar"
            size="lg"
            footer={(
                <>
                    <Button variant="secondary" onClick={onCerrar} disabled={procesando}>Volver</Button>
                    <Button size="lg" icon={ReceiptText} onClick={cobrar} loading={procesando} disabled={!valido}>Cobrar {soles(totalFinal)}</Button>
                </>
            )}
        >
            <div className="space-y-4 p-5">
                <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Comprobante">
                    {[['BOLETA', 'Boleta'], ['FACTURA', 'Factura']].map(([valor, etiqueta]) => (
                        <button
                            key={valor}
                            type="button"
                            role="radio"
                            aria-checked={tipo === valor}
                            onClick={() => setTipo(valor)}
                            className={cx('h-11 rounded-xl text-sm font-medium ring-1', tipo === valor ? 'bg-brand-soft text-brand-ink ring-2 ring-brand' : 'ring-slate-200 hover:bg-slate-50')}
                        >
                            {etiqueta}
                        </button>
                    ))}
                </div>
                <ClienteCobro cliente={cliente} cambiar={setCliente} />
                {tipo === 'FACTURA' && <p className="text-xs text-slate-500">La factura requiere un cliente con RUC.</p>}
                {puedeDescontar && (
                    <Field label="Descuento (S/)">
                        <Input type="number" min={0} step={0.01} inputMode="decimal" value={descuento} onChange={(e) => setDescuento(e.target.value)} />
                    </Field>
                )}
                <PanelPagos
                    pagos={pagos}
                    metodosPago={metodosPago}
                    totalVenta={totalFinal}
                    saldoPendiente={saldo}
                    metodoDePago={metodoDePago}
                    onAgregar={agregarPago}
                    onSeleccionarMetodo={seleccionarMetodo}
                    onActualizar={actualizarPago}
                    onQuitar={retirarPago}
                />
                <p className="flex items-baseline justify-between font-medium">
                    <span>Total a cobrar</span>
                    <span className="font-mono text-2xl tabular-nums">{soles(totalFinal)}</span>
                </p>
                {error && <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700" role="alert">{error}</p>}
            </div>
        </Dialog>
    );
}
