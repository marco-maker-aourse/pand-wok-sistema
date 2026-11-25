import { useCallback, useEffect, useRef, useState } from 'react';
import { comandasNuevas, sonarCampana } from '@/Components/Restaurante/cocina';

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

/**
 * Aviso de comandas nuevas en cocina: campana, vibración y contador en el título de la pestaña.
 *
 * El sonido queda activo por defecto y se recuerda. Los navegadores solo dejan sonar audio
 * después de que la persona toca la página: mientras eso no pasa, `bloqueado` es true y la
 * pantalla pide un toque; el primer toque en cualquier parte lo habilita.
 *
 * El navegador vuelve a suspender el audio al apagarse la pantalla o al quedar la pestaña
 * atrás. Por eso se vigila su estado (si se suspende, vuelve a pedirse el toque), se intenta
 * reanudar al volver a la pestaña y antes de cada campana.
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
            if (!audio.current) {
                audio.current = new (window.AudioContext || window.webkitAudioContext)();
                audio.current.onstatechange = () => setBloqueado(audio.current?.state !== 'running');
            }
            await audio.current.resume();
            setBloqueado(audio.current.state !== 'running');
        } catch {
            setBloqueado(true);
        }
    }, []);

    // Si ya hubo un toque en esta sesión (p. ej. al volver a Cocina desde otro módulo), el
    // navegador deja reanudar el audio sin pedir otro.
    useEffect(() => { if (activo) habilitar(); }, [activo, habilitar]);

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

        if (activo) {
            sonarCampana(audio.current, nuevas.length).then((sono) => { if (!sono) setBloqueado(true); });
        }
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
        const alVolver = () => {
            if (document.hidden) return;
            setSinVer(0);
            if (audio.current && audio.current.state !== 'running') audio.current.resume().catch(() => {});
        };
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
            await sonarCampana(audio.current);
        }
    }, [activo, habilitar]);

    return { activo, bloqueado: activo && bloqueado, alternar, habilitar };
}
