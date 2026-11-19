import { useEffect, useRef, useState } from 'react';
import { Head, router, usePoll } from '@inertiajs/react';
import { Settings2, Volume2, VolumeX } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, toast } from '@/Components/ui';
import { EstadoImpresion, ReimprimirComanda } from '@/Components/Restaurante/ComandasPedido';
import { ESTADO_COMANDA } from '@/Components/Restaurante/estados';
import { cantidad, hora, primerError, tiempoDesde, tituloCuenta } from '@/Components/Restaurante/formato';

const COLUMNAS = ['PENDIENTE', 'EN_PREPARACION', 'LISTA', 'ENTREGADA'];
const ACCIONES = {
    PENDIENTE: [['EN_PREPARACION', 'Iniciar'], ['LISTA', 'Marcar lista']],
    EN_PREPARACION: [['LISTA', 'Marcar lista']],
    LISTA: [['ENTREGADA', 'Entregar'], ['EN_PREPARACION', 'Volver a preparación']],
    ENTREGADA: [],
};

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

function TarjetaComanda({ comanda, puedeGestionar, ahora }) {
    const [enviando, setEnviando] = useState(false);
    const cambiar = (estado) => router.put(`/cocina/comandas/${comanda.id_comanda}/estado`, { estado }, {
        preserveScroll: true,
        onStart: () => setEnviando(true),
        onFinish: () => setEnviando(false),
        onError: (errores) => toast.error('No se cambió el estado', primerError(errores)),
    });

    return (
        <article className="space-y-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <header>
                <h3 className="font-semibold text-slate-900">#{comanda.numero} · {tituloCuenta(comanda.pedido)}</h3>
                <p className="text-xs text-slate-600">{comanda.estacion}{comanda.mozo && ` · ${comanda.mozo}`}</p>
                <p className="text-xs tabular-nums text-slate-600">
                    {hora(comanda.enviada_en)} · {tiempoDesde(comanda.enviada_en, comanda.entregada_en ? Date.parse(comanda.entregada_en) : ahora)}
                </p>
                {comanda.pedido.observacion && <p className="mt-1 text-sm text-amber-800">{comanda.pedido.observacion}</p>}
            </header>
            <ul className="divide-y divide-slate-100 border-y border-slate-100">
                {comanda.items.map((item) => (
                    <li key={item.id_detalle_pedido} className="py-2 text-sm">
                        <p className={item.anulado ? 'text-rose-700 line-through' : 'font-medium text-slate-900'}>{cantidad(item.cantidad)} × {item.nombre}</p>
                        {item.cuenta && <p className="text-xs font-medium text-brand">{tituloCuenta(item.cuenta)}</p>}
                        {item.anulado && <span className="text-xs font-medium text-rose-700">Anulado</span>}
                        {item.opciones.length > 0 && <p className="text-xs text-slate-600">{item.opciones.join(' · ')}</p>}
                        {item.observacion && <p className="text-xs text-amber-800">{item.observacion}</p>}
                    </li>
                ))}
            </ul>
            <EstadoImpresion impresion={comanda.impresion} />
            {puedeGestionar && (
                <div className="flex flex-wrap gap-2">
                    {ACCIONES[comanda.estado].map(([estado, texto], indice) => (
                        <Button key={estado} variant={indice ? 'secondary' : undefined} disabled={enviando} onClick={() => cambiar(estado)}>{texto}</Button>
                    ))}
                    <ReimprimirComanda id={comanda.id_comanda} impresion={comanda.impresion} disabled={enviando} />
                </div>
            )}
        </article>
    );
}

export default function Cocina({ comandas, estaciones, estacion, idSucursal, puedeGestionar, puedeConfigurar }) {
    const [sonido, setSonido] = useState(false);
    const [ahora, setAhora] = useState(Date.now);
    const [sinActualizar, setSinActualizar] = useState(false);
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
            <header className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Cocina</h1>
                    <p className="text-sm text-slate-600" role="status">{sinActualizar ? 'Sin conexión con el tablero. Reintentando…' : 'Actualización automática'}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <label className="flex items-center gap-2 text-sm font-medium">
                        Estación
                        <select value={estacion ?? ''} onChange={(e) => router.get('/cocina', e.target.value === '' ? {} : { estacion: e.target.value }, { preserveState: true, replace: true })} className="min-h-10 rounded-lg border-slate-300 text-sm">
                            <option value="">Todas</option>
                            <option value="0">Cocina general</option>
                            {estaciones.map((e) => <option key={e.id_estacion} value={e.id_estacion}>{e.nombre}</option>)}
                        </select>
                    </label>
                    <Button variant="secondary" icon={sonido ? Volume2 : VolumeX} aria-pressed={sonido} onClick={alternarSonido}>Sonido {sonido ? 'activo' : 'apagado'}</Button>
                    {puedeConfigurar && <Button variant="secondary" icon={Settings2} href="/cocina/estaciones">Estaciones</Button>}
                </div>
            </header>

            <nav aria-label="Estados de comandas" className="mb-3 flex flex-wrap gap-3 text-sm font-medium text-brand sm:hidden">
                {COLUMNAS.map((estado) => <a key={estado} href={`#cocina-${estado}`}>{ESTADO_COMANDA[estado].etiqueta}</a>)}
            </nav>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {COLUMNAS.map((estado) => {
                    const columna = comandas.filter((comanda) => comanda.estado === estado);
                    const { etiqueta, tono } = ESTADO_COMANDA[estado];
                    return (
                        <section key={estado} id={`cocina-${estado}`} className="min-w-0 scroll-mt-20 rounded-xl bg-slate-100 p-3" aria-label={etiqueta}>
                            <h2 className="mb-3 flex items-center justify-between gap-2 font-semibold">{etiqueta}<Badge tone={tono}>{columna.length}</Badge></h2>
                            <div className="space-y-2">
                                {columna.map((comanda) => <TarjetaComanda key={comanda.id_comanda} comanda={comanda} puedeGestionar={puedeGestionar} ahora={ahora} />)}
                                {columna.length === 0 && <p className="py-3 text-sm text-slate-600">Sin comandas</p>}
                            </div>
                        </section>
                    );
                })}
            </div>
        </AppLayout>
    );
}
