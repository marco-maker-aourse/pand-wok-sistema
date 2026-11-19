import { useEffect, useState } from 'react';
import { Button, Dialog, Field, Textarea } from '@/Components/ui';

/** Pide el motivo de una anulación. `requerido` refleja la regla del servidor para habilitar el botón. */
export default function DialogoMotivo({ abierto, titulo, descripcion, requerido = true, confirmar = 'Anular', enviando = false, error, onCerrar, onConfirmar }) {
    const [motivo, setMotivo] = useState('');

    useEffect(() => {
        if (abierto) setMotivo('');
    }, [abierto]);

    return (
        <Dialog
            open={abierto}
            onClose={enviando ? () => {} : onCerrar}
            title={titulo}
            description={descripcion}
            size="sm"
            footer={(
                <>
                    <Button variant="secondary" onClick={onCerrar} disabled={enviando}>Volver</Button>
                    <Button variant="danger" loading={enviando} disabled={requerido && !motivo.trim()} onClick={() => onConfirmar(motivo.trim() || null)}>{confirmar}</Button>
                </>
            )}
        >
            <div className="p-5">
                <Field label={requerido ? 'Motivo' : 'Motivo (opcional)'} error={error} required={requerido}>
                    <Textarea value={motivo} onChange={(e) => setMotivo(e.target.value)} maxLength={255} rows={3} autoFocus placeholder="Ej.: el cliente cambió de plato" />
                </Field>
            </div>
        </Dialog>
    );
}
