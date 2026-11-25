import { useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import { confirmar } from '@/Components/ui';

/**
 * Evita perder un pedido sin guardar: al cerrar la pestaña usa el aviso del navegador y al
 * navegar dentro del sistema pide confirmación con el diálogo propio. Solo intercepta visitas
 * GET (los envíos del propio pedido siguen su curso).
 */
export default function useAvisoBorrador(hayBorrador, mensaje = 'Hay platos sin guardar. Si sales ahora se perderán.') {
    const permitido = useRef(false);

    useEffect(() => {
        if (!hayBorrador) return undefined;
        permitido.current = false;

        const avisarCierre = (evento) => {
            evento.preventDefault();
            evento.returnValue = '';
        };

        const quitar = router.on('before', (evento) => {
            const visita = evento.detail.visit;
            if (permitido.current || visita.method !== 'get') return;
            // Recargas parciales de la misma pantalla (salón, cuentas) no abandonan el pedido.
            if (visita.only?.length) return;
            evento.preventDefault();
            confirmar({ titulo: '¿Salir sin guardar?', mensaje, confirmar: 'Salir y descartar', cancelar: 'Seguir en el pedido', tono: 'warning' })
                .then((salir) => {
                    if (!salir) return;
                    permitido.current = true;
                    router.visit(visita.url, { method: 'get', data: {}, preserveState: visita.preserveState, replace: visita.replace });
                });
        });

        window.addEventListener('beforeunload', avisarCierre);
        return () => {
            window.removeEventListener('beforeunload', avisarCierre);
            quitar();
        };
    }, [hayBorrador, mensaje]);
}
