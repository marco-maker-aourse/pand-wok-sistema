import { useEffect, useRef, useState } from 'react';
import { Head, router, usePoll } from '@inertiajs/react';
import { CheckCheck, ChevronDown, Settings2, Undo2, Volume2, VolumeX, Wifi, WifiOff } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, cx, toast } from '@/Components/ui';
import { EstadoImpresion, ReimprimirComanda } from '@/Components/Restaurante/ComandasPedido';
import { cantidad, hora, primerError, tituloCuenta } from '@/Components/Restaurante/formato';

/** Columnas del tablero, en el orden en que avanza una comanda. */
const COLUMNAS = [
    { estado: 'PENDIENTE', titulo: 'Por hacer', vacio: 'Nada por hacer. Los pedidos nuevos aparecen aquí.' },
    { estado: 'EN_PREPARACION', titulo: 'Cocinando', vacio: 'Toca «Empezar» en una comanda por hacer.' },
    { estado: 'LISTA', titulo: 'Listas', vacio: 'Las comandas terminadas esperan aquí al mozo.' },
];

/** Paso principal (botón grande) y alternativo de cada estado. */
const PASOS = {
    PENDIENTE: { principal: ['EN_PREPARACION', 'Empezar'], alterno: ['LISTA', 'Ya está lista'] },
    EN_PREPARACION: { principal: ['LISTA', 'Lista para servir'], alterno: null },
    LISTA: { principal: ['ENTREGADA', 'Entregada'], alterno: ['EN_PREPARACION', 'Volver a cocinar'] },
    ENTREGADA: { principal: null, alterno: null },
};

/** Minutos de espera desde que la comanda llegó a cocina. */
const minutosDesde = (iso, ahora) => (iso ? Math.max(0, Math.floor((ahora - Date.parse(iso)) / 60000)) : 0);

/** Lo atrasado se ve sin leer: hasta 8 min a tiempo, hasta 15 min atento, después atrasado. */
function urgencia(minutos) {
    if (minutos >= 15) return { franja: 'bg-rose-600', reloj: 'bg-rose-600 text-white', texto: 'atrasada' };
    if (minutos >= 8) return { franja: 'bg-amber-400', reloj: 'bg-amber-100 text-amber-900', texto: 'en espera' };
    return { franja: 'bg-emerald-500', reloj: 'bg-slate-100 text-slate-700', texto: 'a tiempo' };
}

const reloj = (minutos) => (minutos < 60 ? `${minutos} min` : `${Math.floor(minutos / 60)} h ${String(minutos % 60).padStart(2, '0')}`);

function sonar(contexto) {
    if (contexto?.state !== 'running') return;
    const tono = contexto.createOscillator();
    const volumen = contexto.createGain();
    const inicio = contexto.currentTime;
    tono.frequency.value = 680;
    volumen.gain.setValueAtTime(0.12, inicio);
    volumen.gain.exponentialRampToValueAtTime(0.001, inicio + 0.25);
    tono.connect(volumen).connect(contexto.destination);
    tono.start();
    tono.stop(inicio + 0.25);
}

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
    const nivel = urgencia(minutos);
    const { principal, alterno } = PASOS[comanda.estado];

    return (
        <article className={cx('overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200', !activa && 'opacity-70')}
            aria-label={`Comanda ${comanda.numero}, ${tituloCuenta(comanda.pedido)}, ${reloj(minutos)} ${nivel.texto}`}>
            {activa && <div className={cx('h-1.5', nivel.franja)} aria-hidden="true" />}

            <header className="flex items-start gap-3 px-4 pt-3">
                <span className="text-3xl leading-none font-bold text-slate-900 tabular-nums">#{comanda.numero}</span>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-base font-semibold text-slate-900">{tituloCuenta(comanda.pedido)}</p>
                    <p className="truncate text-sm text-slate-500">
                        {hora(comanda.enviada_en)}{comanda.mozo && `, ${comanda.mozo}`}{comanda.estacion !== 'Cocina general' && `, ${comanda.estacion}`}
                    </p>
                </div>
                <span className={cx('shrink-0 rounded-full px-2.5 py-1 text-sm font-semibold tabular-nums', activa ? nivel.reloj : 'bg-slate-100 text-slate-500')}>
                    {reloj(minutos)}
                </span>
            </header>

            {comanda.pedido.observacion && (
                <p className="mx-4 mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900 ring-1 ring-amber-200">{comanda.pedido.observacion}</p>
            )}

            <ul className="mt-2 divide-y divide-slate-100 px-4">
                {comanda.items.map((item) => (
                    <li key={item.id_detalle_pedido} className="flex gap-3 py-2.5">
                        <span className={cx('grid h-8 min-w-8 shrink-0 place-items-center rounded-lg px-1.5 text-lg font-bold tabular-nums',
                            item.anulado ? 'bg-rose-50 text-rose-700' : 'bg-slate-900 text-white')}>
                            {cantidad(item.cantidad)}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className={cx('text-lg leading-snug font-semibold', item.anulado ? 'text-rose-700 line-through' : 'text-slate-900')}>{item.nombre}</p>
                            {item.anulado && <p className="text-sm font-semibold text-rose-700">Anulado: no preparar</p>}
                            {item.cuenta && <p className="text-sm font-medium text-brand-ink">Para {tituloCuenta(item.cuenta)}</p>}
                            {item.opciones.map((opcion) => <p key={opcion} className="text-base text-slate-700">+ {opcion}</p>)}
                            {item.observacion && (
                                <p className="mt-1 inline-block rounded-md bg-amber-100 px-2 py-0.5 text-base font-semibold text-amber-900">{item.observacion}</p>
                            )}
                        </div>
                    </li>
                ))}
            </ul>

            <div className="px-4"><EstadoImpresion impresion={comanda.impresion} /></div>

            {puedeGestionar && activa && (
                <footer className="flex gap-2 p-3">
                    {principal && (
                        <Button size="lg" disabled={enviando} onClick={() => cambiar(principal[0])} className="h-14 min-w-0 flex-1 text-base">
                            {principal[1]}
                        </Button>
                    )}
                    {alterno && (
                        <Button variant="secondary" size="lg" icon={alterno[0] === 'EN_PREPARACION' ? Undo2 : CheckCheck} disabled={enviando} onClick={() => cambiar(alterno[0])}
                            className="h-14 w-14 shrink-0" aria-label={alterno[1]} title={alterno[1]} />
                    )}
                    <span className="shrink-0 [&>button]:h-14 [&>button]:w-14"><ReimprimirComanda id={comanda.id_comanda} impresion={comanda.impresion} disabled={enviando} soloIcono /></span>
                </footer>
            )}
        </article>
    );
}

