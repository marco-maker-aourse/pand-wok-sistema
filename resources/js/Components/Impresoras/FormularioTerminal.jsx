import { useForm } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { Button, CheckboxField, Dialog, Field, Input, Select } from '@/Components/ui';

const VACIA = { codigo: '', nombre: '', identificador_equipo: '', id_impresora: '', impresion_automatica: false, copias_ticket: '1', activo: true };

const desdeTerminal = (t) => ({
    codigo: t.codigo,
    nombre: t.nombre,
    identificador_equipo: t.identificador_equipo ?? '',
    id_impresora: t.id_impresora ?? '',
    impresion_automatica: !!t.impresion_automatica,
    copias_ticket: String(t.copias_ticket),
    activo: !!t.activo,
});

/** Alta o edición de terminal POS (se monta con `key` por registro). */
export default function FormularioTerminal({ abierto, terminal, impresoras, onCerrar }) {
    const form = useForm(terminal ? desdeTerminal(terminal) : VACIA);
    const { data, setData, errors, processing } = form;

    const guardar = (e) => {
        e.preventDefault();
        const opciones = { preserveScroll: true, onSuccess: onCerrar };
        if (terminal) form.put(`/terminales-pos/${terminal.id_terminal}`, opciones);
        else form.post('/terminales-pos', opciones);
    };

    return (
        <Dialog
            open={abierto}
            onClose={processing ? () => {} : onCerrar}
            size="md"
            title={terminal ? 'Editar terminal' : 'Nueva terminal'}
            description="Caja física que imprime comprobantes."
            footer={
                <>
                    <Button variant="secondary" onClick={onCerrar} disabled={processing}>Cancelar</Button>
                    <Button type="submit" form="formulario-terminal" icon={CheckCircle2} loading={processing}>{terminal ? 'Guardar terminal' : 'Registrar terminal'}</Button>
                </>
            }
        >
            <form id="formulario-terminal" onSubmit={guardar} className="space-y-4 p-5">
                <div className="grid grid-cols-2 gap-3">
                    <Field label="Código" error={errors.codigo} required>
                        <Input className="font-mono uppercase" value={data.codigo} onChange={(e) => setData('codigo', e.target.value.toUpperCase())} placeholder="CAJA-01" autoFocus />
                    </Field>
                    <Field label="Nombre" error={errors.nombre} required>
                        <Input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                    </Field>
                </div>
                <Field label="Impresora predeterminada" error={errors.id_impresora}>
                    <Select value={data.id_impresora} onChange={(e) => setData('id_impresora', e.target.value)}>
                        <option value="">Sin impresora</option>
                        {impresoras.filter((p) => p.activo).map((p) => <option key={p.id_impresora} value={p.id_impresora}>{p.nombre} · {p.ancho_papel_mm} mm</option>)}
                    </Select>
                </Field>
                <div className="grid grid-cols-3 gap-3">
                    <Field label="Identificador del equipo" error={errors.identificador_equipo} hint="Opcional" className="col-span-2">
                        <Input value={data.identificador_equipo} onChange={(e) => setData('identificador_equipo', e.target.value)} />
                    </Field>
                    <Field label="Copias" error={errors.copias_ticket}>
                        <Input type="number" min="1" max="5" className="tabular-nums" value={data.copias_ticket} onChange={(e) => setData('copias_ticket', e.target.value)} />
                    </Field>
                </div>
                <div className="space-y-3 rounded-xl bg-slate-50 p-3">
                    <CheckboxField label="Impresión automática" description="Si falla, nunca anula la venta." checked={data.impresion_automatica} onChange={(v) => setData('impresion_automatica', v)} />
                    <CheckboxField label="Terminal activa" checked={data.activo} onChange={(v) => setData('activo', v)} />
                </div>
            </form>
        </Dialog>
    );
}
