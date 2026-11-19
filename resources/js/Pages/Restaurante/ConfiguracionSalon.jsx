import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { LayoutGrid, Pencil, Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, CardHeader, CheckboxField, Dialog, EmptyState, Field, IconButton, Input, PageHeader, Select, StatusBadge, toast } from '@/Components/ui';
import { ESTADO_MESA } from '@/Components/Restaurante/estados';

/** Áreas y mesas de la sucursal activa. Desactivar conserva el historial de cuentas. */
export default function ConfiguracionSalon({ areas = [] }) {
    const { flash = {} } = usePage().props;
    const [formulario, setFormulario] = useState(null);

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
    }, [flash.success]);

    const totalMesas = areas.reduce((suma, area) => suma + area.mesas.length, 0);

    return (
        <AppLayout title="Mesas y áreas">
            <Head title="Mesas y áreas" />
            <div className="space-y-6">
                <PageHeader
                    title="Mesas y áreas"
                    description="Organiza el salón de esta sucursal. Cada sucursal tiene sus propias áreas y mesas."
                    icon={LayoutGrid}
                    breadcrumbs={[{ label: 'Salón', href: '/salon' }, { label: 'Mesas y áreas' }]}
                    meta={`${areas.length} áreas · ${totalMesas} mesas`}
                    actions={(
                        <>
                            <Button variant="secondary" icon={Plus} onClick={() => setFormulario({ tipo: 'area' })}>Nueva área</Button>
                            <Button icon={Plus} disabled={areas.length === 0} onClick={() => setFormulario({ tipo: 'mesa', valores: { id_area: areas[0]?.id_area } })}>Nueva mesa</Button>
                        </>
                    )}
                />

                {areas.length === 0 ? (
                    <Card>
                        <EmptyState icon={LayoutGrid} title="Crea tu primera área" description="Por ejemplo: Salón principal, Terraza o Barra. Luego agrega sus mesas." action={<Button icon={Plus} onClick={() => setFormulario({ tipo: 'area' })}>Nueva área</Button>} />
                    </Card>
                ) : areas.map((area) => (
                    <Card key={area.id_area}>
                        <CardHeader
                            title={<span className="flex items-center gap-2">{area.nombre} {!area.activo && <Badge>Inactiva</Badge>}</span>}
                            description={`${area.mesas.length} mesas · orden ${area.orden}`}
                            actions={(
                                <>
                                    <Button size="sm" variant="secondary" icon={Plus} onClick={() => setFormulario({ tipo: 'mesa', valores: { id_area: area.id_area } })}>Mesa</Button>
                                    <IconButton icon={Pencil} label={`Editar área ${area.nombre}`} onClick={() => setFormulario({ tipo: 'area', valores: area })} />
                                </>
                            )}
                        />
                        {area.mesas.length === 0 ? (
                            <p className="px-5 py-6 text-sm text-slate-500">Sin mesas.</p>
                        ) : (
                            <ul className="grid grid-cols-2 gap-3 p-5 sm:grid-cols-4 lg:grid-cols-6">
                                {area.mesas.map((mesa) => (
                                    <li key={mesa.id_mesa}>
                                        <button
                                            type="button"
                                            onClick={() => setFormulario({ tipo: 'mesa', valores: mesa })}
                                            className="w-full rounded-xl p-3 text-left ring-1 ring-slate-200 hover:bg-slate-50 hover:ring-brand"
                                        >
                                            <span className="block text-lg font-semibold text-slate-900">{mesa.codigo}</span>
                                            <span className="block text-xs text-slate-500">{mesa.capacidad} pers. · {ESTADO_MESA[mesa.estado]?.etiqueta}</span>
                                            {!mesa.activo && <span className="mt-1 block"><StatusBadge active={false} inactiveLabel="Inactiva" /></span>}
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                ))}
            </div>

            <FormularioSalon formulario={formulario} areas={areas} onCerrar={() => setFormulario(null)} />
        </AppLayout>
    );
}

function FormularioSalon({ formulario, areas, onCerrar }) {
    const esMesa = formulario?.tipo === 'mesa';
    const editando = Boolean(formulario?.valores?.id_mesa ?? formulario?.valores?.id_area) && !(esMesa && !formulario?.valores?.id_mesa);
    const [datos, setDatos] = useState({});
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);

    useEffect(() => {
        if (!formulario) return;
        const v = formulario.valores ?? {};
        setErrores({});
        setDatos(esMesa
            ? { id_area: v.id_area ?? '', codigo: v.codigo ?? '', capacidad: v.capacidad ?? 4, orden: v.orden ?? 0, activo: v.activo ?? true }
            : { nombre: v.nombre ?? '', orden: v.orden ?? 0, activo: v.activo ?? true });
    }, [formulario, esMesa]);

    const guardar = (e) => {
        e.preventDefault();
        const v = formulario.valores ?? {};
        const url = esMesa
            ? (v.id_mesa ? `/salon/mesas/${v.id_mesa}` : '/salon/mesas')
            : (v.id_area ? `/salon/areas/${v.id_area}` : '/salon/areas');
        router[(esMesa ? v.id_mesa : v.id_area) ? 'put' : 'post'](url, datos, {
            preserveScroll: true,
            onStart: () => setEnviando(true),
            onFinish: () => setEnviando(false),
            onSuccess: onCerrar,
            onError: setErrores,
        });
    };

    const set = (campo) => (e) => setDatos((d) => ({ ...d, [campo]: e.target.value }));

    return (
        <Dialog
            open={Boolean(formulario)}
            onClose={enviando ? () => {} : onCerrar}
            title={`${editando ? 'Editar' : 'Nueva'} ${esMesa ? 'mesa' : 'área'}`}
            size="sm"
            footer={(
                <>
                    <Button variant="secondary" onClick={onCerrar} disabled={enviando}>Cancelar</Button>
                    <Button type="submit" form="form-salon" loading={enviando}>Guardar</Button>
                </>
            )}
        >
            <form id="form-salon" onSubmit={guardar} className="space-y-4 p-5">
                {esMesa ? (
                    <>
                        <Field label="Área" error={errores.id_area} required>
                            <Select value={datos.id_area ?? ''} onChange={set('id_area')}>
                                {areas.map((a) => <option key={a.id_area} value={a.id_area}>{a.nombre}</option>)}
                            </Select>
                        </Field>
                        <div className="grid grid-cols-2 gap-4">
                            <Field label="Código" error={errores.codigo} required hint="Ej.: M1, T4">
                                <Input value={datos.codigo ?? ''} onChange={(e) => setDatos((d) => ({ ...d, codigo: e.target.value.toUpperCase() }))} maxLength={20} autoFocus />
                            </Field>
                            <Field label="Capacidad" error={errores.capacidad} required>
                                <Input type="number" min={1} max={50} value={datos.capacidad ?? 4} onChange={set('capacidad')} />
                            </Field>
                        </div>
                    </>
                ) : (
                    <Field label="Nombre" error={errores.nombre} required hint="Ej.: Salón principal, Terraza">
                        <Input value={datos.nombre ?? ''} onChange={set('nombre')} maxLength={60} autoFocus />
                    </Field>
                )}
                <Field label="Orden" error={errores.orden} hint="Menor número aparece primero.">
                    <Input type="number" min={0} max={999} value={datos.orden ?? 0} onChange={set('orden')} />
                </Field>
                <CheckboxField
                    label={esMesa ? 'Mesa activa' : 'Área activa'}
                    description="Desactivar la oculta del salón sin borrar su historial. No se permite con cuentas abiertas."
                    checked={datos.activo}
                    onChange={(valor) => setDatos((d) => ({ ...d, activo: valor }))}
                />
                {errores.activo && <p className="text-sm text-rose-600">{errores.activo}</p>}
            </form>
        </Dialog>
    );
}
