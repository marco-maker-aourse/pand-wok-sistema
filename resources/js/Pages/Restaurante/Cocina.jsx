import { useEffect, useState } from 'react';
import { Head, router, usePoll } from '@inertiajs/react';
import {
    Armchair, BellRing, Bike, CheckCheck, ChevronDown, ClipboardList, CookingPot, Flame, HandPlatter, Hourglass,
    Plus, RotateCcw, Settings2, ShoppingBag, StickyNote, Timer, Volume2, VolumeX, Wifi, WifiOff,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, cx, toast } from '@/Components/ui';
import { EstadoImpresion, ReimprimirComanda } from '@/Components/Restaurante/ComandasPedido';
import { minutosDesde, reloj, urgencia, URGENCIA } from '@/Components/Restaurante/cocina';
import { cantidad, hora, primerError, tituloCuenta } from '@/Components/Restaurante/formato';
import useAvisoCocina from '@/hooks/useAvisoCocina';

/** Columnas del tablero, en el orden en que avanza una comanda. */
const COLUMNAS = [
    { estado: 'PENDIENTE', titulo: 'Por hacer', icono: ClipboardList, vacio: 'Sin pedidos por hacer. Los nuevos aparecen aquí con un aviso.' },
    { estado: 'EN_PREPARACION', titulo: 'Cocinando', icono: CookingPot, vacio: 'Toca «Empezar» en una comanda por hacer.' },
    { estado: 'LISTA', titulo: 'Listas', icono: BellRing, vacio: 'Las comandas terminadas esperan aquí al mozo.' },
];

/** Paso principal (botón grande) y alternativo (ícono) de cada estado. */
const PASOS = {
    PENDIENTE: { principal: ['EN_PREPARACION', 'Empezar', CookingPot], alterno: ['LISTA', 'Ya está lista', CheckCheck] },
    EN_PREPARACION: { principal: ['LISTA', 'Lista para servir', BellRing], alterno: null },
    LISTA: { principal: ['ENTREGADA', 'Entregada', HandPlatter], alterno: ['EN_PREPARACION', 'Volver a cocinar', RotateCcw] },
    ENTREGADA: { principal: null, alterno: null },
};

/** La urgencia se lee por ícono y texto, no solo por color. */
const RELOJ = {
    [URGENCIA.A_TIEMPO]: { icono: Timer, clase: 'bg-slate-100 text-slate-700', texto: 'a tiempo' },
    [URGENCIA.EN_ESPERA]: { icono: Hourglass, clase: 'bg-amber-100 text-amber-900', texto: 'en espera' },
    [URGENCIA.ATRASADA]: { icono: Flame, clase: 'bg-rose-600 text-white', texto: 'atrasada' },
};

const ICONO_TIPO = { MESA: Armchair, PARA_LLEVAR: ShoppingBag, DELIVERY: Bike };

