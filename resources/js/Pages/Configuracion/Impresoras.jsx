import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Laptop, Link2, Plus, Printer, Wifi } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, PageHeader, Stat, confirmar, toast } from '@/Components/ui';
import FormularioImpresora from '@/Components/Impresoras/FormularioImpresora';
import FormularioTerminal from '@/Components/Impresoras/FormularioTerminal';
import { ListaImpresoras, ListaTerminales } from '@/Components/Impresoras/Listados';
import { CodigoVinculacion, PanelAgente, PanelAgenteAndroid } from '@/Components/Impresoras/PanelAgente';
import { estadoAgente } from '@/Components/Impresoras/estado';

/**
 * Impresoras y terminales POS. Usa los mismos endpoints que antes:
 * /impresoras (CRUD, diagnosticar, prueba) y /terminales-pos (CRUD, vinculación).
 * `formulario` = { tipo: 'impresora' | 'terminal', registro: objeto | null } | null.
 */
export default function Impresoras({ impresoras = [], terminales = [], installerUrl = null, installerVersion = null, installerSha256 = null, android = null }) {
    const { flash = {} } = usePage().props;
    const [formulario, setFormulario] = useState(null);

    const cerrar = () => setFormulario(null);
    const abrir = (tipo, registro = null) => setFormulario({ tipo, registro });

    const ejecutar = (id, accion) => router.post(`/impresoras/${id}/${accion}`, {}, { preserveScroll: true });
    const vincular = (id) => router.post(`/terminales-pos/${id}/vinculacion`, {}, { preserveScroll: true });
    const revocar = async (terminal) => {
        const confirmado = await confirmar({
            titulo: '¿Revocar el agente de esta terminal?',
            mensaje: `${terminal.nombre} dejará de recibir trabajos de impresión hasta que se vincule de nuevo.`,
            confirmar: 'Revocar acceso',
        });
        if (confirmado) router.delete(`/terminales-pos/${terminal.id_terminal}/vinculacion`, { preserveScroll: true, onSuccess: () => toast.success('Agente revocado', terminal.nombre) });
    };

    const conectadas = impresoras.filter((p) => p.estado_conexion === 'conectada').length;
    const vinculadas = terminales.filter((t) => t.vinculado_en).length;
    const agentesEnLinea = terminales.filter((t) => estadoAgente(t).valor === 'conectada').length;

    return (
        <AuthenticatedLayout header="Impresoras y terminales">
            <Head title="Impresoras y terminales" />

            <div className="space-y-5">
                <PageHeader
                    breadcrumbs={[{ label: 'Configuración' }, { label: 'Impresoras y terminales' }]}
                    title="Impresoras y terminales"
                    description="Asigna una impresora a cada terminal y vincula el equipo que imprime comprobantes. La impresión nunca revierte una venta ni una aceptación fiscal."
                    actions={
                        <>
                            <Button variant="secondary" icon={Laptop} onClick={() => abrir('terminal')}>Nueva terminal</Button>
                            <Button icon={Plus} onClick={() => abrir('impresora')}>Nueva impresora</Button>
                        </>
                    }
                />

                {flash.success && (
                    <p role="status" className="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">{flash.success}</p>
                )}
                {flash.codigo_vinculacion_agente && <CodigoVinculacion codigo={flash.codigo_vinculacion_agente} />}

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat destacado label="Impresoras" value={impresoras.length} hint={`${impresoras.filter((p) => p.activo).length} activas`} icon={Printer} />
                    <Stat label="Conectadas" value={conectadas} hint="Según la última verificación" icon={Wifi} iconTone={conectadas ? 'success' : 'neutral'} />
                    <Stat label="Terminales" value={terminales.length} hint={`${terminales.filter((t) => t.activo).length} activas`} icon={Laptop} />
                    <Stat label="Agentes vinculados" value={vinculadas} hint={`${agentesEnLinea} en línea ahora`} icon={Link2} iconTone={agentesEnLinea ? 'success' : 'brand'} />
                </div>

                <PanelAgente url={installerUrl} version={installerVersion} sha256={installerSha256} />
                <PanelAgenteAndroid url={android?.url} version={android?.version} sha256={android?.sha256} />

                <div className="grid grid-cols-1 gap-5 2xl:grid-cols-2">
                    <ListaImpresoras impresoras={impresoras} onNueva={() => abrir('impresora')} onEditar={(p) => abrir('impresora', p)} onAccion={ejecutar} />
                    <ListaTerminales terminales={terminales} onNueva={() => abrir('terminal')} onEditar={(t) => abrir('terminal', t)} onVincular={vincular} onRevocar={revocar} />
                </div>
            </div>

            {formulario?.tipo === 'impresora' && (
                <FormularioImpresora key={formulario.registro?.id_impresora ?? 'nueva'} abierto impresora={formulario.registro} onCerrar={cerrar} />
            )}
            {formulario?.tipo === 'terminal' && (
                <FormularioTerminal key={formulario.registro?.id_terminal ?? 'nueva'} abierto terminal={formulario.registro} impresoras={impresoras} onCerrar={cerrar} />
            )}
        </AuthenticatedLayout>
    );
}
