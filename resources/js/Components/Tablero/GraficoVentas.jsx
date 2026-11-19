import { useId, useMemo, useState } from 'react';
import { TrendingUp } from 'lucide-react';
import { Card, CardHeader, EmptyState } from '@/Components/ui';

const moneda = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const compacto = new Intl.NumberFormat('es-PE', { notation: 'compact', maximumFractionDigits: 1 });

const ANCHO = 640;
const ALTO = 220;
const MARGEN = { arriba: 16, abajo: 8 };

/** Curva suave (Catmull-Rom → Bézier) que pasa por todos los puntos. */
function trazoSuave(puntos) {
    if (puntos.length < 2) return '';
    let d = `M${puntos[0].x},${puntos[0].y}`;
    for (let i = 0; i < puntos.length - 1; i += 1) {
        const p0 = puntos[i - 1] ?? puntos[i];
        const p1 = puntos[i];
        const p2 = puntos[i + 1];
        const p3 = puntos[i + 2] ?? p2;
        const c1x = p1.x + (p2.x - p0.x) / 6;
        const c1y = p1.y + (p2.y - p0.y) / 6;
        const c2x = p2.x - (p3.x - p1.x) / 6;
        const c2y = p2.y - (p3.y - p1.y) / 6;
        d += ` C${c1x},${Math.min(c1y, ALTO - MARGEN.abajo)} ${c2x},${Math.min(c2y, ALTO - MARGEN.abajo)} ${p2.x},${p2.y}`;
    }
    return d;
}

/** Máximo "redondo" para las guías del eje (1, 2, 2.5 o 5 por potencia de 10). */
function maximoEje(valor) {
    if (valor <= 0) return 1;
    const potencia = 10 ** Math.floor(Math.log10(valor));
    const paso = [1, 2, 2.5, 5, 10].find((p) => p * potencia >= valor);
    return paso * potencia;
}

/**
 * Ingresos por hora (hoy) o por día (otros períodos). Datos: `metrics.hourlySales`
 * del backend ([{ label, total }]). No interpola ni completa valores.
 */
export default function GraficoVentas({ datos = [], esDiario = false, etiquetaPeriodo, className }) {
    const id = useId().replace(/:/g, '');
    const [activo, setActivo] = useState(null);

    const serie = useMemo(() => datos.map((d) => ({ etiqueta: d.label, total: Number(d.total) || 0 })), [datos]);
    const hayVentas = serie.some((d) => d.total > 0);
    const tope = maximoEje(Math.max(...serie.map((d) => d.total), 0));

    const puntos = useMemo(() => {
        const n = Math.max(serie.length - 1, 1);
        return serie.map((d, i) => ({
            x: (i / n) * ANCHO,
            y: MARGEN.arriba + (1 - d.total / tope) * (ALTO - MARGEN.arriba - MARGEN.abajo),
        }));
    }, [serie, tope]);

    const linea = trazoSuave(puntos);
    const area = puntos.length > 1 ? `${linea} L${ANCHO},${ALTO} L0,${ALTO} Z` : '';
    const guias = [0, 0.25, 0.5, 0.75, 1];
    const pasoEtiquetas = Math.ceil(serie.length / 8);
    const punto = activo !== null ? puntos[activo] : null;

    const alMover = (event) => {
        const caja = event.currentTarget.getBoundingClientRect();
        const relativo = (event.clientX - caja.left) / caja.width;
        setActivo(Math.min(serie.length - 1, Math.max(0, Math.round(relativo * (serie.length - 1)))));
    };

    return (
        <Card className={className}>
            <CardHeader
                icon={TrendingUp}
                title={esDiario ? 'Ingresos por día' : 'Ingresos por hora'}
                description={esDiario ? `Ventas emitidas · ${etiquetaPeriodo}` : 'Ventas emitidas durante el día'}
            />
            {!hayVentas ? (
                <EmptyState icon={TrendingUp} title="Sin ventas en este período" description="La curva aparecerá cuando se registren ventas." />
            ) : (
                <div className="flex gap-3 px-5 pt-4 pb-5">
                    <div className="flex h-[220px] flex-col justify-between pb-1 text-right text-[0.6875rem] text-slate-400 tabular-nums" aria-hidden="true">
                        {[...guias].reverse().map((g) => <span key={g}>{compacto.format(tope * g)}</span>)}
                    </div>
                    <div className="min-w-0 flex-1">
                        <div
                            className="relative h-[220px]"
                            onMouseMove={alMover}
                            onMouseLeave={() => setActivo(null)}
                            role="img"
                            aria-label={`Ingresos ${esDiario ? 'por día' : 'por hora'}: máximo ${moneda.format(Math.max(...serie.map((d) => d.total)))}`}
                        >
                            <svg viewBox={`0 0 ${ANCHO} ${ALTO}`} preserveAspectRatio="none" className="absolute inset-0 h-full w-full overflow-visible">
                                <defs>
                                    <linearGradient id={`area-${id}`} x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" style={{ stopColor: 'var(--brand-primary)', stopOpacity: 0.22 }} />
                                        <stop offset="100%" style={{ stopColor: 'var(--brand-primary)', stopOpacity: 0 }} />
                                    </linearGradient>
                                </defs>
                                {guias.map((g) => (
                                    <line key={g} x1="0" x2={ANCHO} y1={MARGEN.arriba + (1 - g) * (ALTO - MARGEN.arriba - MARGEN.abajo)} y2={MARGEN.arriba + (1 - g) * (ALTO - MARGEN.arriba - MARGEN.abajo)} stroke="#e2e8f0" strokeDasharray="4 4" vectorEffect="non-scaling-stroke" />
                                ))}
                                {punto && <rect x={punto.x - ANCHO / serie.length / 2} y="0" width={ANCHO / serie.length} height={ALTO} style={{ fill: 'var(--brand-soft)' }} />}
                                <path d={area} fill={`url(#area-${id})`} />
                                <path d={linea} fill="none" style={{ stroke: 'var(--brand-primary)' }} strokeWidth="2.5" strokeLinecap="round" vectorEffect="non-scaling-stroke" />
                            </svg>
                            {punto && (
                                <>
                                    <span className="pointer-events-none absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-brand shadow" style={{ left: `${(punto.x / ANCHO) * 100}%`, top: `${(punto.y / ALTO) * 100}%` }} />
                                    <div className="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-lg bg-white px-2.5 py-1.5 text-center shadow-[var(--shadow-elevado)] ring-1 ring-slate-900/5" style={{ left: `${(punto.x / ANCHO) * 100}%`, top: `calc(${(punto.y / ALTO) * 100}% - 10px)` }}>
                                        <p className="text-[0.6875rem] text-slate-500">{serie[activo].etiqueta}</p>
                                        <p className="text-sm font-semibold text-slate-900 tabular-nums">{moneda.format(serie[activo].total)}</p>
                                    </div>
                                </>
                            )}
                        </div>
                        <div className="relative mt-2 h-4 text-[0.6875rem] whitespace-nowrap text-slate-400" aria-hidden="true">
                            {serie.map((d, i) => (i % pasoEtiquetas === 0 || i === serie.length - 1) && (
                                <span
                                    key={`${d.etiqueta}-${i}`}
                                    className="absolute"
                                    style={{ left: `${(puntos[i].x / ANCHO) * 100}%`, transform: `translateX(${i === 0 ? '0' : i === serie.length - 1 ? '-100%' : '-50%'})` }}
                                >
                                    {d.etiqueta}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </Card>
    );
}
