import { useForm } from '@inertiajs/react';
import { PlugZap } from 'lucide-react';
import { Button, CheckboxField, Dialog, Field, Input, Select } from '@/Components/ui';

const VACIA = { nombre: '', marca: '', modelo: '', tipo_conexion: 'SISTEMA', identificador: '', puerto: '', ancho_papel_mm: '80', soporta_escpos: false, corte_automatico: false, abre_cajon: false, activo: true };

const desdeImpresora = (p) => ({
    nombre: p.nombre,
    marca: p.marca ?? '',
    modelo: p.modelo ?? '',
    tipo_conexion: p.tipo_conexion,
    identificador: p.identificador,
    puerto: p.puerto ?? '',
    ancho_papel_mm: String(p.ancho_papel_mm),
    soporta_escpos: !!p.soporta_escpos,
    corte_automatico: !!p.corte_automatico,
    abre_cajon: !!p.abre_cajon,
    activo: !!p.activo,
});

/**
 * Alta o edición de impresora. Se monta con `key` por registro, así que el estado
 * inicial sale directamente de `impresora` sin efectos de sincronización.
 */
export default function FormularioImpresora({ abierto, impresora, onCerrar }) {
    const form = useForm(impresora ? desdeImpresora(impresora) : VACIA);
    const { data, setData, errors, processing } = form;
    const esRed = data.tipo_conexion === 'RED';

    const guardar = (e) => {
        e.preventDefault();
        const opciones = { preserveScroll: true, onSuccess: onCerrar };
        if (impresora) form.put(`/impresoras/${impresora.id_impresora}`, opciones);
        else form.post('/impresoras', opciones);
    };

    return (
        <Dialog
            open={abierto}
            onClose={processing ? () => {} : onCerrar}
            size="lg"
            title={impresora ? 'Editar impresora' : 'Nueva impresora'}
            description="Equipo de impresión de la sucursal."
            footer={
                <>
                    <Button variant="secondary" onClick={onCerrar} disabled={processing}>Cancelar</Button>
                    <Button type="submit" form="formulario-impresora" icon={PlugZap} loading={processing}>{impresora ? 'Guardar cambios' : 'Registrar impresora'}</Button>
                </>
            }
        >
            <form id="formulario-impresora" onSubmit={guardar} className="space-y-4 p-5">
                <Field label="Nombre" error={errors.nombre} required>
                    <Input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} autoFocus />
                </Field>
                <div className="grid grid-cols-2 gap-3">
                    <Field label="Marca" error={errors.marca}><Input value={data.marca} onChange={(e) => setData('marca', e.target.value)} /></Field>
                    <Field label="Modelo" error={errors.modelo}><Input value={data.modelo} onChange={(e) => setData('modelo', e.target.value)} /></Field>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <Field label="Conexión" error={errors.tipo_conexion}>
                        <Select value={data.tipo_conexion} onChange={(e) => setData('tipo_conexion', e.target.value)}>
                            <option value="SISTEMA">Cola de Windows</option>
                            <option value="USB">USB instalado en Windows</option>
                            <option value="RED">Red TCP / ESC-POS</option>
                            <option value="BLUETOOTH">Bluetooth Classic instalado</option>
                        </Select>
                    </Field>
                    <Field label="Ancho de papel" error={errors.ancho_papel_mm}>
                        <Select value={data.ancho_papel_mm} onChange={(e) => setData('ancho_papel_mm', e.target.value)}>
                            <option value="58">58 mm</option>
                            <option value="80">80 mm</option>
                        </Select>
                    </Field>
                </div>
                <div className="grid grid-cols-3 gap-3">
                    <Field label="Identificador" error={errors.identificador} required className="col-span-2">
                        <Input
                            className="font-mono"
                            placeholder={esRed ? 'IP o nombre de host' : 'Nombre exacto en Windows'}
                            value={data.identificador}
                            onChange={(e) => setData('identificador', e.target.value)}
                        />
                    </Field>
                    <Field label="Puerto TCP" error={errors.puerto} hint={esRed ? undefined : 'Solo red'}>
                        <Input type="number" className="tabular-nums" disabled={!esRed} value={data.puerto} onChange={(e) => setData('puerto', e.target.value)} />
                    </Field>
                </div>
                <div className="grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-2">
                    <CheckboxField label="Compatible con ESC/POS" checked={data.soporta_escpos} onChange={(v) => setData('soporta_escpos', v)} />
                    <CheckboxField label="Corte automático" checked={data.corte_automatico} onChange={(v) => setData('corte_automatico', v)} />
                    <CheckboxField label="Abre cajón" checked={data.abre_cajon} onChange={(v) => setData('abre_cajon', v)} />
                    <CheckboxField label="Equipo activo" checked={data.activo} onChange={(v) => setData('activo', v)} />
                </div>
            </form>
        </Dialog>
    );
}
