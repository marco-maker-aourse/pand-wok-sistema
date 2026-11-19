import { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bike, ChevronRight, LayoutGrid, Settings2, ShoppingBag, Utensils } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, CardHeader, EmptyState, PageHeader, Stat, cx, toast } from '@/Components/ui';
import DialogoAbrirCuenta from '@/Components/Restaurante/DialogoAbrirCuenta';
import TarjetaMesa from '@/Components/Restaurante/TarjetaMesa';
import { ESTADO_MESA } from '@/Components/Restaurante/estados';
import { primerError, soles, tiempoDesde, tituloCuenta } from '@/Components/Restaurante/formato';

const REFRESCO_MS = 15000;

/** Plano del salón: el mozo ve qué mesas están libres, ocupadas o reservadas y entra a cada cuenta. */
export default function Salon({ areas = [], cuentasSinMesa = [], resumen, puede }) {
    const { flash = {} } = usePage().props;
    const [areaActiva, setAreaActiva] = useState(() => areas[0]?.id_area ?? null);
    const [dialogo, setDialogo] = useState(null);
    const [ahora, setAhora] = useState(Date.now());

    useEffect(() => {
        if (flash.success) toast.success(flash.success);
    }, [flash.success]);

    // Refresco ligero mientras la pestaña está visible: otros mozos abren y cierran cuentas.
    useEffect(() => {
        const intervalo = setInterval(() => {
            setAhora(Date.now());
            if (document.visibilityState === 'visible' && !dialogo) {
                router.reload({ only: ['areas', 'cuentasSinMesa', 'resumen'] });
            }
        }, REFRESCO_MS);
        return () => clearInterval(intervalo);
    }, [dialogo]);

    useEffect(() => {
        if (!areas.some((area) => area.id_area === areaActiva)) setAreaActiva(areas[0]?.id_area ?? null);
    }, [areas, areaActiva]);

    const area = areas.find((a) => a.id_area === areaActiva);

    const rutaCuenta = (id) => `/pedidos/${id}${!puede.abrir && puede.cobrar ? '/cobrar' : ''}`;
    const seleccionarMesa = (mesa) => {
        if (mesa.cuenta) {
            router.visit(rutaCuenta(mesa.cuenta.id_pedido));
        } else if (puede.abrir && ['DISPONIBLE', 'RESERVADA'].includes(mesa.estado)) {
            setDialogo({ tipo: 'MESA', mesa });
        }
    };

    const cambiarEstado = (mesa, estado) => {
        router.put(`/salon/mesas/${mesa.id_mesa}/estado`, { estado }, {
            preserveScroll: true,
            only: ['areas', 'resumen', 'flash'],
            onError: (errores) => toast.error('No se pudo cambiar la mesa', primerError(errores)),
        });
    };

    return (
        <AppLayout title="Salón">
            <Head title="Salón" />
            <div className="space-y-6">
                <PageHeader
                    title="Salón"
                    description="Toca una mesa libre para abrir su cuenta o una ocupada para continuar el pedido."
                    icon={LayoutGrid}
                    actions={(
                        <>
                            {puede.configurar && <Button variant="secondary" icon={Settings2} href="/salon/configuracion">Mesas y áreas</Button>}
                            {puede.abrir && <Button variant="secondary" icon={ShoppingBag} onClick={() => setDialogo({ tipo: 'PARA_LLEVAR' })}>Para llevar</Button>}
                            {puede.abrir && <Button icon={Bike} onClick={() => setDialogo({ tipo: 'DELIVERY' })}>Delivery</Button>}
                        </>
                    )}
                />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <Stat label="Mesas libres" value={resumen.libres} tone="success" icon={LayoutGrid} />
                    <Stat label="Ocupadas" value={resumen.ocupadas} icon={Utensils} destacado />
                    <Stat label="Reservadas" value={resumen.reservadas} tone="warning" icon={LayoutGrid} />
                    <Stat label="Consumo en curso" value={soles(resumen.consumo)} hint={`${resumen.cuentas} cuentas abiertas`} icon={ShoppingBag} />
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <Card>
                        {areas.length === 0 ? (
                            <EmptyState
                                icon={LayoutGrid}
                                title="Aún no hay mesas en esta sucursal"
                                description="Crea las áreas (salón, terraza…) y sus mesas para empezar a tomar pedidos."
                                action={puede.configurar && <Button href="/salon/configuracion" icon={Settings2}>Configurar mesas</Button>}
                            />
                        ) : (
                            <>
                                <div className="flex gap-1 overflow-x-auto border-b border-slate-100 px-3 pt-3" role="tablist" aria-label="Áreas del salón">
                                    {areas.map((a) => {
                                        const ocupadas = a.mesas.filter((m) => m.estado === 'OCUPADA').length;
                                        const activa = a.id_area === areaActiva;
                                        return (
                                            <button
                                                key={a.id_area}
                                                type="button"
                                                role="tab"
                                                aria-selected={activa}
                                                onClick={() => setAreaActiva(a.id_area)}
                                                className={cx('flex shrink-0 items-center gap-2 rounded-t-lg border-b-2 px-4 py-2.5 text-sm font-medium transition-colors', activa ? 'border-brand text-brand-ink' : 'border-transparent text-slate-500 hover:text-slate-800')}
                                            >
                                                {a.nombre}
                                                <span className="rounded-full bg-slate-100 px-1.5 text-xs tabular-nums text-slate-600">{ocupadas}/{a.mesas.length}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                                <div className="p-4 sm:p-5">
                                    {area?.mesas.length ? (
                                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                                            {area.mesas.map((mesa) => (
                                                <TarjetaMesa
                                                    key={mesa.id_mesa}
                                                    mesa={mesa}
                                                    ahora={ahora}
                                                    onSeleccionar={seleccionarMesa}
                                                    onCambiarEstado={cambiarEstado}
                                                    puedeCambiarEstado={puede.abrir}
                                                />
                                            ))}
                                        </div>
                                    ) : (
                                        <EmptyState icon={LayoutGrid} title="Esta área no tiene mesas activas" />
                                    )}
                                    <ul className="mt-5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500" aria-label="Leyenda">
                                        {Object.entries(ESTADO_MESA).map(([clave, estado]) => (
                                            <li key={clave} className="flex items-center gap-1.5"><span className={cx('h-2 w-2 rounded-full', estado.punto)} aria-hidden="true" />{estado.etiqueta}</li>
                                        ))}
                                    </ul>
                                </div>
                            </>
                        )}
                    </Card>

                    <Card as="aside" className="self-start">
                        <CardHeader title="Para llevar y delivery" description="Cuentas abiertas sin mesa" icon={ShoppingBag} />
                        {cuentasSinMesa.length === 0 ? (
                            <p className="px-5 py-8 text-center text-sm text-slate-500">No hay pedidos para llevar ni delivery abiertos.</p>
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {cuentasSinMesa.map((cuenta) => (
                                    <li key={cuenta.id_pedido}>
                                        <Link href={rutaCuenta(cuenta.id_pedido)} className="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium text-slate-900">{tituloCuenta(cuenta)}</p>
                                                <p className="text-xs text-slate-500">
                                                    #{cuenta.numero} · {tiempoDesde(cuenta.abierto_en, ahora)} · {cuenta.items} ítems
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <Badge tone={cuenta.tipo === 'DELIVERY' ? 'info' : 'neutral'}>{cuenta.tipo === 'DELIVERY' ? 'Delivery' : 'Llevar'}</Badge>
                                                <p className="mt-1 font-mono text-sm font-semibold tabular-nums">{soles(cuenta.total)}</p>
                                            </div>
                                            <ChevronRight className="h-4 w-4 text-slate-400" aria-hidden="true" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>

            <DialogoAbrirCuenta
                abierto={Boolean(dialogo)}
                onCerrar={() => setDialogo(null)}
                tipo={dialogo?.tipo}
                mesa={dialogo?.mesa}
            />
        </AppLayout>
    );
}