function Comanda({ comanda, puedeGestionar, ahora }) {
    const [enviando, setEnviando] = useState(false);
    const cambiar = (estado) => router.put(`/cocina/comandas/${comanda.id_comanda}/estado`, { estado }, {
        preserveScroll: true,
        onStart: () => setEnviando(true),
        onFinish: () => setEnviando(false),
        onError: (errores) => toast.error('No se cambió el estado', primerError(errores)),
    });

    const activa = comanda.estado !== 'ENTREGADA';
    const minutos = minutosDesde(comanda.enviada_en, activa ? ahora : Date.parse(comanda.entregada_en));
    const tiempo = RELOJ[urgencia(minutos)];
    const IconoReloj = activa ? tiempo.icono : CheckCheck;
    const IconoTipo = ICONO_TIPO[comanda.pedido.tipo] ?? ShoppingBag;
    const { principal, alterno } = PASOS[comanda.estado];

    return (
        <article className={cx('rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200', !activa && 'opacity-70')}
            aria-label={`Comanda ${comanda.numero}, ${tituloCuenta(comanda.pedido)}, ${reloj(minutos)}${activa ? `, ${tiempo.texto}` : ''}`}>
            <header className="flex items-start gap-3">
                <span className="text-3xl leading-none font-bold text-slate-900 tabular-nums">#{comanda.numero}</span>
                <div className="min-w-0 flex-1">
                    <p className="flex items-center gap-1.5 text-base font-semibold text-slate-900">
                        <IconoTipo className="h-4 w-4 shrink-0 text-slate-500" aria-hidden="true" />
                        <span className="truncate">{tituloCuenta(comanda.pedido)}</span>
                    </p>
                    <p className="truncate text-sm text-slate-500">
                        {hora(comanda.enviada_en)}{comanda.mozo && `, ${comanda.mozo}`}{comanda.estacion !== 'Cocina general' && `, ${comanda.estacion}`}
                    </p>
                </div>
                <span className={cx('inline-flex shrink-0 items-center gap-1 rounded-full py-1 pr-2.5 pl-2 text-sm font-semibold tabular-nums', activa ? tiempo.clase : 'bg-slate-100 text-slate-500')}>
                    <IconoReloj className="h-4 w-4" aria-hidden="true" />{reloj(minutos)}
                </span>
            </header>

            {comanda.pedido.observacion && (
                <p className="mt-3 flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900">
                    <StickyNote className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />{comanda.pedido.observacion}
                </p>
            )}

            <ul className="mt-3 space-y-3">
                {comanda.items.map((item) => (
                    <li key={item.id_detalle_pedido} className="flex gap-3">
                        <span className={cx('grid h-8 min-w-8 shrink-0 place-items-center rounded-lg px-1.5 text-lg font-bold tabular-nums',
                            item.anulado ? 'bg-rose-50 text-rose-700' : 'bg-slate-900 text-white')}>
                            {cantidad(item.cantidad)}
                        </span>
                        <div className="min-w-0 flex-1 space-y-0.5">
                            <p className={cx('text-lg leading-snug font-semibold', item.anulado ? 'text-rose-700 line-through' : 'text-slate-900')}>{item.nombre}</p>
                            {item.anulado && <p className="text-sm font-semibold text-rose-700">Anulado: no preparar</p>}
                            {item.cuenta && <p className="text-sm font-medium text-brand-ink">Para {tituloCuenta(item.cuenta)}</p>}
                            {item.opciones.map((opcion) => (
                                <p key={opcion} className="flex items-center gap-1 text-base text-slate-700">
                                    <Plus className="h-3.5 w-3.5 shrink-0 text-slate-400" aria-hidden="true" />{opcion}
                                </p>
                            ))}
                            {item.observacion && (
                                <p className="flex w-fit items-start gap-1.5 rounded-lg bg-amber-100 px-2 py-1 text-base font-semibold text-amber-900">
                                    <StickyNote className="mt-1 h-4 w-4 shrink-0" aria-hidden="true" />{item.observacion}
                                </p>
                            )}
                        </div>
                    </li>
                ))}
            </ul>

            <div className="mt-2"><EstadoImpresion impresion={comanda.impresion} /></div>

            {puedeGestionar && activa && (
                <footer className="mt-4 flex gap-2">
                    {principal && (
                        <Button size="lg" icon={principal[2]} disabled={enviando} onClick={() => cambiar(principal[0])} className="h-14 min-w-0 flex-1 text-base">
                            {principal[1]}
                        </Button>
                    )}
                    {alterno && (
                        <Button variant="secondary" size="lg" icon={alterno[2]} disabled={enviando} onClick={() => cambiar(alterno[0])}
                            className="h-14 w-14 shrink-0" aria-label={alterno[1]} title={alterno[1]} />
                    )}
                    <span className="shrink-0 [&>button]:h-14 [&>button]:w-14">
                        <ReimprimirComanda id={comanda.id_comanda} impresion={comanda.impresion} disabled={enviando} soloIcono />
                    </span>
                </footer>
            )}
        </article>
    );
}

