import { useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { BookOpen, Globe, Layers, Pencil, Plus, SlidersHorizontal } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import {
    Badge, Button, Card, CardHeader, CheckboxField, Dialog, EmptyState, Field, IconButton, Input, PageHeader,
    PaginacionServidor, SearchInput, Select, StatusBadge, TBody, THead, Table, Td, Th, Tr, buttonClasses, cx, toast,
} from '@/Components/ui';
import { TIPO_PRODUCTO } from '@/Components/Restaurante/estados';
import { primerError, soles } from '@/Components/Restaurante/formato';

/**
 * Carta del restaurante. Los platos se crean en Productos (o con el importador);
 * aquí se decide cómo se venden en esta sucursal.
 */
export default function Carta({ productos, grupos = [], filtros }) {
    const { flash = {}, sucursal } = usePage().props;
    const [pestana, setPestana] = useState('productos');
    const [buscar, setBuscar] = useState(filtros.buscar ?? '');
    const [modificadores, setModificadores] = useState(null);
    const primeraCarga = useRef(true);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
    }, [flash.success]);

    // Búsqueda en el servidor (paginada) con una pausa corta para no consultar por tecla.
    useEffect(() => {
        if (primeraCarga.current) { primeraCarga.current = false; return undefined; }
        const espera = setTimeout(() => router.get('/carta', { buscar: buscar || undefined, tipo: filtros.tipo || undefined }, { preserveState: true, preserveScroll: true, replace: true, only: ['productos', 'filtros'] }), 300);
        return () => clearTimeout(espera);
    }, [buscar]); // eslint-disable-line react-hooks/exhaustive-deps

    const accion = (metodo, url, datos, mensajeError) => router[metodo](url, datos, {
        preserveScroll: true,
        preserveState: true,
        onError: (errores) => toast.error(mensajeError, primerError(errores)),
    });

    return (
        <AppLayout title="Carta">
            <Head title="Carta" />
            <div className="space-y-6">
                <PageHeader
                    title="Carta"
                    description={`Tipo de cada producto, precio y disponibilidad en ${sucursal?.activa?.nombre ?? 'esta sucursal'}, y opciones de cada plato.`}
                    icon={BookOpen}
                    actions={sucursal?.activa && (
                        // Enlace normal (no Inertia): el endpoint devuelve JSON público.
                        <a href="/api/carta-publica" target="_blank" rel="noreferrer" className={buttonClasses({ variant: 'secondary' })}>
                            <Globe className="h-4 w-4" aria-hidden="true" /> Carta pública (JSON)
                        </a>
                    )}
                />

                <div className="flex gap-1 border-b border-slate-200" role="tablist">
                    {[['productos', 'Productos', BookOpen], ['modificadores', `Modificadores (${grupos.length})`, SlidersHorizontal]].map(([clave, etiqueta, Icono]) => (
                        <button key={clave} type="button" role="tab" aria-selected={pestana === clave} onClick={() => setPestana(clave)} className={cx('flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium', pestana === clave ? 'border-brand text-brand-ink' : 'border-transparent text-slate-500 hover:text-slate-800')}>
                            <Icono className="h-4 w-4" aria-hidden="true" />{etiqueta}
                        </button>
                    ))}
                </div>

                {pestana === 'productos' ? (
                    <Card>
                        <div className="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
                            <SearchInput value={buscar} onChange={setBuscar} placeholder="Buscar producto…" className="w-full sm:w-72" />
                            <Select value={filtros.tipo ?? ''} onChange={(e) => router.get('/carta', { buscar: buscar || undefined, tipo: e.target.value || undefined }, { preserveState: true, replace: true })} className="w-full sm:w-52" aria-label="Filtrar por tipo">
                                <option value="">Todos los tipos</option>
                                {Object.entries(TIPO_PRODUCTO).map(([clave, tipo]) => <option key={clave} value={clave}>{tipo.etiqueta}</option>)}
                            </Select>
                        </div>
                        {productos.data.length === 0 ? (
                            <EmptyState icon={BookOpen} title="No hay productos" description="Crea productos en Productos o importa una carta con php artisan restaurante:importar-carta." />
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <THead>
                                        <Tr>
                                            <Th>Producto</Th>
                                            <Th>Tipo</Th>
                                            <Th align="right">Precio general</Th>
                                            <Th>Precio en esta sucursal</Th>
                                            <Th>Agotado hoy</Th>
                                            <Th>Opciones</Th>
                                        </Tr>
                                    </THead>
                                    <TBody>
                                        {productos.data.map((producto) => (
                                            <Tr key={producto.id_producto}>
                                                <Td>
                                                    <span className="flex items-start gap-2">
                                                        {producto.codigo_carta && <span className="mt-0.5 rounded bg-slate-100 px-1 font-mono text-xs text-slate-600">{producto.codigo_carta}</span>}
                                                        <span>
                                                            <span className="block font-medium text-slate-900">{producto.nombre}</span>
                                                            <span className="block text-xs text-slate-500">{producto.categoria}</span>
                                                        </span>
                                                    </span>
                                                </Td>
                                                <Td>
                                                    <Select value={producto.tipo_producto} aria-label={`Tipo de ${producto.nombre}`} className="w-40" onChange={(e) => accion('put', `/carta/productos/${producto.id_producto}/tipo`, { tipo_producto: e.target.value }, 'No se cambió el tipo')}>
                                                        {Object.entries(TIPO_PRODUCTO).map(([clave, tipo]) => <option key={clave} value={clave}>{tipo.etiqueta}</option>)}
                                                    </Select>
                                                </Td>
                                                <Td align="right" className="font-mono tabular-nums">{producto.precio_general === null ? '—' : soles(producto.precio_general)}</Td>
                                                <Td>
                                                    {producto.tipo_producto !== 'INSUMO' && producto.id_precio && (
                                                        <PrecioSucursal producto={producto} onGuardar={(datos) => accion('put', `/carta/precios/${producto.id_precio}`, datos, 'No se guardó el precio')} />
                                                    )}
                                                </Td>
                                                <Td>
                                                    {producto.tipo_producto !== 'INSUMO' && (
                                                        <CheckboxField label={<span className="sr-only">Agotado</span>} checked={producto.agotado} onChange={(valor) => accion('put', `/carta/productos/${producto.id_producto}/agotado`, { agotado: valor }, 'No se actualizó')} />
                                                    )}
                                                </Td>
                                                <Td>
                                                    {producto.tipo_producto !== 'INSUMO' && (
                                                        <Button size="sm" variant="ghost" icon={Layers} onClick={() => setModificadores(producto)}>
                                                            {producto.grupos.length ? `${producto.grupos.length} grupo(s)` : 'Asignar'}
                                                        </Button>
                                                    )}
                                                </Td>
                                            </Tr>
                                        ))}
                                    </TBody>
                                </Table>
                            </div>
                        )}
                        <PaginacionServidor paginador={productos} entidad="productos" />
                    </Card>
                ) : (
                    <GruposModificadores grupos={grupos} />
                )}
            </div>

            <DialogoGruposProducto producto={modificadores} grupos={grupos} onCerrar={() => setModificadores(null)} />
        </AppLayout>
    );
}

