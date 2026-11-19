import { toast } from '@/Components/ui/Toast';

/** Mensajes homogéneos para consultas DNI/RUC sin exponer errores del proveedor externo. */
export default function avisarErrorConsultaDocumento(error) {
    const status = error?.response?.status;
    const serverMessage = error?.response?.data?.message;
    const message = status === 404
        ? 'No se encontraron datos para el documento ingresado.'
        : status === 422
            ? serverMessage || 'Verifica el tipo y la longitud configurada para el documento.'
            : serverMessage || 'No fue posible consultar el documento. Puedes continuar con el registro manual.';

    return Promise.resolve(toast.warning('Consulta no disponible', message));
}
