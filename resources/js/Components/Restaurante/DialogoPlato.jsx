import { useEffect, useMemo, useState } from 'react';
import { Minus, Plus } from 'lucide-react';
import { Badge, Button, Dialog, Field, Input, cx } from '@/Components/ui';
import { soles } from './formato';
import Distintivo from '@/Components/Restaurante/Distintivo';

/**
 * Elige presentación, opciones (sopa del menú, acompañamientos, término…), cantidad y nota.
 *
 * Con cantidad 1 las opciones se marcan con un toque. Con más unidades cada opción lleva un
 * contador, para repartir: 2 menús → 1 sopa wantán y 1 de pollo. Las reglas (mínimo y máximo
 * por plato) y los recargos son los de la sucursal, que envía el servidor; el total que se
 * muestra es orientativo: el servidor vuelve a validar y cotizar al guardar.
 */

/** Pedidos más comunes a cocina: un toque los agrega a la nota. */
const NOTAS_RAPIDAS = [
    'Sin cebolla china', 'Sin cebolla', 'Poco sillao', 'Poco sal', 'Sin ajinomoto', 'Sin kion',
    'Picante', 'Sin ají', 'Bien cocido', 'Bien frito', 'Arroz aparte', 'Para llevar',
];

const agregarNota = (actual, texto) => {
    const partes = actual.split(',').map((p) => p.trim()).filter(Boolean);
    return partes.some((p) => p.toLowerCase() === texto.toLowerCase()) ? actual : [...partes, texto].join(', ').slice(0, 255);
};

