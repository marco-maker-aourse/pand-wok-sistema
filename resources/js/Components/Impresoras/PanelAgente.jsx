import { Copy, Download, KeyRound, ShieldCheck } from 'lucide-react';
import { Button, Card, IconoTile, toast } from '@/Components/ui';

const PASOS = [
    ['Instala el agente', 'Descárgalo en el equipo de caja.'],
    ['Registra la terminal', 'Selecciona su impresora predeterminada.'],
    ['Vincula y prueba', 'Usa el código temporal y verifica un ticket.'],
];

/** Descarga del agente Windows y pasos de configuración (datos del servidor). */
export function PanelAgente({ url, version, sha256 }) {
    return (
        <Card className="overflow-hidden">
            <div className="grid gap-5 p-5 lg:grid-cols-[1fr_auto] lg:items-center">
                <ol className="grid gap-4 sm:grid-cols-3">
                    {PASOS.map(([titulo, detalle], i) => (
                        <li key={titulo} className="flex gap-3">
                            <span className="volumen-marca grid h-8 w-8 shrink-0 place-items-center rounded-lg text-sm font-semibold text-on-brand">{i + 1}</span>
                            <span className="text-sm">
                                <span className="block font-medium text-slate-900">{titulo}</span>
                                <span className="block text-slate-500">{detalle}</span>
                            </span>
                        </li>
                    ))}
                </ol>
                <div className="rounded-xl bg-brand-soft p-4 ring-1 ring-brand-border lg:w-80">
                    <p className="flex items-center gap-2 text-sm font-medium text-slate-900">
                        <ShieldCheck className="h-4 w-4 text-brand-ink" aria-hidden="true" />
                        Agente Windows {version ? `v${version}` : 'sin publicar'}
                    </p>
                    {sha256 && <code className="mt-2 block break-all rounded-md bg-white/80 px-2 py-1 font-mono text-[0.6875rem] text-slate-500">SHA-256 · {sha256}</code>}
                    {url ? (
                        <a href={url} target="_blank" rel="noreferrer" className="volumen-marca mt-3 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg text-sm font-semibold text-on-brand hover:brightness-105">
                            <Download className="h-4 w-4" aria-hidden="true" />
                            Descargar agente
                        </a>
                    ) : (
                        <p className="mt-3 text-xs text-slate-500">Instalador pendiente de publicación segura.</p>
                    )}
                </div>
            </div>
        </Card>
    );
}

/** Código temporal de vinculación (llega una sola vez en la sesión flash). */
export function CodigoVinculacion({ codigo }) {
    const copiar = async () => {
        try {
            await navigator.clipboard?.writeText(codigo.codigo);
            toast.success('Código copiado', 'Pégalo en el instalador del agente.');
        } catch {
            toast.warning('No se pudo copiar', 'Escribe el código manualmente.');
        }
    };

    return (
        <Card className="flex flex-wrap items-center justify-between gap-4 bg-brand-soft p-5 ring-brand-border">
            <div className="flex items-center gap-4">
                <IconoTile icon={KeyRound} tone="solido" size="lg" />
                <div>
                    <p className="text-sm font-medium text-brand-ink">Código temporal para {codigo.terminal}</p>
                    <p className="mt-1 font-mono text-3xl font-semibold tracking-[0.3em] text-slate-900 tabular-nums">{codigo.codigo}</p>
                    <p className="mt-1 text-xs text-slate-600">Válido por 10 minutos y se muestra una sola vez.</p>
                </div>
            </div>
            <Button icon={Copy} onClick={copiar}>Copiar código</Button>
        </Card>
    );
}