export default function Cocina({ comandas, estaciones, estacion, idSucursal, puedeGestionar, puedeConfigurar }) {
    const [sonido, setSonido] = useState(false);
    const [ahora, setAhora] = useState(Date.now);
    const [sinConexion, setSinConexion] = useState(false);
    const [vista, setVista] = useState('PENDIENTE');
    const [verEntregadas, setVerEntregadas] = useState(false);
    const audio = useRef(null);
    const vistos = useRef(null);

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

    useEffect(() => {
        const filtro = `${idSucursal}:${estacion}`;
        const anteriores = vistos.current;
        const ids = new Set(comandas.map((comanda) => comanda.id_comanda));
        if (sonido && anteriores?.filtro === filtro && comandas.some((comanda) => comanda.estado === 'PENDIENTE' && !anteriores.ids.has(comanda.id_comanda))) {
            sonar(audio.current);
        }
        vistos.current = { filtro, ids };
    }, [comandas, estacion, idSucursal, sonido]);

    useEffect(() => () => { audio.current?.close().catch(() => {}); }, []);

    const alternarSonido = async () => {
        if (sonido) {
            setSonido(false);
            return;
        }
        try {
            audio.current ??= new (window.AudioContext || window.webkitAudioContext)();
            await audio.current.resume();
            setSonido(true);
            sonar(audio.current);
        } catch {
            toast.error('El navegador no pudo activar el sonido');
        }
    };

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
                <Button variant={sonido ? 'primary' : 'secondary'} icon={sonido ? Volume2 : VolumeX} aria-pressed={sonido} onClick={alternarSonido}
                    aria-label={sonido ? 'Apagar aviso sonoro' : 'Activar aviso sonoro'} title={sonido ? 'Aviso sonoro activo' : 'Aviso sonoro apagado'} className="shrink-0 sm:ml-auto">
                    <span className="hidden sm:inline">{sonido ? 'Sonido activo' : 'Activar sonido'}</span>
                </Button>
                {puedeConfigurar && <Button variant="secondary" icon={Settings2} href="/cocina/estaciones" aria-label="Configurar estaciones" title="Configurar estaciones" className="shrink-0" />}
            </header>

            {/* Celular: una columna a la vez, con su cantidad siempre a la vista. */}
            <nav className="sticky top-0 z-10 -mx-4 mb-3 bg-slate-50/95 px-4 py-2 backdrop-blur lg:hidden" aria-label="Estado de las comandas">
                <div className="grid grid-cols-3 gap-1 rounded-xl bg-slate-200/70 p-1" role="tablist">
                    {COLUMNAS.map(({ estado, titulo }) => {
                        const total = porEstado(estado).length;
                        return (
                            <button key={estado} type="button" role="tab" aria-selected={vista === estado} onClick={() => setVista(estado)}
                                className={cx('flex h-11 items-center justify-center gap-1.5 rounded-lg text-sm font-semibold', vista === estado ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600')}>
                                {titulo}
                                <span className={cx('grid h-6 min-w-6 place-items-center rounded-full px-1.5 text-xs tabular-nums',
                                    estado === 'PENDIENTE' && total > 0 ? 'bg-rose-600 text-white' : 'bg-slate-300/70 text-slate-700')}>{total}</span>
                            </button>
                        );
                    })}
                </div>
            </nav>

            <div className="grid gap-4 lg:grid-cols-3">
                {COLUMNAS.map(({ estado, titulo, vacio }) => {
                    const columna = porEstado(estado);
                    return (
                        <section key={estado} aria-label={titulo} className={cx('min-w-0', vista !== estado && 'hidden lg:block')}>
                            <h2 className="mb-2 hidden items-baseline gap-2 text-lg font-semibold text-slate-900 lg:flex">
                                {titulo}<span className="text-base font-medium text-slate-500 tabular-nums">{columna.length}</span>
                            </h2>
                            <div className="space-y-3">
                                {columna.map((comanda) => <Comanda key={comanda.id_comanda} comanda={comanda} puedeGestionar={puedeGestionar} ahora={ahora} />)}
                                {columna.length === 0 && <p className="rounded-2xl border-2 border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">{vacio}</p>}
                            </div>
                        </section>
                    );
                })}
            </div>

            {entregadas.length > 0 && (
                <section className="mt-6" aria-label="Entregadas">
                    <button type="button" onClick={() => setVerEntregadas(!verEntregadas)} aria-expanded={verEntregadas}
                        className="flex h-11 items-center gap-2 rounded-xl px-2 text-sm font-medium text-slate-600 hover:bg-slate-100">
                        <ChevronDown className={cx('h-4 w-4 transition-transform', verEntregadas && 'rotate-180')} aria-hidden="true" />
                        Entregadas en las últimas 12 horas ({entregadas.length})
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
