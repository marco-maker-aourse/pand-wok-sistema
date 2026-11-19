import { FileClock, PackageSearch, ReceiptText } from 'lucide-react';
import { Badge, Card, CardHeader, EmptyState } from '@/Components/ui';

const moneda = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

/** Últimas ventas del período (`metrics.recentSales`). */
export function UltimasVentas({ ventas = [], pendientesSunat = 0, className }) {
    return (
        <Card className={className}>
            <CardHeader
                icon={ReceiptText}
                title="Últimas ventas"
                description="Comprobantes emitidos recientemente"
                actions={pendientesSunat > 0 && <Badge tone="warning" icon={FileClock}>{pendientesSunat} por revisar</Badge>}
            />
            {ventas.length === 0 ? (
                <EmptyState icon={ReceiptText} title="Aún no hay ventas" description="Aparecerán aquí al emitir comprobantes." />
            ) : (
                <ul className="divide-y divide-slate-100 px-5">
                    {ventas.slice(0, 6).map((venta) => (
                        <li key={venta.id} className="flex items-center justify-between gap-3 py-3">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-slate-900">{venta.customer || 'Cliente no registrado'}</p>
                                <p className="text-xs text-slate-500"><span className="font-mono">{venta.document}</span> · {venta.receipt}</p>
                            </div>
                            <span className="shrink-0 text-sm font-semibold text-slate-900 tabular-nums">{moneda.format(Number(venta.total) || 0)}</span>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

/** Productos sin existencias (`metrics.stockAlerts`). */
export function AlertasInventario({ alertas = [], className }) {
    return (
        <Card className={className}>
            <CardHeader icon={PackageSearch} title="Atención de inventario" description="Productos sin disponibilidad" />
            {alertas.length === 0 ? (
                <EmptyState icon={PackageSearch} title="Todo con existencias" description="No hay productos agotados." />
            ) : (
                <ul className="divide-y divide-slate-100 px-5">
                    {alertas.slice(0, 6).map((alerta) => (
                        <li key={alerta.id} className="flex items-center justify-between gap-3 py-3">
                            <p className="truncate text-sm font-medium text-slate-900">{alerta.name}</p>
                            <Badge tone="danger" dot>Sin stock</Badge>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
