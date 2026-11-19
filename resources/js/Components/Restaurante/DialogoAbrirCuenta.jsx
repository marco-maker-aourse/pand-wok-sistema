import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Minus, Plus } from 'lucide-react';
import { Button, Dialog, Field, Input, Textarea } from '@/Components/ui';
import { nuevaClave, TIPOS_PEDIDO } from './formato';

/**
 * Abre una cuenta de mesa, para llevar o delivery. La clave de idempotencia se genera al
 * abrir el diálogo: un doble toque o un reintento por red devuelve la misma cuenta.
 */
export default function DialogoAbrirCuenta({ abierto, onCerrar, tipo = 'MESA', mesa = null }) {
    const clave = useMemo(() => (abierto ? nuevaClave() : null), [abierto]);
    const [datos, setDatos] = useState({});
    const [errores, setErrores] = useState({});
    const [enviando, setEnviando] = useState(false);

    useEffect(() => {
        if (abierto) {
            setDatos({ comensales: Math.min(mesa?.capacidad ?? 2, 2), nombre_contacto: '', telefono_contacto: '', direccion_entrega: '', observacion: '' });
            setErrores({});
        }
    }, [abierto, mesa]);

    const cambiar = (campo) => (e) => setDatos((d) => ({ ...d, [campo]: e.target.value }));
    const comensales = Number(datos.comensales) || 1;

    const enviar = (e) => {
        e.preventDefault();
        router.post('/pedidos', {
            tipo,
            id_mesa: mesa?.id_mesa ?? null,
            comensales: tipo === 'MESA' ? comensales : null,
            nombre_contacto: datos.nombre_contacto || null,
            telefono_contacto: datos.telefono_contacto || null,
            direccion_entrega: datos.direccion_entrega || null,
            observacion: datos.observacion || null,
            clave_idempotencia: clave,
        }, {
            onStart: () => setEnviando(true),
            onFinish: () => setEnviando(false),
            onError: setErrores,
        });
    };

    const titulo = tipo === 'MESA' ? `Abrir mesa ${mesa?.codigo ?? ''}` : `Nueva cuenta · ${TIPOS_PEDIDO[tipo]}`;

    return (
        <Dialog
            open={abierto}
            onClose={enviando ? () => {} : onCerrar}
            title={titulo}
            size="sm"
            footer={(
                <>
                    <Button variant="secondary" onClick={onCerrar} disabled={enviando}>Cancelar</Button>
                    <Button type="submit" form="abrir-cuenta" loading={enviando}>Abrir cuenta</Button>
                </>
            )}
        >
            <form id="abrir-cuenta" onSubmit={enviar} className="space-y-4 p-5">
                {errores.general && <p className="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700" role="alert">{errores.general}</p>}

                {tipo === 'MESA' ? (
                    <Field label="Comensales" error={errores.comensales}>
                        <div className="flex items-center gap-3">
                            <Button variant="secondary" size="lg" icon={Minus} aria-label="Menos comensales" onClick={() => setDatos((d) => ({ ...d, comensales: Math.max(1, comensales - 1) }))} />
                            <span className="w-12 text-center font-mono text-2xl font-semibold tabular-nums" aria-live="polite">{comensales}</span>
                            <Button variant="secondary" size="lg" icon={Plus} aria-label="Más comensales" onClick={() => setDatos((d) => ({ ...d, comensales: Math.min(100, comensales + 1) }))} />
                        </div>
                    </Field>
                ) : (
                    <>
                        <Field label="Nombre del cliente" error={errores.nombre_contacto}>
                            <Input value={datos.nombre_contacto ?? ''} onChange={cambiar('nombre_contacto')} maxLength={120} autoFocus />
                        </Field>
                        <Field label="Teléfono" error={errores.telefono_contacto}>
                            <Input value={datos.telefono_contacto ?? ''} onChange={cambiar('telefono_contacto')} maxLength={20} inputMode="tel" />
                        </Field>
                        {tipo === 'DELIVERY' && (
                            <Field label="Dirección de entrega" error={errores.direccion_entrega} required>
                                <Textarea value={datos.direccion_entrega ?? ''} onChange={cambiar('direccion_entrega')} maxLength={255} rows={2} />
                            </Field>
                        )}
                    </>
                )}

                <Field label="Nota de la cuenta" error={errores.observacion} hint="Opcional. Ej.: cumpleaños, alergia general.">
                    <Input value={datos.observacion ?? ''} onChange={cambiar('observacion')} maxLength={255} />
                </Field>
            </form>
        </Dialog>
    );
}
