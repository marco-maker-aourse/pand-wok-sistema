import { useEffect, useState } from 'react';
import { Button, Dialog, Field, Textarea } from '@/Components/ui';

/** Pide el motivo de una anulación. `requerido` refleja la regla del servidor para habilitar el botón. */
export default function DialogoMotivo({ abierto, titulo, descripcion, requerido = true, confirmar = 'Anular', enviando = false, error, onCerrar, onConfirmar, puedeRecuperar = false }) {
    const [motivo, setMotivo] = useState('');
    const [recuperar, setRecuperar] = useState(false);

    useEffect(() => {
        if (abierto) {
            setMotivo('');
            setRecuperar(false);
        }
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
                    <Button variant="danger" loading={enviando} disabled={requerido && !motivo.trim()} onClick={() => onConfirmar(motivo.trim() || null, recuperar)}>{confirmar}</Button>
                </>
            )}
        >
            <div className="p-5">
                <Field label={requerido ? 'Motivo' : 'Motivo (opcional)'} error={error} required={requerido}>
                    <Textarea value={motivo} onChange={(e) => setMotivo(e.target.value)} maxLength={255} rows={3} autoFocus placeholder="Ej.: el cliente cambió de plato" />
                </Field>
                {puedeRecuperar && (
                    <label className="mt-3 flex items-start gap-2 text-sm">
                        <input className="mt-0.5" type="checkbox" checked={recuperar} onChange={(e) => setRecuperar(e.target.checked)} />
                        <span>Devolver al stock los insumos que cocina aún no preparó y que se pueden usar.</span>
                    </label>
                )}
            </div>
        </Dialog>
    );
}
