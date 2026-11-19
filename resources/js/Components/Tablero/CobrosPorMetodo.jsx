import { CreditCard } from 'lucide-react';
import { Card, CardHeader, EmptyState } from '@/Components/ui';

const moneda = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const SERIES = ['var(--brand-serie-1)', 'var(--brand-serie-2)', 'var(--brand-serie-3)', 'var(--brand-serie-4)', 'var(--brand-serie-5)'];
const RADIO = 70;
const CIRCUNFERENCIA = 2 * Math.PI * RADIO;

/**
 * Distribución de cobros por método de pago (datos reales de `metrics.paymentMethods`).
 * Donut en tintes de la marca con el total cobrado al centro.
 */
export default function CobrosPorMetodo({ pagos = [], className }) {
    const filas = pagos.map((p) => ({ nombre: p.name, monto: Number(p.total) || 0 })).filter((p) => p.monto > 0).sort((a, b) => b.monto - a.monto);
    const total = filas.reduce((suma, p) => suma + p.monto, 0);

    let acumulado = 0;
    const segmentos = filas.map((fila, i) => {
        const fraccion = fila.monto / total;
        const segmento = { ...fila, color: SERIES[Math.min(i, SERIES.length - 1)], longitud: fraccion * CIRCUNFERENCIA, desfase: -acumulado * CIRCUNFERENCIA, porcentaje: Math.round(fraccion * 100) };
        acumulado += fraccion;
        return segmento;
    });

    return (
        <Card className={className}>
            <CardHeader icon={CreditCard} title="Cobros por método" description="Pagos completados en el período" />
            {filas.length === 0 ? (
                <EmptyState icon={CreditCard} title="Aún no hay cobros" description="Se mostrarán cuando se registren ventas." />
            ) : (
                <div className="p-5">
                    <div className="relative mx-auto aspect-square w-full max-w-[13rem]">
                        <svg viewBox="0 0 180 180" className="h-full w-full -rotate-90" role="img" aria-label={`Total cobrado ${moneda.format(total)}`}>
                            <circle cx="90" cy="90" r={RADIO} fill="none" stroke="#f1f5f9" strokeWidth="22" />
                            {segmentos.map((s) => (
                                <circle
                                    key={s.nombre}
                                    cx="90"
                                    cy="90"
                                    r={RADIO}
                                    fill="none"
                                    style={{ stroke: s.color }}
                                    strokeWidth="22"
                                    strokeDasharray={`${Math.max(s.longitud - 2, 0.1)} ${CIRCUNFERENCIA}`}
                                    strokeDashoffset={s.desfase}
                                >
                                    <title>{`${s.nombre}: ${moneda.format(s.monto)}`}</title>
                                </circle>
                            ))}
                        </svg>
                        <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                            <span className="text-xs text-slate-500">Total cobrado</span>
                            <span className="mt-0.5 text-lg font-semibold tracking-[-0.02em] text-slate-900 tabular-nums">{moneda.format(total)}</span>
                        </div>
                    </div>
                    <ul className="mt-5 space-y-2.5">
                        {segmentos.map((s) => (
                            <li key={s.nombre} className="flex items-center gap-2.5 text-sm">
                                <span className="h-2.5 w-2.5 shrink-0 rounded-[3px]" style={{ backgroundColor: s.color }} aria-hidden="true" />
                                <span className="min-w-0 flex-1 truncate text-slate-600">{s.nombre}</span>
                                <span className="text-xs text-slate-400 tabular-nums">{s.porcentaje}%</span>
                                <span className="w-24 text-right font-medium text-slate-900 tabular-nums">{moneda.format(s.monto)}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </Card>
    );
}