/** Precio propio de la sucursal: vacío = usa el general; "no se vende aquí" lo oculta de la carta local. */
function PrecioSucursal({ producto, onGuardar }) {
    const [valor, setValor] = useState(producto.precio_sucursal ?? '');
    useEffect(() => setValor(producto.precio_sucursal ?? ''), [producto.precio_sucursal]);

    const guardar = () => {
        const nuevo = valor === '' ? null : Number(valor);
        if (nuevo === (producto.precio_sucursal ?? null)) return;
        onGuardar({ precio_venta: nuevo, vende: true });
    };

    if (!producto.vende_en_sucursal) {
        return (
            <span className="flex items-center gap-2">
                <Badge tone="neutral">No se vende aquí</Badge>
                <Button size="sm" variant="ghost" onClick={() => onGuardar({ precio_venta: null, vende: true })}>Vender</Button>
            </span>
        );
    }

    return (
        <span className="flex items-center gap-2">
            <Input
                type="number" min={0} step="0.10" inputMode="decimal"
                value={valor}
                placeholder={producto.precio_general?.toFixed(2)}
                onChange={(e) => setValor(e.target.value)}
                onBlur={guardar}
                onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()}
                className="w-28 text-right font-mono"
                aria-label={`Precio de ${producto.nombre} en esta sucursal`}
            />
            <Button size="sm" variant="ghost" onClick={() => onGuardar({ precio_venta: null, vende: false })}>No vender</Button>
        </span>
    );
}

