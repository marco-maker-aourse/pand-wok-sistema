import { useSyncExternalStore } from 'react';

const suscribir = (fn) => {
    window.addEventListener('online', fn);
    window.addEventListener('offline', fn);
    return () => {
        window.removeEventListener('online', fn);
        window.removeEventListener('offline', fn);
    };
};

/** `true` mientras el navegador reporta conexión de red. */
export default function useConexion() {
    return useSyncExternalStore(suscribir, () => navigator.onLine, () => true);
}
