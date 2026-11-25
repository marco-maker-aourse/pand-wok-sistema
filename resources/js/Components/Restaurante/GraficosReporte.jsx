/**
 * Gráficos del reporte del restaurante. Una sola serie por gráfico, en el color de la marca
 * (magnitud, no identidad): no hace falta leyenda y el título nombra la serie. Cada barra
 * tiene su valor al pasar el mouse o al enfocarla con el teclado, y una etiqueta accesible.
 */

/** Columnas verticales (tiempo en el eje X): ventas por día u horas pico. */
export function Columnas({ datos, etiqueta, valor, formato, formatoEje = formato, descripcion, alto = 176 }) {
    const maximo = Math.max(...datos.map(valor), 0);
    if (!datos.length || maximo <= 0) {
        return <p className="py-10 text-center text-sm text-slate-500">Sin ventas en el período.</p>;
    }
    const mitad = maximo / 2;
    // Limita los rótulos y conserva su posición incluso cuando las columnas son estrechas.
    const intervalos = Math.min(5, datos.length - 1);
    const indices = Array.from({ length: intervalos + 1 }, (_, i) =>
        intervalos ? Math.round(i * (datos.length - 1) / intervalos) : 0);

    return (
        <div className="relative pl-12" role="group" aria-label={descripcion}>
            {/* Guías: máximo y mitad, recesivas. */}
            {[maximo, mitad].map((guia) => (
                <div key={guia} className="pointer-events-none absolute right-0 left-12 border-t border-dashed border-slate-200" style={{ top: `${(1 - guia / maximo) * alto}px` }}>
                    <span className="absolute -top-2 -left-12 w-11 truncate text-right text-[0.6875rem] text-slate-400 tabular-nums">{formatoEje(guia)}</span>
                </div>
            ))}
            <div className="relative flex items-end gap-[2px] border-b border-slate-300" style={{ height: alto }}>
                {datos.map((fila, i) => {
                    const v = valor(fila);
                    const posicion = (i + 0.5) / datos.length;
                    const alineacion = posicion < 0.25 ? 'left-0' : posicion > 0.75 ? 'right-0' : 'left-1/2 -translate-x-1/2';
                    return (
                        <div key={i} tabIndex={0} aria-label={`${etiqueta(fila)}: ${formato(v)}`}
                            className="group relative flex h-full min-w-0 flex-1 items-end outline-none">
                            <div className="w-full rounded-t-[4px] bg-brand transition-[filter] group-hover:brightness-110 group-focus-visible:ring-2 group-focus-visible:ring-brand-border"
                                style={{ height: v > 0 ? `max(2px, ${(v / maximo) * 100}%)` : 0 }} />
                            <span role="tooltip" className={`pointer-events-none absolute bottom-full z-10 mb-1 hidden rounded-md bg-slate-900 px-2 py-1 text-xs whitespace-nowrap text-white shadow-lg group-hover:block group-focus-visible:block ${alineacion}`}>
                                <span className="block text-slate-300">{etiqueta(fila)}</span>
                                <span className="font-semibold tabular-nums">{formato(v)}</span>
                            </span>
                        </div>
                    );
                })}
            </div>
            <div className="relative mt-1.5 h-4" aria-hidden="true">
                {indices.map((i) => (
                    <span key={i} className="absolute -translate-x-1/2 whitespace-nowrap text-[0.6875rem] text-slate-600"
                        style={{ left: `${Math.min(94, Math.max(6, (i + 0.5) * 100 / datos.length))}%` }}>
                        {etiqueta(datos[i], true)}
                    </span>
                ))}
            </div>
        </div>
    );
}

/**
 * Barras horizontales con nombre y valor rotulados (categorías, tipos de pedido, platos).
 * `detalle` agrega un dato secundario bajo el nombre.
 */
export function Barras({ filas, nombre, valor, formato, detalle, vacio = 'Sin ventas en el período.' }) {
    const maximo = Math.max(...filas.map(valor), 0);
    if (!filas.length || maximo <= 0) return <p className="py-6 text-center text-sm text-slate-500">{vacio}</p>;

    return (
        <ul className="space-y-3">
            {filas.map((fila, i) => (
                <li key={i}>
                    <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
                        <span className="min-w-0 break-words text-slate-800">{nombre(fila)}</span>
                        <span className="shrink-0 font-medium text-slate-900 tabular-nums">{formato(valor(fila))}</span>
                    </div>
                    <div className="h-2 rounded-full bg-slate-100">
                        <div className="h-2 rounded-full bg-brand" style={{ width: `${Math.max(0, (valor(fila) / maximo) * 100)}%` }} />
                    </div>
                    {detalle && <p className="mt-1 text-xs text-slate-500">{detalle(fila)}</p>}
                </li>
            ))}
        </ul>
    );
}
