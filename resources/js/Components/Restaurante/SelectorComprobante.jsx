import { Building2, FileMinus, ReceiptText } from 'lucide-react';
import { cx } from '@/Components/ui';

/** Tipos que acepta el cobro (CobrarPedidoRequest / PosRequest). */
export const COMPROBANTES = [
    { valor: 'NOTA_VENTA', etiqueta: 'Nota de venta', detalle: 'No va a SUNAT', icono: FileMinus },
    { valor: 'BOLETA', etiqueta: 'Boleta', detalle: 'DNI o público', icono: ReceiptText },
    { valor: 'FACTURA', etiqueta: 'Factura', detalle: 'Requiere RUC', icono: Building2 },
];

/** Elección del comprobante con su consecuencia visible en cada opción. */
export default function SelectorComprobante({ valor, onCambiar, disabled = false }) {
    return (
        <div className="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Comprobante">
            {COMPROBANTES.map(({ valor: opcion, etiqueta, detalle, icono: Icono }) => {
                const activo = valor === opcion;
                return (
                    <button
                        key={opcion}
                        type="button"
                        role="radio"
                        aria-checked={activo}
                        disabled={disabled}
                        onClick={() => onCambiar(opcion)}
                        className={cx(
                            'flex min-h-16 flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-2 text-center ring-1 transition-colors disabled:cursor-not-allowed disabled:opacity-60',
                            activo ? 'bg-brand-soft text-brand-ink ring-2 ring-brand' : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50',
                        )}
                    >
                        <Icono className="h-4 w-4" aria-hidden="true" />
                        <span className="text-sm leading-tight font-medium">{etiqueta}</span>
                        <span className={cx('text-[0.6875rem] leading-tight', activo ? 'text-brand-ink/80' : 'text-slate-500')}>{detalle}</span>
                    </button>
                );
            })}
        </div>
    );
}

/** Explicación del comprobante elegido; `sinDocumento` = cliente «Público en general». */
export function explicarComprobante(tipo, sinDocumento) {
    if (tipo === 'NOTA_VENTA') return 'Documento interno sin valor tributario: no se envía a SUNAT. Útil cuando el cliente no da su DNI.';
    if (tipo === 'FACTURA') return 'La factura requiere un cliente con RUC y se envía a SUNAT.';
    return sinDocumento
        ? 'Boleta electrónica a «Público en general» (se envía a SUNAT). Si el cliente no quiere comprobante, usa nota de venta.'
        : 'Boleta electrónica: se envía a SUNAT.';
}
