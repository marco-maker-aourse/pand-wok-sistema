import { useEffect, useRef, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, toast } from '@/Components/ui';
import { primerError } from '@/Components/Restaurante/formato';

const NUEVA = { nombre: '', activo: true, por_defecto: false, orden: 0, copias: 1, id_impresora: '', id_terminal: '', categorias: [] };

function FormularioEstacion({ estacion, categorias, impresoras, terminales, cerrar }) {
    const form = useRef(null);
    useEffect(() => { form.current?.scrollIntoView({ block: 'nearest' }); }, []);
    const { data, setData, processing, errors, post, put } = useForm({
        ...NUEVA,
        ...estacion,
        id_impresora: estacion.id_impresora ?? '',
        id_terminal: estacion.id_terminal ?? '',
    });
    const guardar = (evento) => {
        evento.preventDefault();
        const opciones = {
            preserveScroll: true,
            onSuccess: () => { cerrar(); toast.success('Estación guardada'); },
            onError: (errores) => toast.error('No se guardó la estación', primerError(errores)),
        };
        if (estacion.id_estacion) put(`/cocina/estaciones/${estacion.id_estacion}`, opciones);
        else post('/cocina/estaciones', opciones);
    };

    const alternarCategoria = (id) => setData('categorias', data.categorias.includes(id)
        ? data.categorias.filter((categoria) => categoria !== id)
        : [...data.categorias, id]);
    const impresora = impresoras.find((a) => a.id_impresora === Number(data.id_impresora));
    const enNube = Boolean(impresora?.cloudprnt && !data.id_terminal);
    const campo = 'mt-1 block min-h-10 w-full rounded-lg border-slate-300 text-sm';

    return (
        <Card className="p-4">
            <form ref={form} onSubmit={guardar} className="space-y-4">
                <h2 className="text-lg font-semibold">{estacion.id_estacion ? 'Editar estación' : 'Nueva estación'}</h2>
                {Object.keys(errors).length > 0 && <p role="alert" className="text-sm text-rose-700">{primerError(errors)}</p>}
                <div className="grid gap-3 sm:grid-cols-2">
                    <label className="text-sm font-medium">Nombre<input className={campo} required maxLength={60} value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} /></label>
                    <label className="text-sm font-medium">Orden<input className={campo} required type="number" min={0} max={32767} value={data.orden} onChange={(e) => setData('orden', e.target.value)} /></label>
                    <label className="text-sm font-medium">Impresora<select className={campo} value={data.id_impresora} onChange={(e) => {
                        const id = e.target.value;
                        const seleccion = impresoras.find((a) => a.id_impresora === Number(id));
                        setData({ ...data, id_impresora: id, id_terminal: !id || seleccion?.cloudprnt ? '' : data.id_terminal, copias: seleccion?.cloudprnt ? 1 : data.copias });
                    }}>
                        <option value="">Sin impresora</option>
                        {impresoras.map((a) => <option key={a.id_impresora} value={a.id_impresora}>{a.nombre}{a.cloudprnt ? ' · CloudPRNT' : ''}</option>)}
                    </select></label>
                    <label className="text-sm font-medium">Terminal del agente<select className={campo} value={data.id_terminal} onChange={(e) => setData({ ...data, id_terminal: e.target.value, copias: !e.target.value && impresora?.cloudprnt ? 1 : data.copias })}>
                        <option value="">{impresora?.cloudprnt ? 'CloudPRNT directo' : 'Sin terminal'}</option>
                        {terminales.map((a) => <option key={a.id_terminal} value={a.id_terminal}>{a.nombre}</option>)}
                    </select></label>
                    <label className="text-sm font-medium">Copias<input className={campo} required type="number" min={1} max={5} value={data.copias} disabled={enNube} onChange={(e) => setData('copias', e.target.value)} /></label>
                </div>
                <label className="flex min-h-10 items-center gap-2 text-sm"><input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} /> Estación activa</label>
                <label className="flex min-h-10 items-center gap-2 text-sm"><input type="checkbox" checked={data.por_defecto} onChange={(e) => setData('por_defecto', e.target.checked)} /> Por defecto para platos sin categoría asignada</label>
                <fieldset>
                    <legend className="text-sm font-semibold">Categorías asignadas</legend>
                    <p className="text-sm text-slate-600">Si una categoría ya pertenece a otra estación de esta sucursal, se moverá al guardar.</p>
                    <div className="mt-2 grid gap-1 sm:grid-cols-2">
                        {categorias.map((categoria) => (
                            <label key={categoria.id_categoria} className="flex min-h-10 items-center gap-2 text-sm">
                                <input type="checkbox" checked={data.categorias.includes(categoria.id_categoria)} onChange={() => alternarCategoria(categoria.id_categoria)} />{categoria.nombre}
                            </label>
                        ))}
                        {categorias.length === 0 && <p className="text-sm text-slate-600">No hay categorías registradas.</p>}
                    </div>
                </fieldset>
                <div className="flex flex-wrap gap-2">
                    <Button type="submit" loading={processing}>Guardar estación</Button>
                    <Button variant="secondary" disabled={processing} onClick={cerrar}>Cancelar</Button>
                </div>
            </form>
        </Card>
    );
}

export default function Estaciones({ estaciones, categorias, impresoras, terminales, puedeVerCocina }) {
    const [formulario, setFormulario] = useState(null);
    const nombresCategorias = new Map(categorias.map((categoria) => [categoria.id_categoria, categoria.nombre]));

    return (
        <AppLayout title="Estaciones de cocina">
            <Head title="Estaciones de cocina" />
            <header className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold text-slate-900">Estaciones de cocina</h1>
                <div className="flex flex-wrap gap-2">
                    {puedeVerCocina && <Button variant="secondary" href="/cocina">Ver cocina</Button>}
                    <Button icon={Plus} onClick={() => setFormulario({})}>Nueva estación</Button>
                </div>
            </header>
            {formulario && <FormularioEstacion key={formulario.id_estacion ?? 'nueva'} estacion={formulario} categorias={categorias} impresoras={impresoras} terminales={terminales} cerrar={() => setFormulario(null)} />}
            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {estaciones.map((estacion) => (
                    <Card key={estacion.id_estacion} className="flex flex-col items-start gap-2 p-4">
                        <h2 className="font-semibold">{estacion.nombre}</h2>
                        <p className="text-sm text-slate-600">{estacion.activo ? 'Activa' : 'Inactiva'}{estacion.por_defecto && ' · Por defecto'}</p>
                        <p className="text-sm text-slate-600">{estacion.categorias.map((id) => nombresCategorias.get(id)).filter(Boolean).join(', ') || 'Sin categorías asignadas'}</p>
                        <p className="text-sm text-slate-600">{estacion.impresora?.nombre ?? 'Sin impresora'}{estacion.terminal ? ` · ${estacion.terminal.nombre}` : estacion.impresora && ' · CloudPRNT directo'}</p>
                        <Button variant="secondary" onClick={() => setFormulario(estacion)}>Editar</Button>
                    </Card>
                ))}
                {estaciones.length === 0 && <p className="text-sm text-slate-600">Todavía no hay estaciones en esta sucursal.</p>}
            </div>
        </AppLayout>
    );
}
