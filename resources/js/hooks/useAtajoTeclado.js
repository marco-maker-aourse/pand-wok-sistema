import { useEffect, useRef } from 'react';

/**
 * Registra un atajo de teclado global. `combinacion` acepta formas como
 * 'F2', 'F9', 'Escape', 'mod+k' (Ctrl en Windows/Linux, ⌘ en macOS).
 * Con `enCampos: false` el atajo se ignora mientras se escribe en un campo.
 */
export default function useAtajoTeclado(combinacion, accion, { activo = true, enCampos = true } = {}) {
    const accionRef = useRef(accion);
    accionRef.current = accion;

    useEffect(() => {
        if (!activo) return undefined;
        const partes = combinacion.toLowerCase().split('+');
        const tecla = partes.pop();
        const conMod = partes.includes('mod');

        const alPresionar = (event) => {
            if (event.key.toLowerCase() !== tecla) return;
            if (conMod !== (event.ctrlKey || event.metaKey)) return;
            const objetivo = event.target;
            const escribiendo = objetivo instanceof HTMLElement && (objetivo.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(objetivo.tagName));
            if (escribiendo && !enCampos) return;
            event.preventDefault();
            accionRef.current(event);
        };

        window.addEventListener('keydown', alPresionar);
        return () => window.removeEventListener('keydown', alPresionar);
    }, [combinacion, activo, enCampos]);
}
