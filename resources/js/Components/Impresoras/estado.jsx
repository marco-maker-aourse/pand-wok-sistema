import { Badge } from '@/Components/ui';

const TONO_POR_ESTADO = { conectada: 'success', desconectada: 'neutral', error: 'danger', desconocido: 'warning' };

/** Estado de conexión de una impresora o de un agente, con el tono semántico del sistema. */
export function EstadoConexion({ valor, etiqueta }) {
    return <Badge tone={TONO_POR_ESTADO[valor] ?? 'warning'} dot>{etiqueta ?? valor ?? 'desconocido'}</Badge>;
}

/** Estado del agente de una terminal según su vinculación y su último contacto (umbral de 70 s). */
export function estadoAgente(terminal) {
    if (!terminal.vinculado_en) return { valor: 'desconectada', etiqueta: 'Sin vincular' };
    if (!terminal.ultimo_contacto_en) return { valor: 'desconocido', etiqueta: 'Vinculado sin contacto' };
    return Date.now() - new Date(terminal.ultimo_contacto_en).getTime() <= 70_000
        ? { valor: 'conectada', etiqueta: 'Conectado' }
        : { valor: 'desconectada', etiqueta: 'Sin conexión reciente' };
}

export const fechaHora = (valor) => (valor ? new Date(valor).toLocaleString('es-PE') : null);
