import { AlertTriangle, ArrowDownRight, ArrowUpRight, Boxes, CircleDollarSign, TrendingUp } from 'lucide-react';
import { Stat, cx } from '@/Components/ui';

const moneda = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const plural = (n, singular, pluralTexto = `${singular}s`) => `${n} ${n === 1 ? singular : pluralTexto}`;

/** Variación real respecto al período anterior (`insights.salesVariation`); sin dato no se muestra. */
function Variacion({ valor }) {
    if (valor === null || valor === undefined) return null;
    const sube = valor >= 0;
    const Icono = sube ? ArrowUpRight : ArrowDownRight;
    return (
        <span className={cx('inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-xs font-semibold tabular-nums', sube ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700')}>
            <Icono className="h-3.5 w-3.5" aria-hidden="true" />
            {sube ? '+' : ''}{valor}%
            <span className="sr-only"> respecto al período anterior</span>
        </span>
    );
}

/** Indicadores principales del período, con datos de `metrics.summary` e `insights`. */
export default function IndicadoresOperativos({ resumen, etiquetaPeriodo, insights = {} }) {
    const ventas = Number(resumen.salesTotal) || 0;
    const comprobantes = Number(resumen.salesCount) || 0;
    const ticket = Number(resumen.averageTicket) || 0;
    const compras = Number(resumen.purchasesTotal) || 0;
    const ingresos = Number(resumen.purchasesCount) || 0;
    const sinStock = Number(resumen.zeroStockCount) || 0;
    const clientes = Number(insights.uniqueCustomers) || 0;

    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Stat
                destacado
                label={`Ventas · ${etiquetaPeriodo}`}
                value={moneda.format(ventas)}
                extra={<Variacion valor={insights.salesVariation} />}
                hint={`${plural(comprobantes, 'comprobante')} ${comprobantes === 1 ? 'emitido' : 'emitidos'}`}
                icon={CircleDollarSign}
            />
            <Stat label="Ticket promedio" value={moneda.format(ticket)} hint={`${plural(clientes, 'cliente atendido', 'clientes atendidos')}`} icon={TrendingUp} />
            <Stat label="Compras e ingresos" value={moneda.format(compras)} hint={`${plural(ingresos, 'ingreso')} ${ingresos === 1 ? 'registrado' : 'registrados'}`} icon={Boxes} />
            <Stat
                label="Sin existencias"
                value={sinStock}
                hint={sinStock > 0 ? 'Artículos por reponer' : 'Stock en niveles óptimos'}
                icon={AlertTriangle}
                tone={sinStock > 0 ? 'danger' : 'default'}
                iconTone={sinStock > 0 ? 'danger' : 'neutral'}
            />
        </div>
    );
}
