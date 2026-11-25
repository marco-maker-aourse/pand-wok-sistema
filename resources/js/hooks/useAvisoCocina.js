import { useCallback, useEffect, useRef, useState } from 'react';
import { comandasNuevas } from '@/Components/Restaurante/cocina';

const CLAVE_PREFERENCIA = 'cocina.sonido';

function leerPreferencia() {
    try {
        return window.localStorage.getItem(CLAVE_PREFERENCIA) !== 'apagado';
    } catch {
        return true;
    }
}

function guardarPreferencia(activo) {
    try {
        window.localStorage.setItem(CLAVE_PREFERENCIA, activo ? 'activo' : 'apagado');
    } catch {
        // Sin almacenamiento (modo privado): la preferencia dura lo que la pestaña.
    }
}

/** "Ding-dong" de dos notas; con varias comandas nuevas se repite una vez. */
function campana(contexto, veces = 1) {
    const inicio = contexto.currentTime;
    for (let r = 0; r < Math.min(veces, 2); r++) {
        [[988, 0], [740, 0.22]].forEach(([frecuencia, desfase]) => {
            const t = inicio + r * 0.7 + desfase;
            const tono = contexto.createOscillator();
            const volumen = contexto.createGain();
            tono.type = 'triangle';
            tono.frequency.value = frecuencia;
            volumen.gain.setValueAtTime(0.0001, t);
            volumen.gain.exponentialRampToValueAtTime(0.35, t + 0.02);
            volumen.gain.exponentialRampToValueAtTime(0.0001, t + 0.6);
            tono.connect(volumen).connect(contexto.destination);
            tono.start(t);
            tono.stop(t + 0.62);
        });
    }
}

/**
 * Aviso de comandas nuevas en cocina: campana, vibración y contador en el título de la pestaña.
 *
 * El sonido queda activo por defecto y se recuerda. Los navegadores solo dejan sonar audio
 * después de que la persona toca la página: mientras eso no pasa, `bloqueado` es true y la
 * pantalla pide un toque; el primer toque en cualquier parte lo habilita.
 */
export default function useAvisoCocina(comandas, filtro) {
    const [activo, setActivo] = useState(leerPreferencia);
    const [bloqueado, setBloqueado] = useState(true);
    const [sinVer, setSinVer] = useState(0);
    const audio = useRef(null);
    const vistos = useRef(null);
    const titulo = useRef(null);

    const habilitar = useCallback(async () => {
        try {
            audio.current ??= new (window.AudioContext || window.webkitAudioContext)();
            await audio.current.resume();
            setBloqueado(audio.current.state !== 'running');
        } catch {
            setBloqueado(true);
        }
    }, []);

    // El primer toque en cualquier parte de la pantalla desbloquea el audio.
    useEffect(() => {
        if (!activo || !bloqueado) return undefined;
        const alTocar = () => habilitar();
        window.addEventListener('pointerdown', alTocar, { once: true });
        window.addEventListener('keydown', alTocar, { once: true });
        return () => {
            window.removeEventListener('pointerdown', alTocar);
            window.removeEventListener('keydown', alTocar);
        };
    }, [activo, bloqueado, habilitar]);

    useEffect(() => {
        const { nuevas, vistos: actuales } = comandasNuevas(vistos.current, comandas, filtro);
        vistos.current = actuales;
        if (nuevas.length === 0) return;

        if (activo && audio.current?.state === 'running') campana(audio.current, nuevas.length);
        try { navigator.vibrate?.([180, 90, 180]); } catch { /* sin vibración */ }
        if (document.hidden) setSinVer((n) => n + nuevas.length);
    }, [comandas, filtro, activo]);

    // Contador en la pestaña mientras la cocina mira otra cosa.
    useEffect(() => {
        if (sinVer > 0) {
            titulo.current ??= document.title;
            document.title = `(${sinVer}) Nuevas comandas`;
        } else if (titulo.current) {
            document.title = titulo.current;
            titulo.current = null;
        }
    }, [sinVer]);
    useEffect(() => {
        const alVolver = () => { if (!document.hidden) setSinVer(0); };
        document.addEventListener('visibilitychange', alVolver);
        return () => {
            document.removeEventListener('visibilitychange', alVolver);
            if (titulo.current) document.title = titulo.current;
        };
    }, []);

    useEffect(() => () => { audio.current?.close().catch(() => {}); }, []);

    const alternar = useCallback(async () => {
        const siguiente = !activo;
        setActivo(siguiente);
        guardarPreferencia(siguiente);
        if (siguiente) {
            await habilitar();
            if (audio.current?.state === 'running') campana(audio.current);
        }
    }, [activo, habilitar]);

    return { activo, bloqueado: activo && bloqueado, alternar, habilitar };
}
