import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { usePage } from '@inertiajs/react';
import { Bot, Check, Copy, Loader2, RotateCcw, Send, Square, Trash2, X } from 'lucide-react';

const MAX_CARACTERES = 500;
const MAX_HISTORIAL = 10;
const SUGERENCIAS = ['Resumen de ventas de hoy', 'Esta semana vs. la pasada', 'Lo más vendido del mes', '¿Qué productos están sin stock?', 'Estado de mi caja'];

const nuevoId = () => `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;

/* ------------------------------------------------------------------ Persistencia
 * La conversación se guarda en sessionStorage por usuario: sobrevive a la navegación
 * entre pantallas y se borra al cerrar la pestaña. Nunca se comparte entre usuarios. */
function claveConversacion(idUsuario) {
    return `asistente.conversacion.v2.${idUsuario ?? 'anonimo'}`;
}

function cargarConversacion(clave) {
    try {
        const datos = JSON.parse(window.sessionStorage.getItem(clave) ?? '[]');
        return Array.isArray(datos) ? datos.filter((m) => m && (m.rol === 'user' || m.rol === 'bot') && typeof m.texto === 'string') : [];
    } catch {
        return [];
    }
}

function guardarConversacion(clave, mensajes) {
    try {
        // Solo mensajes terminados: una respuesta a medias no se restaura.
        const terminados = mensajes.filter((m) => m.estado !== 'escribiendo').slice(-40);
        window.sessionStorage.setItem(clave, JSON.stringify(terminados));
    } catch {
        /* Almacenamiento no disponible: la conversación vive solo en memoria. */
    }
}

/* ------------------------------------------------------------------ Red */
function tokenXsrf() {
    const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

function mensajeDeError(status, cuerpo) {
    if (status === 419) return 'Tu sesión expiró. Recarga la página para seguir consultando.';
    if (status === 429) return 'Hiciste varias consultas seguidas. Espera un momento e inténtalo de nuevo.';
    if (status === 403) return 'No tienes permiso para usar el asistente.';
    if (status === 422) return cuerpo?.message || 'La consulta no es válida.';
    return cuerpo?.respuesta || 'No fue posible procesar la consulta. Inténtalo nuevamente.';
}

/**
 * Envía la consulta y procesa el stream NDJSON del servidor.
 * Llama a onEvento con { tipo: 'estado'|'texto'|'reiniciar'|'fin'|'error', ... }.
 */
async function consultarEnStream({ mensaje, historial, signal, onEvento }) {
    const respuesta = await fetch('/ia/chat', {
        method: 'POST',
        credentials: 'same-origin',
        signal,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/x-ndjson, application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': tokenXsrf(),
        },
        body: JSON.stringify({ mensaje, historial }),
    });

    if (!respuesta.ok) {
        let cuerpo = null;
        try { cuerpo = await respuesta.json(); } catch { /* sin cuerpo JSON */ }
        onEvento({ tipo: 'error', texto: mensajeDeError(respuesta.status, cuerpo) });
        return;
    }

    const lector = respuesta.body.getReader();
    const decodificador = new TextDecoder();
    let pendiente = '';
    let terminado = false;

    const procesar = (linea) => {
        if (!linea.trim()) return;
        try {
            const evento = JSON.parse(linea);
            if (evento.tipo === 'fin' || evento.tipo === 'error') terminado = true;
            onEvento(evento);
        } catch { /* línea incompleta o inválida: se ignora */ }
    };

    for (;;) {
        const { value, done } = await lector.read();
        if (done) break;
        pendiente += decodificador.decode(value, { stream: true });
        const lineas = pendiente.split('\n');
        pendiente = lineas.pop() ?? '';
        lineas.forEach(procesar);
    }
    procesar(pendiente);
    if (!terminado) onEvento({ tipo: 'error', texto: 'La conexión se interrumpió antes de terminar la respuesta.' });
}

/* ------------------------------------------------------------------ Componente */
export default function AsistenteOperativo() {
    const { auth } = usePage().props;
    const clave = claveConversacion(auth?.user?.id_usuario);

    const [abierto, setAbierto] = useState(false);
    const [consulta, setConsulta] = useState('');
    const [mensajes, setMensajes] = useState(() => (typeof window === 'undefined' ? [] : cargarConversacion(clave)));
    const [estado, setEstado] = useState(null);
    const controladorRef = useRef(null);
    const conversacionRef = useRef(null);
    const entradaRef = useRef(null);
    const consultando = estado !== null;

    useEffect(() => { guardarConversacion(clave, mensajes); }, [clave, mensajes]);

    useEffect(() => {
        const contenedor = conversacionRef.current;
        if (abierto && contenedor) contenedor.scrollTo({ top: contenedor.scrollHeight, behavior: 'smooth' });
    }, [abierto, mensajes, estado]);

    useEffect(() => {
        if (abierto) entradaRef.current?.focus();
    }, [abierto]);

    // Cerrar con Escape; cancelar la consulta en curso al desmontar.
    useEffect(() => {
        if (!abierto) return undefined;
        const alPresionar = (e) => { if (e.key === 'Escape') setAbierto(false); };
        window.addEventListener('keydown', alPresionar);
        return () => window.removeEventListener('keydown', alPresionar);
    }, [abierto]);
    useEffect(() => () => controladorRef.current?.abort(), []);

    const actualizarMensaje = (id, cambios) => setMensajes((lista) => lista.map((m) => (m.id === id ? { ...m, ...(typeof cambios === 'function' ? cambios(m) : cambios) } : m)));

    const preguntar = useCallback(async (pregunta, base = null) => {
        const texto = pregunta.trim().slice(0, MAX_CARACTERES);
        if (!texto || controladorRef.current) return;

        const previos = (base ?? mensajes)
            .filter((m) => m.estado !== 'error' && m.texto)
            .slice(-MAX_HISTORIAL)
            .map(({ rol, texto: t }) => ({ rol, texto: t.slice(0, 4000) }));

        const idRespuesta = nuevoId();
        setConsulta('');
        setMensajes([...(base ?? mensajes), { id: nuevoId(), rol: 'user', texto }, { id: idRespuesta, rol: 'bot', texto: '', estado: 'escribiendo' }]);
        setEstado('Analizando tu pregunta');

        const controlador = new AbortController();
        controladorRef.current = controlador;

        try {
            await consultarEnStream({
                mensaje: texto,
                historial: previos,
                signal: controlador.signal,
                onEvento: (evento) => {
                    if (evento.tipo === 'estado') setEstado(evento.texto);
                    else if (evento.tipo === 'texto') { setEstado(null); actualizarMensaje(idRespuesta, (m) => ({ texto: m.texto + evento.delta })); }
                    else if (evento.tipo === 'reiniciar') actualizarMensaje(idRespuesta, { texto: '' });
                    else if (evento.tipo === 'fin') actualizarMensaje(idRespuesta, { texto: evento.texto, estado: 'ok' });
                    else if (evento.tipo === 'error') actualizarMensaje(idRespuesta, { texto: evento.texto, estado: 'error', pregunta: texto });
                },
            });
        } catch (error) {
            if (error?.name === 'AbortError') {
                actualizarMensaje(idRespuesta, (m) => ({ texto: m.texto ? `${m.texto}\n\n_Respuesta detenida._` : 'Respuesta detenida.', estado: m.texto ? 'ok' : 'error', pregunta: texto }));
            } else {
                actualizarMensaje(idRespuesta, { texto: 'No hay conexión con el servidor. Revisa tu internet e inténtalo de nuevo.', estado: 'error', pregunta: texto });
            }
        } finally {
            controladorRef.current = null;
            setEstado(null);
            entradaRef.current?.focus();
        }
    }, [mensajes]);

    const detener = () => controladorRef.current?.abort();

    // Reintenta la pregunta de una respuesta fallida, quitando ese intento del historial.
    const reintentar = (mensaje) => {
        const indice = mensajes.findIndex((m) => m.id === mensaje.id);
        const base = mensajes.slice(0, Math.max(0, indice - 1));
        preguntar(mensaje.pregunta, base);
    };

    const limpiar = () => {
        detener();
        setMensajes([]);
    };

    const interfaz = <>
        {!abierto && <button
            type="button"
            onClick={() => setAbierto(true)}
            aria-label="Abrir asistente"
            title="Abrir asistente"
            className="volumen-marca fixed z-[70] grid h-16 w-16 place-items-center rounded-full border border-white/80 text-on-brand shadow-[0_18px_35px_rgba(15,23,42,0.24)] focus:outline-none focus-visible:ring-4 focus-visible:ring-brand/25 hover:brightness-105"
            style={{ right: 'max(1.25rem, calc(env(safe-area-inset-right) + 1rem))', bottom: 'calc(var(--alto-barra-inferior, env(safe-area-inset-bottom)) + 1rem)' }}
        >
            <span aria-hidden="true" className="absolute inset-0 rounded-full bg-white/10" />
            <Bot aria-hidden="true" className="relative h-8 w-8" strokeWidth={1.8} />
            {consultando && <span aria-hidden="true" className="absolute top-1 right-1 h-3 w-3 animate-pulse rounded-full bg-amber-400 ring-2 ring-white" />}
        </button>}

        {abierto && <div className="fixed inset-0 z-[80]" onMouseDown={() => setAbierto(false)}>
            <section
                role="dialog"
                aria-label="Asistente"
                className="absolute bottom-4 left-3 right-3 h-[min(600px,calc(100dvh-2rem))] w-auto overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_24px_48px_-12px_rgba(15,23,42,0.28)] sm:bottom-5 sm:left-auto sm:right-5 sm:w-[440px]"
                onMouseDown={(event) => event.stopPropagation()}
            >
                <div className="flex h-full flex-col">
                    <Cabecera onCerrar={() => setAbierto(false)} onLimpiar={limpiar} hayMensajes={mensajes.length > 0} />
                    <div ref={conversacionRef} className="flex-1 space-y-3 overflow-y-auto bg-slate-50/40 p-4 text-[13px] [scrollbar-width:thin]" aria-live="polite">
                        {mensajes.length === 0
                            ? <Bienvenida nombre={auth?.user?.nombres} onPreguntar={preguntar} />
                            : mensajes.map((mensaje) => <Mensaje key={mensaje.id} mensaje={mensaje} estado={mensaje.estado === 'escribiendo' ? estado : null} onReintentar={() => reintentar(mensaje)} bloqueado={consultando} />)}
                    </div>
                    <Formulario entradaRef={entradaRef} consulta={consulta} setConsulta={setConsulta} consultando={consultando} onPreguntar={preguntar} onDetener={detener} />
                </div>
            </section>
        </div>}
    </>;

    // El portal evita que animaciones, scroll u overflow de una pantalla oculten el asistente.
    return typeof document === 'undefined' ? null : createPortal(interfaz, document.body);
}

function Cabecera({ onCerrar, onLimpiar, hayMensajes }) {
    return <header className="flex items-center justify-between border-b border-slate-100 bg-slate-50 p-4">
        <div className="flex items-center gap-2.5">
            <div className="volumen-marca grid h-9 w-9 place-items-center rounded-xl text-on-brand"><Bot className="h-5 w-5" aria-hidden="true" /></div>
            <div>
                <h3 className="text-sm font-semibold text-slate-900">Asistente</h3>
                <p className="text-xs text-slate-500">Consulta tus datos en lenguaje natural</p>
            </div>
        </div>
        <div className="flex items-center gap-1">
            {hayMensajes && <button type="button" onClick={onLimpiar} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200/60 hover:text-slate-600" title="Nueva conversación" aria-label="Nueva conversación"><Trash2 className="h-4 w-4" /></button>}
            <button type="button" onClick={onCerrar} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200/60 hover:text-slate-600" title="Cerrar (Esc)" aria-label="Cerrar asistente"><X className="h-4 w-4" /></button>
        </div>
    </header>;
}

function Bienvenida({ nombre, onPreguntar }) {
    const primerNombre = nombre?.split(' ')[0];
    return <div className="flex h-full flex-col justify-end gap-4 pb-1">
        <div className="rounded-2xl bg-white px-4 py-3.5 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
            <p className="font-semibold text-slate-900">Hola{primerNombre ? `, ${primerNombre}` : ''}.</p>
            <p className="mt-1 leading-relaxed text-slate-600">Pregúntame con tus palabras sobre ventas, stock, caja, comprobantes, cobros o compras de esta sucursal. Solo consulto datos: no modifico nada.</p>
        </div>
        <div>
            <p className="mb-2 text-xs font-medium text-slate-500">Prueba con</p>
            <div className="flex flex-wrap gap-1.5">
                {SUGERENCIAS.map((s) => <button key={s} type="button" onClick={() => onPreguntar(s)} className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-brand-border hover:bg-brand-soft/50">{s}</button>)}
            </div>
        </div>
    </div>;
}

function Mensaje({ mensaje, estado, onReintentar, bloqueado }) {
    const esUsuario = mensaje.rol === 'user';
    if (esUsuario) {
        return <div className="flex justify-end"><div className="volumen-marca max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-br-md px-3.5 py-2.5 leading-relaxed text-on-brand">{mensaje.texto}</div></div>;
    }

    const error = mensaje.estado === 'error';
    const escribiendo = mensaje.estado === 'escribiendo';
    return <div className="group flex justify-start">
        <div className="max-w-[90%]">
            <div className={`rounded-2xl rounded-bl-md px-3.5 py-2.5 leading-relaxed ${error ? 'bg-rose-50 text-rose-800 ring-1 ring-rose-200' : 'bg-white text-slate-800 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]'}`}>
                {mensaje.texto
                    ? <TextoFormateado texto={mensaje.texto} cursor={escribiendo} />
                    : <span className="flex items-center gap-2 text-slate-500"><Loader2 className="h-3.5 w-3.5 animate-spin" aria-hidden="true" />{estado ?? 'Pensando'}…</span>}
            </div>
            {!escribiendo && <div className="mt-1 flex gap-1 pl-1">
                {error && mensaje.pregunta && <button type="button" onClick={onReintentar} disabled={bloqueado} className="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-200/60 disabled:opacity-50"><RotateCcw className="h-3 w-3" aria-hidden="true" />Reintentar</button>}
                {!error && <BotonCopiar texto={mensaje.texto} />}
            </div>}
        </div>
    </div>;
}

function BotonCopiar({ texto }) {
    const [copiado, setCopiado] = useState(false);
    const copiar = async () => {
        try {
            await navigator.clipboard.writeText(texto.replace(/\*\*/g, ''));
            setCopiado(true);
            window.setTimeout(() => setCopiado(false), 1500);
        } catch { /* portapapeles no disponible */ }
    };
    return <button type="button" onClick={copiar} className="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-xs text-slate-400 opacity-0 transition hover:bg-slate-200/60 hover:text-slate-600 focus-visible:opacity-100 group-hover:opacity-100" aria-label="Copiar respuesta">
        {copiado ? <Check className="h-3 w-3" aria-hidden="true" /> : <Copy className="h-3 w-3" aria-hidden="true" />}{copiado ? 'Copiado' : 'Copiar'}
    </button>;
}

function Formulario({ entradaRef, consulta, setConsulta, consultando, onPreguntar, onDetener }) {
    // El cuadro crece con el texto hasta ~5 líneas.
    useEffect(() => {
        const entrada = entradaRef.current;
        if (!entrada) return;
        entrada.style.height = 'auto';
        entrada.style.height = `${Math.min(entrada.scrollHeight, 120)}px`;
    }, [consulta, entradaRef]);

    const enviar = (event) => {
        event.preventDefault();
        if (!consultando) onPreguntar(consulta);
    };

    const restantes = MAX_CARACTERES - consulta.length;
    return <form onSubmit={enviar} className="border-t border-slate-100 bg-white p-3">
        <div className="flex items-end gap-2">
            <textarea
                ref={entradaRef}
                rows={1}
                value={consulta}
                maxLength={MAX_CARACTERES}
                onChange={(event) => setConsulta(event.target.value)}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing) enviar(event);
                }}
                placeholder="Escribe tu consulta…"
                aria-label="Consulta para el asistente"
                className="max-h-[120px] min-h-10 flex-1 resize-none rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-[13px] leading-5"
            />
            {consultando
                ? <button type="button" onClick={onDetener} className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-800 text-white hover:bg-slate-700" aria-label="Detener respuesta" title="Detener"><Square className="h-3.5 w-3.5 fill-current" /></button>
                : <button type="submit" disabled={!consulta.trim()} className="volumen-marca grid h-10 w-10 shrink-0 place-items-center rounded-xl text-on-brand hover:brightness-105 disabled:opacity-50" aria-label="Enviar consulta"><Send className="h-4 w-4" /></button>}
        </div>
        <p className="mt-1.5 flex justify-between px-1 text-[11px] text-slate-400">
            <span>Enter para enviar · Shift+Enter para salto de línea</span>
            {restantes <= 80 && <span className={restantes <= 20 ? 'text-amber-600' : ''}>{restantes}</span>}
        </p>
    </form>;
}

/* ------------------------------------------------------------------ Formato
 * Subconjunto seguro de Markdown: párrafos, listas (- o 1.), **negrita** y _cursiva_.
 * No se interpreta HTML: todo se renderiza como texto de React. */
function Enlinea({ texto }) {
    return texto.split(/(\*\*[^*]+\*\*|_[^_\s][^_]*_)/g).map((parte, i) => {
        if (parte.startsWith('**') && parte.endsWith('**') && parte.length > 4) return <strong key={i} className="font-semibold text-slate-900">{parte.slice(2, -2)}</strong>;
        if (parte.startsWith('_') && parte.endsWith('_') && parte.length > 2) return <em key={i} className="text-slate-500">{parte.slice(1, -1)}</em>;
        return <span key={i}>{parte}</span>;
    });
}

function TextoFormateado({ texto, cursor }) {
    const bloques = [];
    for (const linea of texto.split('\n')) {
        const item = linea.match(/^\s*(?:[-*•]|(\d+)[.)])\s+(.*)$/);
        const ultimo = bloques[bloques.length - 1];
        if (item) {
            const ordenada = item[1] !== undefined;
            if (ultimo?.tipo === 'lista' && ultimo.ordenada === ordenada) ultimo.items.push(item[2]);
            else bloques.push({ tipo: 'lista', ordenada, items: [item[2]] });
        } else if (linea.trim() === '') {
            bloques.push({ tipo: 'espacio' });
        } else if (ultimo?.tipo === 'parrafo') {
            ultimo.lineas.push(linea.replace(/^#+\s*/, ''));
        } else {
            bloques.push({ tipo: 'parrafo', lineas: [linea.replace(/^#+\s*/, '')] });
        }
    }

    const visibles = bloques.filter((b) => b.tipo !== 'espacio');
    return <div className="space-y-2">
        {visibles.map((bloque, i) => {
            const final = cursor && i === visibles.length - 1 ? <span aria-hidden="true" className="ml-0.5 inline-block h-3.5 w-1.5 translate-y-0.5 animate-pulse rounded-sm bg-slate-400" /> : null;
            if (bloque.tipo === 'lista') {
                const Lista = bloque.ordenada ? 'ol' : 'ul';
                return <Lista key={i} className={`space-y-1 pl-4 ${bloque.ordenada ? 'list-decimal' : 'list-disc'} marker:text-slate-400`}>
                    {bloque.items.map((item, j) => <li key={j}><Enlinea texto={item} />{j === bloque.items.length - 1 && final}</li>)}
                </Lista>;
            }
            return <p key={i}>{bloque.lineas.map((l, j) => <span key={j}>{j > 0 && <br />}<Enlinea texto={l} /></span>)}{final}</p>;
        })}
    </div>;
}