export default function Cocina({ comandas, estaciones, estacion, idSucursal, puedeGestionar, puedeConfigurar }) {
    const [ahora, setAhora] = useState(Date.now);
    const [sinConexion, setSinConexion] = useState(false);
    const [vista, setVista] = useState('PENDIENTE');
    const [verEntregadas, setVerEntregadas] = useState(false);
    const aviso = useAvisoCocina(comandas, `${idSucursal}:${estacion ?? ''}`);

    usePoll(5000, {
        only: ['comandas', 'estaciones'],
        onSuccess: () => { setAhora(Date.now()); setSinConexion(false); },
        onError: () => setSinConexion(true),
        onNetworkError: () => { setSinConexion(true); return false; },
    });
    // Los relojes avanzan aunque no lleguen cambios.
    useEffect(() => {
        const intervalo = setInterval(() => setAhora(Date.now()), 30000);
        return () => clearInterval(intervalo);
    }, []);

    const porEstado = (estado) => comandas.filter((comanda) => comanda.estado === estado);
    const entregadas = porEstado('ENTREGADA');

    return (
        <AppLayout title="Cocina">
            <Head title="Cocina" />

            <header className="mb-3 flex items-center gap-2">
                <label className="relative min-w-0 flex-1 sm:flex-none">
                    <span className="sr-only">Estación</span>
                    <select value={estacion ?? ''} onChange={(e) => router.get('/cocina', e.target.value === '' ? {} : { estacion: e.target.value }, { preserveState: true, replace: true })}
                        className="h-11 w-full appearance-none rounded-xl border-slate-200 bg-white pr-9 pl-3 text-sm font-medium shadow-sm sm:w-56">
                        <option value="">Todas las estaciones</option>
                        <option value="0">Cocina general</option>
                        {estaciones.map((e) => <option key={e.id_estacion} value={e.id_estacion}>{e.nombre}</option>)}
                    </select>
                    <ChevronDown className="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                </label>
                <span role="status" title={sinConexion ? 'Sin conexión: reintentando' : 'Actualizándose en vivo'}
                    className={cx('flex h-11 items-center gap-1.5 rounded-xl px-3 text-sm font-medium', sinConexion ? 'bg-rose-50 text-rose-700' : 'text-emerald-700')}>
                    {sinConexion ? <WifiOff className="h-4 w-4" aria-hidden="true" /> : <Wifi className="h-4 w-4" aria-hidden="true" />}
                    <span className={cx(!sinConexion && 'sr-only sm:not-sr-only')}>{sinConexion ? 'Sin conexión' : 'En vivo'}</span>
                </span>
                <Button variant={aviso.activo ? 'secondary' : 'ghost'} icon={aviso.activo ? Volume2 : VolumeX} aria-pressed={aviso.activo} onClick={aviso.alternar}
                    aria-label={aviso.activo ? 'Silenciar aviso de pedidos' : 'Activar aviso de pedidos'} title={aviso.activo ? 'Suena con cada pedido nuevo' : 'Aviso de pedidos silenciado'}
                    className="shrink-0 sm:ml-auto">
                    <span className="hidden sm:inline">{aviso.activo ? 'Aviso activo' : 'Silenciado'}</span>
                </Button>
                {puedeConfigurar && <Button variant="secondary" icon={Settings2} href="/cocina/estaciones" aria-label="Configurar estaciones" title="Configurar estaciones" className="shrink-0" />}
            </header>

            {/* Los navegadores no dejan sonar audio hasta que alguien toca la página. */}
            {aviso.bloqueado && (
                <button type="button" onClick={aviso.habilitar}
                    className="mb-3 flex w-full items-center gap-3 rounded-2xl bg-slate-900 px-4 py-3 text-left text-white">
                    <BellRing className="h-6 w-6 shrink-0 animate-pulse motion-reduce:animate-none" aria-hidden="true" />
                    <span className="min-w-0 flex-1">
                        <span className="block font-semibold">Toca para activar el sonido</span>
                        <span className="block text-sm text-slate-300">Así suena una campana con cada pedido nuevo.</span>
                    </span>
                </button>
            )}

            {/* Celular: una columna a la vez, con su cantidad siempre a la vista. */}
            <nav className="sticky top-0 z-10 -mx-4 mb-3 bg-slate-50/95 px-4 py-2 backdrop-blur lg:hidden" aria-label="Estado de las comandas">
                <div className="grid grid-cols-3 gap-1 rounded-xl bg-slate-200/70 p-1" role="tablist">
                    {COLUMNAS.map(({ estado, titulo, icono: Icono }) => {
                        const total = porEstado(estado).length;
                        return (
                            <button key={estado} type="button" role="tab" aria-selected={vista === estado} onClick={() => setVista(estado)}
                                className={cx('flex h-12 flex-col items-center justify-center rounded-lg text-xs font-semibold', vista === estado ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600')}>
                                <span className="flex items-center gap-1">
                                    <Icono className="h-4 w-4" aria-hidden="true" />
                                    <span className={cx('tabular-nums', estado === 'PENDIENTE' && total > 0 && 'rounded-full bg-rose-600 px-1.5 text-white')}>{total}</span>
                                </span>
                                {titulo}
                            </button>
                        );
                    })}
                </div>
            </nav>

            <div className="grid gap-4 lg:grid-cols-3">
                {COLUMNAS.map(({ estado, titulo, icono: Icono, vacio }) => {
                    const columna = porEstado(estado);
                    return (
                        <section key={estado} aria-label={titulo} className={cx('min-w-0', vista !== estado && 'hidden lg:block')}>
                            <h2 className="mb-2 hidden items-center gap-2 text-lg font-semibold text-slate-900 lg:flex">
                                <Icono className="h-5 w-5 text-slate-500" aria-hidden="true" />{titulo}
                                <span className="text-base font-medium text-slate-500 tabular-nums">{columna.length}</span>
                            </h2>
                            <div className="space-y-3">
                                {columna.map((comanda) => <Comanda key={comanda.id_comanda} comanda={comanda} puedeGestionar={puedeGestionar} ahora={ahora} />)}
                                {columna.length === 0 && (
                                    <div className="flex flex-col items-center gap-2 rounded-2xl bg-white/60 px-6 py-10 text-center text-sm text-slate-500">
                                        <Icono className="h-8 w-8 text-slate-300" aria-hidden="true" />{vacio}
                                    </div>
                                )}
                            </div>
                        </section>
                    );
                })}
            </div>

            {entregadas.length > 0 && (
                <section className="mt-6" aria-label="Entregadas">
                    <button type="button" onClick={() => setVerEntregadas(!verEntregadas)} aria-expanded={verEntregadas}
                        className="flex h-11 items-center gap-2 rounded-xl px-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
                        <HandPlatter className="h-4 w-4" aria-hidden="true" />
                        Entregadas en las últimas 12 horas ({entregadas.length})
                        <ChevronDown className={cx('h-4 w-4 transition-transform', verEntregadas && 'rotate-180')} aria-hidden="true" />
                    </button>
                    {verEntregadas && (
                        <div className="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {entregadas.map((comanda) => <Comanda key={comanda.id_comanda} comanda={comanda} puedeGestionar={puedeGestionar} ahora={ahora} />)}
                        </div>
                    )}
                </section>
            )}
        </AppLayout>
    );
}
