import { useSyncExternalStore } from 'react';
import { ConfirmDialog } from './Dialog';

/*
 * Confirmación basada en promesas: `if (await confirmar({...})) { ... }`.
 * <Confirmador /> vive en el layout; solo hay una confirmación abierta a la vez.
 */
let actual = null;
const suscriptores = new Set();
const emitir = () => suscriptores.forEach((fn) => fn());

/**
 * @param {{ titulo: string, mensaje?: import('react').ReactNode, confirmar?: string, cancelar?: string, tono?: 'danger' | 'warning' }} opciones
 * @returns {Promise<boolean>}
 */
export function confirmar(opciones) {
    actual?.resolver(false);
    return new Promise((resolver) => {
        actual = { ...opciones, resolver };
        emitir();
    });
}

function responder(valor) {
    if (!actual) return;
    actual.resolver(valor);
    actual = null;
    emitir();
}

const suscribir = (fn) => {
    suscriptores.add(fn);
    return () => suscriptores.delete(fn);
};
const leer = () => actual;

export function Confirmador() {
    const pedido = useSyncExternalStore(suscribir, leer, leer);

    return (
        <ConfirmDialog
            open={Boolean(pedido)}
            onClose={() => responder(false)}
            onConfirm={() => responder(true)}
            title={pedido?.titulo ?? ''}
            confirmLabel={pedido?.confirmar ?? 'Confirmar'}
            cancelLabel={pedido?.cancelar ?? 'Cancelar'}
            tone={pedido?.tono ?? 'danger'}
        >
            {pedido?.mensaje}
        </ConfirmDialog>
    );
}