function DialogoGruposProducto({ producto, grupos, onCerrar }) {
    const [seleccion, setSeleccion] = useState([]);
    const [enviando, setEnviando] = useState(false);
    useEffect(() => { if (producto) setSeleccion(producto.grupos); }, [producto]);

    const guardar = () => router.put(`/carta/productos/${producto.id_producto}/modificadores`, { grupos: seleccion }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => setEnviando(true),
        onFinish: () => setEnviando(false),
        onSuccess: onCerrar,
        onError: (errores) => toast.error('No se guardó', primerError(errores)),
    });

    return (
        <Dialog
            open={Boolean(producto)}
            onClose={onCerrar}
            title={`Opciones de ${producto?.nombre ?? ''}`}
            description="Marca los grupos que el mozo verá al pedir este plato, en el orden en que los marques."
            size="md"
            footer={<><Button variant="secondary" onClick={onCerrar}>Cancelar</Button><Button onClick={guardar} loading={enviando}>Guardar</Button></>}
        >
            <div className="space-y-2 p-5">
                {grupos.length === 0 && <p className="text-sm text-slate-500">Primero crea grupos en la pestaña Modificadores.</p>}
                {grupos.map((grupo) => {
                    const posicion = seleccion.indexOf(grupo.id_grupo);
                    return (
                        <label key={grupo.id_grupo} className={cx('flex cursor-pointer items-center gap-3 rounded-xl p-3 ring-1', posicion >= 0 ? 'bg-brand-soft ring-brand' : 'ring-slate-200')}>
                            <input type="checkbox" checked={posicion >= 0} onChange={(e) => setSeleccion((s) => (e.target.checked ? [...s, grupo.id_grupo] : s.filter((id) => id !== grupo.id_grupo)))} />
                            <span className="flex-1">
                                <span className="block text-sm font-medium">{grupo.nombre} {!grupo.activo && <Badge>Inactivo</Badge>}</span>
                                <span className="block text-xs text-slate-500">{grupo.opciones.map((o) => o.nombre).join(', ') || 'Sin opciones'}</span>
                            </span>
                            {posicion >= 0 && <span className="font-mono text-xs text-brand-ink">#{posicion + 1}</span>}
                        </label>
                    );
                })}
            </div>
        </Dialog>
    );
}

