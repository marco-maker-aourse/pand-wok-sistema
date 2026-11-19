import { useRef, useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, Field, Input, toast } from '@/Components/ui';
import { nuevaClave, primerError, soles } from '@/Components/Restaurante/formato';

const cantidad = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 4 }).format;
const estilo = 'w-full rounded-lg border-slate-300 text-sm';
const nombre = (item) => `${item.nombre} · ${item.presentacion}`;

function SelectorInsumo({ insumos, valor, cambiar }) {
    return <select required className={estilo} value={valor} onChange={(e) => cambiar(e.target.value)}>
        <option value="">Selecciona un insumo</option>
        {insumos.map((item) => <option key={item.id_precio} value={item.id_precio}>{nombre(item)}</option>)}
    </select>;
}

function EditarReceta({ plato, receta, insumos }) {
    const form = useForm({ ingredientes: receta.map((linea) => ({ ...linea, clave: nuevaClave() })) });
    const lineas = form.data.ingredientes;
    const cambiar = (clave, valores) => form.setData('ingredientes', lineas.map((linea) => linea.clave === clave ? { ...linea, ...valores } : linea));
    const costos = new Map(insumos.map((item) => [Number(item.id_precio), item]));
    const incompleta = lineas.some((linea) => costos.get(Number(linea.id_precio_insumo))?.costo == null);
    const costo = lineas.reduce((total, linea) => {
        const item = costos.get(Number(linea.id_precio_insumo));
        return total + (item?.costo ?? 0) * (item?.factor ?? 0) * Number(linea.cantidad || 0);
    }, 0);
    const enviar = (evento) => {
        evento.preventDefault();
        form.transform(({ ingredientes }) => ({ ingredientes: ingredientes.map(({ id_precio_insumo, cantidad }) => ({ id_precio_insumo, cantidad })) }));
        form.put(`/recetas/${plato}`, { preserveScroll: true, onError: (errores) => toast.error('No se guardó la receta', primerError(errores)) });
    };

    return (
        <form onSubmit={enviar} className="space-y-3">
            {lineas.map((linea) => {
                const insumo = costos.get(Number(linea.id_precio_insumo));
                return (
                    <div key={linea.clave} className="grid gap-2 rounded-lg border p-3 sm:grid-cols-[minmax(0,1fr)_8rem_auto] sm:items-start">
                        <Field label="Insumo"><SelectorInsumo insumos={insumos} valor={linea.id_precio_insumo} cambiar={(id_precio_insumo) => cambiar(linea.clave, { id_precio_insumo })} /></Field>
                        <Field label="Cantidad"><Input required type="number" min={0.0001} step={0.0001} max={9999} value={linea.cantidad} onChange={(e) => cambiar(linea.clave, { cantidad: e.target.value })} /></Field>
                        <Button className="self-end" variant="secondary" onClick={() => form.setData('ingredientes', lineas.filter((otra) => otra.clave !== linea.clave))}>Quitar</Button>
                        {insumo && <p className="text-xs text-slate-600 sm:col-span-3">{cantidad((linea.cantidad || 0) * insumo.factor)} {insumo.unidad ?? 'unidades base'} · Stock {cantidad(insumo.stock)}</p>}
                    </div>
                );
            })}
            {!lineas.length && <p className="text-sm text-slate-600">Este plato está sin receta.</p>}
            <div className="flex flex-wrap items-center gap-2">
                <Button variant="secondary" disabled={!insumos.length} onClick={() => form.setData('ingredientes', [...lineas, { clave: nuevaClave(), id_precio_insumo: '', cantidad: '' }])}>Añadir insumo</Button>
                <Button type="submit" loading={form.processing}>Guardar receta</Button>
            </div>
            {lineas.length > 0 && <p className="text-sm font-medium">{incompleta ? 'Costo estimado pendiente de existencias valorizadas.' : `Costo estimado por presentación: ${soles(costo)}`}</p>}
        </form>
    );
}

function RegistrarMerma({ insumos, mermas }) {
    const form = useForm({ id_precio: '', cantidad: '', motivo: '' });
    const intento = useRef(null);
    const insumo = insumos.find((item) => Number(item.id_precio) === Number(form.data.id_precio));
    const enviar = (evento) => {
        evento.preventDefault();
        const firma = JSON.stringify(form.data);
        if (firma !== intento.current?.firma) intento.current = { firma, clave: nuevaClave() };
        form.transform((datos) => ({ ...datos, clave: intento.current.clave }));
        form.post('/mermas', {
            preserveScroll: true,
            onSuccess: () => { form.reset(); intento.current = null; },
            onError: (errores) => toast.error('No se registró la merma', primerError(errores)),
        });
    };

    return (
        <div className="space-y-4">
            <form onSubmit={enviar} className="space-y-3">
                <Field label="Insumo"><SelectorInsumo insumos={insumos} valor={form.data.id_precio} cambiar={(id) => form.setData('id_precio', id)} /></Field>
                <Field label="Cantidad en la presentación elegida"><Input required type="number" min={0.0001} step={0.0001} value={form.data.cantidad} onChange={(e) => form.setData('cantidad', e.target.value)} /></Field>
                {insumo && <p className="text-sm text-slate-600">Stock: {cantidad(insumo.stock)} {insumo.unidad ?? 'unidades base'}</p>}
                <Field label="Motivo"><Input required maxLength={255} value={form.data.motivo} onChange={(e) => form.setData('motivo', e.target.value)} /></Field>
                <Button type="submit" loading={form.processing} disabled={!insumo}>Registrar merma</Button>
            </form>
            <h2 className="font-semibold">Últimas mermas registradas</h2>
            {mermas.length ? <ul className="divide-y">{mermas.map((merma) => <li key={merma.id_merma} className="py-2 text-sm">{merma.nombre} · {cantidad(merma.cantidad_base)} en unidad base · {merma.motivo}</li>)}</ul> : <p className="text-sm text-slate-500">Sin mermas registradas.</p>}
        </div>
    );
}

export default function Recetas({ verRecetas, puedeRecetas, puedeMermas, insumos, platos, recetas, mermas }) {
    const [plato, setPlato] = useState('');
    const { flash } = usePage().props;
    const titulo = verRecetas ? 'Recetas' : 'Mermas de insumos';
    return (
        <AppLayout title={titulo}>
            <Head title={titulo} />
            <header className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h1 className="text-xl font-semibold">{titulo}</h1>
                {verRecetas && puedeMermas && <Button href="/mermas" variant="secondary">Registrar merma</Button>}
                {!verRecetas && puedeRecetas && <Button href="/recetas" variant="secondary">Ver recetas</Button>}
            </header>
            <Card className="max-w-3xl space-y-4 p-4">
                {flash?.success && <p role="status" className="text-sm text-emerald-800">{flash.success}</p>}
                {!insumos.length && <p className="text-sm text-slate-600">Define productos de tipo Insumo y sus presentaciones en el catálogo.</p>}
                {verRecetas ? <>
                    <Field label="Plato y presentación"><select className={estilo} value={plato} onChange={(e) => setPlato(e.target.value)}>
                        <option value="">Selecciona un plato</option>
                        {platos.map((item) => <option key={item.id_precio} value={item.id_precio}>{nombre(item)}</option>)}
                    </select></Field>
                    {plato && <EditarReceta key={plato} plato={plato} receta={recetas[plato] ?? []} insumos={insumos} />}
                </> : <RegistrarMerma insumos={insumos} mermas={mermas} />}
            </Card>
        </AppLayout>
    );
}
