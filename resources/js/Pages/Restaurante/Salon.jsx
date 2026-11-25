import { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Bike, ChevronRight, Clock3, LayoutGrid, RefreshCw, Settings2, ShoppingBag } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, EmptyState, PageHeader, cx, toast } from '@/Components/ui';
import TarjetaMesa, { ESTILO_MESA } from '@/Components/Restaurante/TarjetaMesa';
import { primerError, soles, tiempoDesde, tituloCuenta } from '@/Components/Restaurante/formato';

const REFRESCO_MS = 15000;
const FILTROS = [
    { clave: 'TODAS', etiqueta: 'Todas' },
    { clave: 'DISPONIBLE', etiqueta: 'Libres' },
    { clave: 'OCUPADA', etiqueta: 'Ocupadas' },
    { clave: 'RESERVADA', etiqueta: 'Reservadas' },
];

/** Filtro de estado con su conteo: también sirve de resumen del área. */
function FiltroEstado({ filtro, onCambiar, conteos }) {
    return (
        <div className="flex gap-1 overflow-x-auto rounded-xl bg-slate-200/60 p-1 [scrollbar-width:none]" role="radiogroup" aria-label="Mostrar mesas">
            {FILTROS.map(({ clave, etiqueta }) => {
                const Icono = ESTILO_MESA[clave]?.icono;
                const activo = filtro === clave;
                return (
                    <button key={clave} type="button" role="radio" aria-checked={activo} onClick={() => onCambiar(clave)}
                        className={cx('flex h-9 shrink-0 items-center gap-1.5 rounded-lg px-3 text-sm font-medium transition-colors', activo ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900')}>
                        {Icono && <Icono className={cx('h-4 w-4', activo ? ESTILO_MESA[clave].texto : 'text-slate-400')} aria-hidden="true" />}
                        {etiqueta}
                        <span className="font-mono text-xs text-slate-500 tabular-nums">{conteos[clave] ?? 0}</span>
                    </button>
                );
            })}
        </div>
    );
}

function CuentasSinMesa({ cuentas, ahora, rutaCuenta, puedeAbrir }) {
    return (
        <Card as="aside" className="self-start overflow-hidden">
            <header className="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <div>
                    <h2 className="text-sm font-semibold text-slate-900">Para llevar y delivery</h2>
                    <p className="text-xs text-slate-500">{cuentas.length ? `${cuentas.length} ${cuentas.length === 1 ? 'pedido abierto' : 'pedidos abiertos'}` : 'Sin pedidos abiertos'}</p>
                </div>
                {puedeAbrir && (
                    <div className="flex gap-1">
                        <Button variant="secondary" size="sm" icon={ShoppingBag} href="/pos?tipo=PARA_LLEVAR" aria-label="Nuevo para llevar" />
                        <Button variant="secondary" size="sm" icon={Bike} href="/pos?tipo=DELIVERY" aria-label="Nuevo delivery" />
                    </div>
                )}
            </header>
            {cuentas.length === 0 ? (
                <p className="px-4 py-8 text-center text-sm text-slate-500">Los pedidos de mostrador y delivery sin cobrar aparecen aquí.</p>
            ) : (
                <ul className="divide-y divide-slate-100">
                    {cuentas.map((cuenta) => {
                        const Icono = cuenta.tipo === 'DELIVERY' ? Bike : ShoppingBag;
                        return (
                            <li key={cuenta.id_pedido}>
                                <Link href={rutaCuenta(cuenta.id_pedido)} className="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 active:bg-slate-100">
                                    <span className={cx('grid h-9 w-9 shrink-0 place-items-center rounded-xl', cuenta.tipo === 'DELIVERY' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-600')}>
                                        <Icono className="h-4 w-4" aria-hidden="true" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-slate-900">{tituloCuenta(cuenta)}</p>
                                        <p className="flex items-center gap-1 text-xs text-slate-500 tabular-nums">
                                            <Clock3 className="h-3 w-3" aria-hidden="true" />{tiempoDesde(cuenta.abierto_en, ahora)} · {cuenta.items} ítems
                                        </p>
                                    </div>
                                    <span className="font-mono text-sm font-semibold text-slate-900 tabular-nums">{soles(cuenta.total)}</span>
                                    <ChevronRight className="h-4 w-4 text-slate-400" aria-hidden="true" />
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Card>
    );
}

/** Plano del salón: qué mesas están libres, ocupadas o reservadas, y un toque para atenderlas. */
export default function Salon({ areas = [], cuentasSinMesa = [], resumen, puede }) {
    const { flash = {} } = usePage().props;
    const [areaActiva, setAreaActiva] = useState(() => areas[0]?.id_area ?? null);
    const [filtro, setFiltro] = useState('TODAS');
    const [ahora, setAhora] = useState(Date.now());
    const [actualizando, setActualizando] = useState(false);

    useEffect(() => { if (flash.success) toast.success(flash.success); }, [flash.success]);

    const recargar = () => router.reload({
        only: ['areas', 'cuentasSinMesa', 'resumen'],
        onStart: () => setActualizando(true),
        onFinish: () => { setActualizando(false); setAhora(Date.now()); },
    });

    // Otros mozos abren y cierran cuentas: refresco ligero solo con la pestaña visible.
    useEffect(() => {
        const intervalo = setInterval(() => {
            setAhora(Date.now());
            if (document.visibilityState === 'visible') router.reload({ only: ['areas', 'cuentasSinMesa', 'resumen'] });
        }, REFRESCO_MS);
        return () => clearInterval(intervalo);
    }, []);

    useEffect(() => {
        if (!areas.some((a) => a.id_area === areaActiva)) setAreaActiva(areas[0]?.id_area ?? null);
    }, [areas, areaActiva]);

    const area = areas.find((a) => a.id_area === areaActiva);
    const conteos = useMemo(() => (area?.mesas ?? []).reduce((c, m) => ({ ...c, [m.estado]: (c[m.estado] ?? 0) + 1, TODAS: c.TODAS + 1 }), { TODAS: 0 }), [area]);
    const mesas = (area?.mesas ?? []).filter((m) => filtro === 'TODAS' || m.estado === filtro);

    // Todo se atiende desde el punto de venta; quien solo cobra va directo al cobro.
    const rutaCuenta = (id) => (puede.abrir ? `/pos?cuenta=${id}` : `/pedidos/${id}/cobrar`);
    const seleccionarMesa = (mesa) => {
        if (mesa.cuenta) router.visit(rutaCuenta(mesa.cuenta.id_pedido));
        else if (puede.abrir && ['DISPONIBLE', 'RESERVADA'].includes(mesa.estado)) router.get('/pos', { mesa: mesa.id_mesa });
        else if (mesa.estado === 'OCUPADA') {
            // La cuenta pudo cerrarse en otro equipo: se actualiza el plano en lugar de no hacer nada.
            toast.info(`La mesa ${mesa.codigo} ya no tiene una cuenta abierta`, 'Actualizando el salón…');
            recargar();
        }
    };

    const cambiarEstado = (mesa, estado) => router.put(`/salon/mesas/${mesa.id_mesa}/estado`, { estado }, {
        preserveScroll: true,
        only: ['areas', 'resumen', 'flash'],
        onError: (errores) => toast.error('No se pudo cambiar la mesa', primerError(errores)),
    });

    const ocupacion = resumen.mesas ? Math.round((resumen.ocupadas / resumen.mesas) * 100) : 0;

    return (
        <AppLayout title="Salón">
            <Head title="Salón" />
            <div className="space-y-4">
                <PageHeader
                    title="Salón"
                    icon={LayoutGrid}
                    description={resumen.mesas
                        ? `${resumen.ocupadas} de ${resumen.mesas} mesas ocupadas (${ocupacion} %), ${soles(resumen.consumo)} en consumo.`
                        : 'Configura las áreas y mesas para empezar.'}
                    actions={(
                        <>
                            <Button variant="ghost" icon={RefreshCw} onClick={recargar} loading={actualizando} aria-label="Actualizar salón" />
                            {puede.configurar && <Button variant="secondary" icon={Settings2} href="/salon/configuracion">Mesas y áreas</Button>}
                            {puede.abrir && <Button variant="secondary" icon={ShoppingBag} href="/pos?tipo=PARA_LLEVAR">Para llevar</Button>}
                            {puede.abrir && <Button icon={Bike} href="/pos?tipo=DELIVERY">Delivery</Button>}
                        </>
                    )}
                />

                <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_21rem]">
                    <Card className="overflow-hidden">
                        {areas.length === 0 ? (
                            <EmptyState
                                icon={LayoutGrid}
                                title="Aún no hay mesas en esta sucursal"
                                description="Crea las áreas (salón, terraza…) y sus mesas para tomar pedidos por mesa."
                                action={puede.configurar && <Button href="/salon/configuracion" icon={Settings2}>Configurar mesas</Button>}
                            />
                        ) : (
                            <>
                                <div className="space-y-3 border-b border-slate-100 p-3 sm:p-4">
                                    {areas.length > 1 && (
                                        <div className="-mx-1 flex gap-1 overflow-x-auto px-1 [scrollbar-width:none]" role="tablist" aria-label="Áreas del salón">
                                            {areas.map((a) => {
                                                const ocupadas = a.mesas.filter((m) => m.estado === 'OCUPADA').length;
                                                const activa = a.id_area === areaActiva;
                                                return (
                                                    <button key={a.id_area} type="button" role="tab" aria-selected={activa} onClick={() => { setAreaActiva(a.id_area); setFiltro('TODAS'); }}
                                                        className={cx('flex shrink-0 flex-col items-start gap-1 rounded-xl px-3.5 py-2 text-left transition-colors', activa ? 'bg-brand text-on-brand' : 'bg-slate-100 text-slate-700 hover:bg-slate-200')}>
                                                        <span className="text-sm font-semibold">{a.nombre}</span>
                                                        <span className={cx('text-xs tabular-nums', activa ? 'text-on-brand/80' : 'text-slate-500')}>{ocupadas} de {a.mesas.length} ocupadas</span>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    )}
                                    <FiltroEstado filtro={filtro} onCambiar={setFiltro} conteos={conteos} />
                                </div>

                                <div className="p-2 sm:p-4">
                                    {mesas.length ? (
                                        <div className="grid grid-cols-2 gap-1 sm:grid-cols-3 sm:gap-2 lg:grid-cols-4 2xl:grid-cols-5">
                                            {mesas.map((mesa) => (
                                                <TarjetaMesa key={mesa.id_mesa} mesa={mesa} ahora={ahora} onSeleccionar={seleccionarMesa} onCambiarEstado={cambiarEstado} puedeCambiarEstado={puede.abrir} />
                                            ))}
                                        </div>
                                    ) : (
                                        <EmptyState icon={LayoutGrid} title={filtro === 'TODAS' ? 'Esta área no tiene mesas activas' : 'Ninguna mesa en ese estado'}
                                            action={filtro !== 'TODAS' && <Button variant="secondary" onClick={() => setFiltro('TODAS')}>Ver todas</Button>} />
                                    )}
                                </div>
                            </>
                        )}
                    </Card>

                    <CuentasSinMesa cuentas={cuentasSinMesa} ahora={ahora} rutaCuenta={rutaCuenta} puedeAbrir={puede.abrir} />
                </div>
            </div>
        </AppLayout>
    );
}