function GruposModificadores({ grupos }) {
    const [editor, setEditor] = useState(null);

    return (
        <div className="space-y-4">
            <div className="flex justify-end"><Button icon={Plus} onClick={() => setEditor({ tipo: 'grupo' })}>Nuevo grupo</Button></div>
            {grupos.length === 0 ? (
                <Card><EmptyState icon={SlidersHorizontal} title="Sin modificadores" description="Crea grupos como «Término de la carne» (obligatorio, 1 opción) o «Adicionales» (opcional, varias)." /></Card>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">
                    {grupos.map((grupo) => (
                        <Card key={grupo.id_grupo}>
                            <CardHeader
                                title={<span className="flex items-center gap-2">{grupo.nombre} {!grupo.activo && <Badge>Inactivo</Badge>}</span>}
                                description={grupo.seleccion_minima > 0 ? `Obligatorio · elegir ${grupo.seleccion_minima} a ${grupo.seleccion_maxima}` : `Opcional · hasta ${grupo.seleccion_maxima}`}
                                actions={(
                                    <>
                                        <Button size="sm" variant="secondary" icon={Plus} onClick={() => setEditor({ tipo: 'opcion', grupo })}>Opción</Button>
                                        <IconButton icon={Pencil} label={`Editar ${grupo.nombre}`} onClick={() => setEditor({ tipo: 'grupo', valores: grupo })} />
                                    </>
                                )}
                            />
                            <ul className="divide-y divide-slate-100">
                                {grupo.opciones.length === 0 && <li className="px-5 py-4 text-sm text-slate-500">Sin opciones.</li>}
                                {grupo.opciones.map((opcion) => (
                                    <li key={opcion.id_opcion} className="flex items-center gap-3 px-5 py-2.5">
                                        <span className="flex-1 text-sm">{opcion.nombre}</span>
                                        {!opcion.activo && <StatusBadge active={false} />}
                                        <span className="font-mono text-sm tabular-nums text-slate-600">{opcion.precio_adicional > 0 ? `+${soles(opcion.precio_adicional)}` : 'sin costo'}</span>
                                        <IconButton icon={Pencil} label={`Editar ${opcion.nombre}`} onClick={() => setEditor({ tipo: 'opcion', grupo, valores: opcion })} />
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    ))}
                </div>
            )}
            <EditorModificador editor={editor} onCerrar={() => setEditor(null)} />
        </div>
    );
}

function EditorModificador({ editor, onCerrar }) {
    const esGrupo = editor?.tipo === 'grupo';
    const valores = editor?.valores;
    const [datos, setDatos] = useState({});
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);

    useEffect(() => {
        if (!editor) return;
        setErrores({});
        setDatos(esGrupo
            ? { nombre: valores?.nombre ?? '', seleccion_minima: valores?.seleccion_minima ?? 0, seleccion_maxima: valores?.seleccion_maxima ?? 1, activo: valores?.activo ?? true }
            : { nombre: valores?.nombre ?? '', precio_adicional: valores?.precio_adicional ?? 0, orden: valores?.orden ?? 0, activo: valores?.activo ?? true });
    }, [editor, esGrupo, valores]);

    const guardar = (e) => {
        e.preventDefault();
        const url = esGrupo
            ? (valores ? `/carta/modificadores/${valores.id_grupo}` : '/carta/modificadores')
            : (valores ? `/carta/opciones/${valores.id_opcion}` : `/carta/modificadores/${editor.grupo.id_grupo}/opciones`);
        router[valores ? 'put' : 'post'](url, datos, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setEnviando(true),
            onFinish: () => setEnviando(false),
            onSuccess: onCerrar,
            onError: setErrores,
        });
    };

    const set = (campo) => (e) => setDatos((d) => ({ ...d, [campo]: e.target.value }));

    return (
        <Dialog
            open={Boolean(editor)}
            onClose={enviando ? () => {} : onCerrar}
            title={esGrupo ? (valores ? 'Editar grupo' : 'Nuevo grupo de opciones') : `${valores ? 'Editar' : 'Nueva'} opción · ${editor?.grupo?.nombre ?? ''}`}
            size="sm"
            footer={<><Button variant="secondary" onClick={onCerrar} disabled={enviando}>Cancelar</Button><Button type="submit" form="form-modificador" loading={enviando}>Guardar</Button></>}
        >
            <form id="form-modificador" onSubmit={guardar} className="space-y-4 p-5">
                <Field label="Nombre" error={errores.nombre} required hint={esGrupo ? 'Ej.: Término de la carne, Adicionales' : 'Ej.: Jugoso, Huevo frito'}>
                    <Input value={datos.nombre ?? ''} onChange={set('nombre')} maxLength={80} autoFocus />
                </Field>
                {esGrupo ? (
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Mínimo a elegir" error={errores.seleccion_minima} hint="0 = opcional">
                            <Input type="number" min={0} max={20} value={datos.seleccion_minima ?? 0} onChange={set('seleccion_minima')} />
                        </Field>
                        <Field label="Máximo a elegir" error={errores.seleccion_maxima}>
                            <Input type="number" min={1} max={20} value={datos.seleccion_maxima ?? 1} onChange={set('seleccion_maxima')} />
                        </Field>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Precio adicional" error={errores.precio_adicional}>
                            <Input type="number" min={0} step="0.10" value={datos.precio_adicional ?? 0} onChange={set('precio_adicional')} className="font-mono" />
                        </Field>
                        <Field label="Orden" error={errores.orden}>
                            <Input type="number" min={0} max={999} value={datos.orden ?? 0} onChange={set('orden')} />
                        </Field>
                    </div>
                )}
                <CheckboxField label="Activo" checked={datos.activo} onChange={(valor) => setDatos((d) => ({ ...d, activo: valor }))} />
            </form>
        </Dialog>
    );
}
