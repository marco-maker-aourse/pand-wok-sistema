import { Building, Coins, CreditCard, Smartphone } from 'lucide-react';
import { Button, Dialog, Field, Input } from '@/Components/ui';
import { formatoMoneda } from './formato';

function IconoPago({ nombre }) {
    const n = (nombre || '').toLowerCase();
    const Icon = n.includes('yape') || n.includes('plin') ? Smartphone : n.includes('tarjeta') ? CreditCard : n.includes('transferencia') ? Building : Coins;
    return <Icon className="h-4 w-4 text-slate-500" aria-hidden="true" />;
}

function DetallePago({ pago, metodo, onActualizar }) {
    const esEfectivo = metodo?.tipo === 'EFECTIVO';
    const esBilleteraDirecta = metodo?.tipo === 'BILLETERA_DIRECTA';
    const esTerminalFisico = metodo?.tipo === 'PASARELA';
    const vuelto = Math.max(0, (Number(pago.monto_recibido) || 0) - (Number(pago.monto) || 0));

    return (
        <div className="rounded-2xl bg-white p-3 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
            <div className="mb-2 flex items-center justify-between text-sm font-medium text-slate-900">
                <span className="flex items-center gap-2"><IconoPago nombre={metodo?.nombre} />{metodo?.nombre}</span>
                <span className="tabular-nums">{formatoMoneda.format(Number(pago.monto) || 0)}</span>
            </div>
            {esEfectivo ? (
                <Field label="Efectivo recibido">
                    <Input
                        type="number"
                        min="0"
                        step="0.01"
                        className="font-mono"
                        value={pago.monto_recibido}
                        onWheel={(e) => e.target.blur()}
                        onChange={(e) => onActualizar('monto_recibido', e.target.value)}
                        placeholder={Number(pago.monto || 0).toFixed(2)}
                    />
                </Field>
            ) : (
                <Field label={metodo?.requiere_referencia ? 'Número de operación' : 'Referencia (opcional)'} required={Boolean(metodo?.requiere_referencia)}>
                    <Input
                        type="text"
                        maxLength={100}
                        className="font-mono"
                        value={pago.nro_referencia}
                        onChange={(e) => onActualizar('nro_referencia', e.target.value)}
                        placeholder={esTerminalFisico ? 'Voucher del terminal' : 'Código de operación'}
                    />
                </Field>
            )}
            {esEfectivo && <p className="mt-1.5 text-right text-sm font-medium text-emerald-700 tabular-nums">Vuelto: {formatoMoneda.format(vuelto)}</p>}
            {esBilleteraDirecta && <p className="mt-1.5 text-xs text-amber-800">Verifica que el pago se haya recibido antes de confirmar la venta.</p>}
            {esTerminalFisico && <p className="mt-1.5 text-xs text-amber-800">Confirma que el terminal aprobó la operación antes de continuar.</p>}
        </div>
    );
}

/** Revisión final del cobro: comprobante, cliente, medios de pago y vuelto. */
const NOMBRE_COMPROBANTE = {
    BOLETA: 'Boleta electrónica',
    FACTURA: 'Factura electrónica',
    NOTA_VENTA: 'Nota de venta (sin valor tributario, no va a SUNAT)',
};

export default function DialogoConfirmarCobro({ open, onClose, onConfirmar, tipoComprobante, cliente, mediosPago, total, pagos, metodoDePago, onActualizarPago, procesando = false }) {
    return (
        <Dialog
            open={open}
            onClose={onClose}
            title="Confirmar cobro"
            size="md"
            footer={
                <>
                    <Button variant="secondary" onClick={onClose} disabled={procesando}>Cancelar</Button>
                    <Button onClick={() => onConfirmar()} loading={procesando}>Confirmar y emitir comprobante</Button>
                </>
            }
        >
            <div className="space-y-4 p-5">
                <dl className="space-y-1.5 text-sm">
                    <div className="flex justify-between gap-4"><dt className="text-slate-500">Comprobante</dt><dd className="text-right font-medium text-slate-900">{NOMBRE_COMPROBANTE[tipoComprobante] ?? tipoComprobante}</dd></div>
                    <div className="flex justify-between gap-4"><dt className="text-slate-500">Cliente</dt><dd className="max-w-[60%] truncate font-medium text-slate-900">{cliente?.nombre_razon_social}</dd></div>
                    <div className="flex justify-between gap-4"><dt className="text-slate-500">Medios de pago</dt><dd className="text-right font-medium text-slate-900">{mediosPago}</dd></div>
                </dl>
                <div className="flex items-baseline justify-between rounded-lg bg-brand-soft px-4 py-3">
                    <span className="text-sm font-medium text-brand-ink">Total a cobrar</span>
                    <span className="text-2xl font-semibold text-slate-900 tabular-nums">{formatoMoneda.format(total)}</span>
                </div>
                <div className="space-y-2">
                    {pagos.map((pago, indice) => (
                        <DetallePago key={`${pago.id_metodo}-${indice}`} pago={pago} metodo={metodoDePago(pago)} onActualizar={(campo, valor) => onActualizarPago(indice, campo, valor)} />
                    ))}
                </div>
            </div>
        </Dialog>
    );
}