export default function DialogoPlato({ producto, grupos, onCerrar, onAgregar }) {
    const abierto = Boolean(producto);
    const [idPrecio, setIdPrecio] = useState(null);
    const [conteos, setConteos] = useState({});
    const [cantidad, setCantidad] = useState(1);
    const [nota, setNota] = useState('');

    useEffect(() => {
        if (!producto) return;
        const conStock = producto.presentaciones.find((p) => p.stock === null || p.stock === undefined || p.stock > 0);
        setIdPrecio((conStock ?? producto.presentaciones[0])?.id_precio ?? null);
        setConteos({});
        setCantidad(1);
        setNota('');
    }, [producto]);

    // Grupos del plato con su regla efectiva (la del plato si la tiene, si no la del grupo).
    const gruposPlato = useMemo(() => (producto?.grupos ?? []).map((id) => {
        const grupo = grupos.find((g) => g.id_grupo === id);
        if (!grupo) return null;
        const regla = producto.reglas?.find((r) => r.id_grupo === id);
        return { ...grupo, minimo: regla?.seleccion_minima ?? grupo.seleccion_minima, maximo: regla?.seleccion_maxima ?? grupo.seleccion_maxima };
    }).filter(Boolean), [producto, grupos]);

    const presentacion = producto?.presentaciones.find((p) => p.id_precio === idPrecio);
    const totalEn = (mapa, grupo) => grupo.opciones.reduce((suma, o) => suma + (mapa[o.id_opcion] ?? 0), 0);
    const totalGrupo = (grupo) => totalEn(conteos, grupo);
    const recargos = gruposPlato.flatMap((g) => g.opciones).reduce((suma, o) => suma + o.precio_adicional * (conteos[o.id_opcion] ?? 0), 0);
    const total = (presentacion?.precio_venta ?? 0) * cantidad + recargos;
    const pendientes = gruposPlato.filter((g) => totalGrupo(g) < g.minimo * cantidad);
    const sinStock = presentacion && presentacion.stock !== null && presentacion.stock !== undefined && presentacion.stock < cantidad;

    // Al cambiar la cantidad, la opción única de un grupo acompaña a todas las unidades.
    const cambiarCantidad = (nueva) => {
        setConteos((actuales) => {
            const siguiente = { ...actuales };
            gruposPlato.forEach((g) => {
                const elegidas = g.opciones.filter((o) => (actuales[o.id_opcion] ?? 0) > 0);
                if (elegidas.length === 1 && actuales[elegidas[0].id_opcion] === cantidad) {
                    siguiente[elegidas[0].id_opcion] = Math.min(nueva, g.maximo * nueva);
                } else {
                    elegidas.forEach((o) => { siguiente[o.id_opcion] = Math.min(actuales[o.id_opcion], nueva); });
                }
            });
            return siguiente;
        });
        setCantidad(nueva);
    };

    const sumar = (actuales, grupo, opcion, delta) => {
        const actual = actuales[opcion.id_opcion] ?? 0;
        const nuevo = actual + delta;
        const totalOtras = totalEn(actuales, grupo) - actual;
        if (nuevo < 0 || nuevo > cantidad || totalOtras + nuevo > grupo.maximo * cantidad) return actuales;
        return { ...actuales, [opcion.id_opcion]: nuevo };
    };

    /** Un toque: con una unidad marca/desmarca (o reemplaza si el grupo admite una sola). */
    const tocar = (grupo, opcion) => {
        setConteos((actuales) => {
            const actual = actuales[opcion.id_opcion] ?? 0;
            if (cantidad === 1) {
                if (actual > 0) return { ...actuales, [opcion.id_opcion]: 0 };
                if (grupo.maximo === 1) {
                    const limpio = { ...actuales };
                    grupo.opciones.forEach((o) => { limpio[o.id_opcion] = 0; });
                    return { ...limpio, [opcion.id_opcion]: 1 };
                }
                return totalEn(actuales, grupo) >= grupo.maximo ? actuales : { ...actuales, [opcion.id_opcion]: 1 };
            }
            return sumar(actuales, grupo, opcion, 1);
        });
    };

    const agregar = () => {
        const elegidas = gruposPlato.flatMap((g) => g.opciones).filter((o) => (conteos[o.id_opcion] ?? 0) > 0);
        onAgregar({
            id_precio: idPrecio,
            cantidad,
            selecciones: elegidas.map((o) => ({ id_opcion: o.id_opcion, cantidad: conteos[o.id_opcion] })),
            id_producto: producto.id_producto,
            observacion: nota.trim() || null,
            // Solo para mostrar en el borrador; el servidor no los usa.
            nombre: producto.nombre + (producto.presentaciones.length > 1 ? ` (${presentacion?.nombre})` : ''),
            distintivo: producto.distintivo ?? null,
            detalle: elegidas.map((o) => (cantidad > 1 && conteos[o.id_opcion] !== cantidad ? `${conteos[o.id_opcion]} × ${o.nombre}` : o.nombre)),
            precio_estimado: total / cantidad,
        });
    };

    return (
        <Dialog
            open={abierto}
            onClose={onCerrar}
            title={producto && <span className="flex flex-wrap items-center gap-2">{producto.nombre}<Distintivo texto={producto.distintivo} grande /></span>}
            size="lg"
            footer={(
                <>
                    <Button variant="secondary" size="lg" onClick={onCerrar} className="hidden sm:inline-flex">Cancelar</Button>
                    <Button size="lg" onClick={agregar} disabled={!presentacion || pendientes.length > 0 || sinStock} className="min-h-14 flex-1 text-base sm:flex-none">
                        Agregar {cantidad} · {soles(total)}
                    </Button>
                </>
            )}
        >
            {producto && (
                <div className="space-y-5 p-5">
                    {producto.presentaciones.length > 1 && (
                        <fieldset>
                            <legend className="mb-2 text-sm font-medium text-slate-800">Tamaño / presentación</legend>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                {producto.presentaciones.map((p) => {
                                    const agotada = p.stock !== null && p.stock !== undefined && p.stock <= 0;
                                    return (
                                        <Opcion key={p.id_precio} activa={p.id_precio === idPrecio} disabled={agotada} onClick={() => setIdPrecio(p.id_precio)}>
                                            <span className="block font-semibold">{p.nombre}</span>
                                            <span className="block font-mono text-xs tabular-nums">{soles(p.precio_venta)}</span>
                                            {p.stock !== null && p.stock !== undefined && (
                                                <span className={cx('block text-xs', agotada ? 'text-rose-600' : 'text-slate-500')}>{agotada ? 'Sin stock' : `${p.stock} disp.`}</span>
                                            )}
                                        </Opcion>
                                    );
                                })}
                            </div>
                        </fieldset>
                    )}

                    {gruposPlato.map((grupo) => {
                        const elegidas = totalGrupo(grupo);
                        const conRecargo = grupo.opciones.some((o) => o.precio_adicional > 0);
                        return (
                            <fieldset key={grupo.id_grupo}>
                                <legend className="mb-2 flex flex-wrap items-center gap-2 text-sm font-medium text-slate-800">
                                    {grupo.nombre}
                                    {grupo.minimo > 0 ? <Badge tone="warning">Obligatorio</Badge> : <span className="text-xs font-normal text-slate-500">Opcional</span>}
                                    <span className="text-xs font-normal tabular-nums text-slate-500">
                                        {cantidad > 1 ? `${elegidas} de ${grupo.maximo * cantidad}` : grupo.maximo > 1 ? `hasta ${grupo.maximo}` : ''}
                                    </span>
                                </legend>
                                {grupo.opciones.length === 0 ? (
                                    <p className="text-sm text-slate-500">No hay alternativas disponibles hoy en esta sucursal.</p>
                                ) : (
                                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        {grupo.opciones.map((opcion) => {
                                            const n = conteos[opcion.id_opcion] ?? 0;
                                            const precio = opcion.precio_adicional > 0
                                                ? <span className="font-mono text-xs tabular-nums text-slate-500">+{soles(opcion.precio_adicional)}</span>
                                                : (conRecargo || opcion.id_producto) && <span className="block text-xs text-emerald-700">Incluido en el precio</span>;
                                            if (cantidad === 1) {
                                                return (
                                                    <Opcion key={opcion.id_opcion} activa={n > 0} onClick={() => tocar(grupo, opcion)}>
                                                        <span className="block">{opcion.nombre}</span>{precio}
                                                    </Opcion>
                                                );
                                            }
                                            return (
                                                <div key={opcion.id_opcion} className={cx('flex items-center gap-1 rounded-xl p-1.5 ring-1', n > 0 ? 'bg-brand-soft ring-2 ring-brand' : 'bg-white ring-slate-200')}>
                                                    <button type="button" className="min-h-10 flex-1 px-2 text-left text-sm font-medium" onClick={() => tocar(grupo, opcion)}>
                                                        <span className="block">{opcion.nombre}</span>{precio}
                                                    </button>
                                                    {n > 0 && (
                                                        <>
                                                            <button type="button" aria-label={`Quitar ${opcion.nombre}`} className="grid h-9 w-9 place-items-center rounded-lg bg-white ring-1 ring-slate-200" onClick={() => setConteos((a) => sumar(a, grupo, opcion, -1))}>
                                                                <Minus className="h-4 w-4" aria-hidden="true" />
                                                            </button>
                                                            <span className="w-6 text-center font-mono font-semibold tabular-nums" aria-live="polite">{n}</span>
                                                        </>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </fieldset>
                        );
                    })}

                    <div className="grid gap-4 sm:grid-cols-[auto_1fr] sm:items-end">
                        <Field label="Cantidad">
                            <div className="flex items-center gap-2">
                                <Button variant="secondary" size="lg" icon={Minus} aria-label="Menos" onClick={() => cambiarCantidad(Math.max(1, cantidad - 1))} />
                                <span className="w-10 text-center font-mono text-xl font-semibold tabular-nums" aria-live="polite">{cantidad}</span>
                                <Button variant="secondary" size="lg" icon={Plus} aria-label="Más" onClick={() => cambiarCantidad(Math.min(99, cantidad + 1))} />
                            </div>
                        </Field>
                        <Field label="Nota para cocina">
                            <Input value={nota} onChange={(e) => setNota(e.target.value)} maxLength={255} placeholder="Escribe o toca una sugerencia" />
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {NOTAS_RAPIDAS.map((texto) => (
                                    <button key={texto} type="button" onClick={() => setNota(agregarNota(nota, texto))}
                                        className="h-9 rounded-full bg-slate-100 px-3 text-sm text-slate-700 ring-1 ring-slate-200 hover:bg-slate-200 active:scale-95">
                                        {texto}
                                    </button>
                                ))}
                            </div>
                        </Field>
                    </div>

                    {pendientes.length > 0 && (
                        <p className="text-sm text-amber-700" role="status">
                            Falta elegir: {pendientes.map((g) => `${g.nombre}${cantidad > 1 ? ` (${g.minimo * cantidad - totalGrupo(g)})` : ''}`).join(', ')}.
                        </p>
                    )}
                    {sinStock && <p className="text-sm text-rose-700" role="status">No hay stock suficiente de {presentacion.nombre}.</p>}
                </div>
            )}
        </Dialog>
    );
}

function Opcion({ activa, disabled = false, onClick, children }) {
    return (
        <button
            type="button"
            aria-pressed={activa}
            disabled={disabled}
            onClick={onClick}
            className={cx(
                'min-h-12 rounded-xl px-3 py-2 text-left text-sm font-medium ring-1 transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                activa ? 'bg-brand-soft text-brand-ink ring-2 ring-brand' : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50',
            )}
        >
            {children}
        </button>
    );
}
