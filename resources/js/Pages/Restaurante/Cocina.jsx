import { useEffect, useRef, useState } from 'react';
import { Head, router, usePoll } from '@inertiajs/react';
import { ChefHat, RefreshCw, Settings2, Volume2, VolumeX, WifiOff } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, PageHeader, cx, toast } from '@/Components/ui';
import TarjetaComanda from '@/Components/Restaurante/cocina/TarjetaComanda';
import { ESTADO_COMANDA } from '@/Components/Restaurante/estados';

const COLUMNAS = ['PENDIENTE', 'EN_PREPARACION', 'LISTA', 'ENTREGADA'];
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

export default function Cocina({ comandas, estaciones, estacion, idSucursal, puedeGestionar, puedeConfigurar }) {
    const [sonido, setSonido] = useState(false);
    const [ahora, setAhora] = useState(Date.now);
    const [sinActualizar, setSinActualizar] = useState(false);
    const [columnaMovil, setColumnaMovil] = useState('PENDIENTE');
    const audio = useRef(null);
    const vistos = useRef(null);
    usePoll(5000, {
        only: ['comandas', 'estaciones'],
        onSuccess: () => { setAhora(Date.now()); setSinActualizar(false); },
        onError: () => setSinActualizar(true),
        onNetworkError: () => { setSinActualizar(true); return false; },
    });

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

    return (
        <AppLayout title="Cocina">
            <Head title="Cocina" />
            <PageHeader
                title="Cocina"
                icon={ChefHat}
                description={(
                    <span role="status" className={cx('inline-flex items-center gap-1.5', sinActualizar && 'font-medium text-rose-700')}>
                        {sinActualizar ? <WifiOff className="h-3.5 w-3.5" aria-hidden="true" /> : <RefreshCw className="h-3.5 w-3.5" aria-hidden="true" />}
                        {sinActualizar ? 'Sin conexión con el tablero. Reintentando…' : 'Se actualiza solo cada 5 segundos'}
                    </span>
                )}
                actions={(
                    <>
                        <label className="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <span className="sr-only sm:not-sr-only">Estación</span>
                            <select value={estacion ?? ''} onChange={(e) => router.get('/cocina', e.target.value === '' ? {} : { estacion: e.target.value }, { preserveState: true, replace: true })} className="min-h-10 rounded-lg text-sm">
                                <option value="">Todas las estaciones</option>
                                <option value="0">Cocina general</option>
                                {estaciones.map((e) => <option key={e.id_estacion} value={e.id_estacion}>{e.nombre}</option>)}
                            </select>
                        </label>
                        <Button variant="secondary" icon={sonido ? Volume2 : VolumeX} aria-pressed={sonido} onClick={alternarSonido}>
                            {sonido ? 'Sonido activo' : 'Activar sonido'}
                        </Button>
                        {puedeConfigurar && <Button variant="secondary" icon={Settings2} href="/cocina/estaciones">Estaciones</Button>}
                    </>
                )}
            />

            {/* En celular se ve una columna a la vez, elegida con estas pestañas. */}
            <div className="mt-4 mb-3 grid grid-cols-4 gap-1 rounded-xl bg-slate-200/60 p-1 sm:hidden" role="tablist" aria-label="Estados de comandas">
                {COLUMNAS.map((estado) => {
                    const total = comandas.filter((c) => c.estado === estado).length;
                    return (
                        <button key={estado} type="button" role="tab" aria-selected={columnaMovil === estado} onClick={() => setColumnaMovil(estado)}
                            className={cx('flex h-12 flex-col items-center justify-center rounded-lg text-xs font-medium leading-tight', columnaMovil === estado ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600')}>
                            <span className="truncate">{ESTADO_COMANDA[estado].etiqueta}</span>
                            <span className="font-mono tabular-nums">{total}</span>
                        </button>
                    );
                })}
            </div>

            <div className="grid gap-3 sm:mt-5 sm:grid-cols-2 xl:grid-cols-4">
                {COLUMNAS.map((estado) => {
                    const columna = comandas.filter((comanda) => comanda.estado === estado);
                    const { etiqueta, tono } = ESTADO_COMANDA[estado];
                    return (
                        <section key={estado} className={cx('min-w-0 rounded-2xl bg-slate-100/80 p-2.5 ring-1 ring-slate-200/70', columnaMovil !== estado && 'hidden sm:block')} aria-label={etiqueta}>
                            <h2 className="mb-2.5 flex items-center justify-between gap-2 px-1 text-sm font-semibold text-slate-800">
                                {etiqueta}
                                <Badge tone={tono}>{columna.length}</Badge>
                            </h2>
                            <div className="space-y-2.5">
                                {columna.map((comanda) => <TarjetaComanda key={comanda.id_comanda} comanda={comanda} puedeGestionar={puedeGestionar} ahora={ahora} />)}
                                {columna.length === 0 && <p className="rounded-xl border border-dashed border-slate-300 py-6 text-center text-sm text-slate-500">Sin comandas</p>}
                            </div>
                        </section>
                    );
                })}
            </div>
        </AppLayout>
    );
}
