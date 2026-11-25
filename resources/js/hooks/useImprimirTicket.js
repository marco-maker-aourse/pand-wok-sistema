import { useState } from 'react';
import axios from 'axios';
import { toast } from '@/Components/ui';

/** Identificador por pulsación: si la misma solicitud llega dos veces, el servidor no duplica el ticket. */
const nuevaSolicitud = () => (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : undefined);

/**
 * Impresión manual del ticket fiscal desde cualquier pantalla. El servidor valida que el
 * comprobante esté aceptado por SUNAT y que la caja tenga impresora; aquí solo se informa.
 */
export default function useImprimirTicket() {
    const [imprimiendo, setImprimiendo] = useState(false);

    const imprimir = async (idVenta) => {
        if (imprimiendo) return false;
        setImprimiendo(true);
        try {
            const { data } = await axios.post(`/ventas/${idVenta}/imprimir-ticket`, { solicitud: nuevaSolicitud() });
            toast.success('Ticket en cola', data?.message || 'La solicitud de impresión quedó registrada.');
            return true;
        } catch (error) {
            toast.error('No se pudo imprimir', error.response?.data?.message || 'Revisa la conexión e inténtalo de nuevo.');
            return false;
        } finally {
            setImprimiendo(false);
        }
    };

    return { imprimir, imprimiendo };
}
