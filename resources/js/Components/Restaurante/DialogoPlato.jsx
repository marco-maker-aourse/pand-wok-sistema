import { useEffect, useMemo, useState } from 'react';
import { Minus, Plus } from 'lucide-react';
import { Badge, Button, Dialog, Field, Input, cx } from '@/Components/ui';
import { soles } from './formato';

/**
 * Elige presentación, opciones (modificadores), cantidad y nota de un plato.
 * El total que se muestra es orientativo: el servidor recalcula el precio al guardar.
 */
export default function DialogoPlato({ producto, grupos, onCerrar, onAgregar }) {
    const abierto = Boolean(producto);
    const [idPrecio, setIdPrecio] = useState(null);
    const [opciones, setOpciones] = useState([]);
    const [cantidad, setCantidad] = useState(1);
    const [nota, setNota] = useState('');

    useEffect(() => {
        if (producto) {
            setIdPrecio(producto.presentaciones[0]?.id_precio ?? null);
            setOpciones([]);
            setCantidad(1);
            setNota('');
        }
    }, [producto]);

    const gruposPlato = useMemo(
        () => (producto?.grupos ?? []).map((id) => grupos.find((g) => g.id_grupo === id)).filter(Boolean),
        [producto, grupos],
    );
    const presentacion = producto?.presentaciones.find((p) => p.id_precio === idPrecio);
    const adicional = gruposPlato.flatMap((g) => g.opciones).filter((o) => opciones.includes(o.id_opcion)).reduce((suma, o) => suma + o.precio_adicional, 0);
    const pendientes = gruposPlato.filter((g) => g.opciones.filter((o) => opciones.includes(o.id_opcion)).length < g.seleccion_minima);

    const alternar = (grupo, opcion) => {
        setOpciones((actuales) => {
            const delGrupo = grupo.opciones.map((o) => o.id_opcion);
            if (actuales.includes(opcion.id_opcion)) return actuales.filter((id) => id !== opcion.id_opcion);
            if (grupo.seleccion_maxima === 1) return [...actuales.filter((id) => !delGrupo.includes(id)), opcion.id_opcion];
            const elegidas = actuales.filter((id) => delGrupo.includes(id)).length;
            return elegidas >= grupo.seleccion_maxima ? actuales : [...actuales, opcion.id_opcion];
        });
    };

    const agregar = () => {
        const nombresOpciones = gruposPlato.flatMap((g) => g.opciones).filter((o) => opciones.includes(o.id_opcion)).map((o) => o.nombre);
        onAgregar({
            id_precio: idPrecio,
            cantidad,
            opciones,
            observacion: nota.trim() || null,
            // Solo para mostrar en el borrador; el servidor no los usa.
            nombre: producto.nombre + (producto.presentaciones.length > 1 ? ` (${presentacion?.nombre})` : ''),
            detalle: nombresOpciones,
            precio_estimado: (presentacion?.precio_venta ?? 0) + adicional,
        });
    };

    return (
        <Dialog
            open={abierto}
            onClose={onCerrar}
            title={producto?.nombre}
            size="lg"
            footer={(
                <>
                    <Button variant="secondary" onClick={onCerrar}>Cancelar</Button>
                    <Button size="lg" onClick={agregar} disabled={!presentacion || pendientes.length > 0}>
                        Agregar {cantidad} · {soles(((presentacion?.precio_venta ?? 0) + adicional) * cantidad)}
                    </Button>
                </>
            )}
        >
            {producto && (
                <div className="space-y-5 p-5">
                    {producto.presentaciones.length > 1 && (
                        <fieldset>
                            <legend className="mb-2 text-sm font-medium text-slate-800">Tamaño / presentación</legend>
                            <div className="flex flex-wrap gap-2">
                                {producto.presentaciones.map((p) => (
                                    <Opcion key={p.id_precio} activa={p.id_precio === idPrecio} onClick={() => setIdPrecio(p.id_precio)}>
                                        {p.nombre} · {soles(p.precio_venta)}
                                    </Opcion>
                                ))}
                            </div>
                        </fieldset>
                    )}

                    {gruposPlato.map((grupo) => (
                        <fieldset key={grupo.id_grupo}>
                            <legend className="mb-2 flex items-center gap-2 text-sm font-medium text-slate-800">
                                {grupo.nombre}
                                {grupo.seleccion_minima > 0 ? <Badge tone="warning">Obligatorio</Badge> : <span className="text-xs font-normal text-slate-500">Opcional</span>}
                                {grupo.seleccion_maxima > 1 && <span className="text-xs font-normal text-slate-500">hasta {grupo.seleccion_maxima}</span>}
                            </legend>
                            <div className="flex flex-wrap gap-2">
                                {grupo.opciones.map((opcion) => (
                                    <Opcion key={opcion.id_opcion} activa={opciones.includes(opcion.id_opcion)} onClick={() => alternar(grupo, opcion)}>
                                        {opcion.nombre}{opcion.precio_adicional > 0 && <span className="text-slate-500"> +{soles(opcion.precio_adicional)}</span>}
                                    </Opcion>
                                ))}
                            </div>
                        </fieldset>
                    ))}

                    <div className="grid gap-4 sm:grid-cols-[auto_1fr] sm:items-end">
                        <Field label="Cantidad">
                            <div className="flex items-center gap-2">
                                <Button variant="secondary" size="lg" icon={Minus} aria-label="Menos" onClick={() => setCantidad((c) => Math.max(1, c - 1))} />
                                <span className="w-10 text-center font-mono text-xl font-semibold tabular-nums" aria-live="polite">{cantidad}</span>
                                <Button variant="secondary" size="lg" icon={Plus} aria-label="Más" onClick={() => setCantidad((c) => Math.min(99, c + 1))} />
                            </div>
                        </Field>
                        <Field label="Nota para cocina" hint="Ej.: sin cebolla, poco ají.">
                            <Input value={nota} onChange={(e) => setNota(e.target.value)} maxLength={255} />
                        </Field>
                    </div>

                    {pendientes.length > 0 && (
                        <p className="text-sm text-amber-700" role="status">Falta elegir: {pendientes.map((g) => g.nombre).join(', ')}.</p>
                    )}
                </div>
            )}
        </Dialog>
    );
}

function Opcion({ activa, onClick, children }) {
    return (
        <button
            type="button"
            aria-pressed={activa}
            onClick={onClick}
            className={cx(
                'min-h-11 rounded-xl px-4 text-sm font-medium ring-1 transition-colors',
                activa ? 'bg-brand-soft text-brand-ink ring-2 ring-brand' : 'bg-white text-slate-700 ring-slate-200 hover:bg-slate-50',
            )}
        >
            {children}
        </button>
    );
}
