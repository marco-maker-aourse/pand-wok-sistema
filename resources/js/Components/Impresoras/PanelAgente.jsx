import { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import { Copy, Download, KeyRound, ShieldCheck, Smartphone } from 'lucide-react';
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

/**
 * App Android del agente: imprime boletas, facturas y comandas desde el celular en una
 * ticketera Bluetooth o de red. Se instala escaneando el QR con el celular o con el enlace.
 */
export function PanelAgenteAndroid({ url, version, sha256 }) {
    const [qr, setQr] = useState(null);
    useEffect(() => {
        let vigente = true;
        if (url) QRCode.toDataURL(url, { margin: 1, width: 176, errorCorrectionLevel: 'M' }).then((dato) => vigente && setQr(dato)).catch(() => setQr(null));
        return () => { vigente = false; };
    }, [url]);

    return (
        <Card className="overflow-hidden">
            <div className="grid gap-5 p-5 sm:grid-cols-[auto_1fr] sm:items-center">
                {url && qr ? (
                    <img src={qr} alt="Código QR para descargar la app en el celular" width={176} height={176} className="mx-auto rounded-lg bg-white p-1 ring-1 ring-slate-200" />
                ) : (
                    <IconoTile icon={Smartphone} size="lg" />
                )}
                <div className="min-w-0 space-y-2">
                    <p className="flex items-center gap-2 text-sm font-medium text-slate-900">
                        <Smartphone className="h-4 w-4 text-brand-ink" aria-hidden="true" />
                        Tiqo, app para celular (Android) {version ? `v${version}` : 'sin publicar'}
                    </p>
                    <ol className="list-decimal space-y-1 pl-5 text-sm text-slate-600">
                        <li>Escanea el QR con la cámara del celular o abre el enlace y descarga la app.</li>
                        <li>Permite «instalar apps desconocidas» si el celular lo pide.</li>
                        <li>Crea aquí una terminal <b>solo para ese celular</b>, toca «Vincular agente» y escanea el QR desde Tiqo. No uses la de la computadora: cada terminal admite un solo agente.</li>
                        <li>Tiqo encuentra la ticketera Bluetooth o de red y la deja lista.</li>
                    </ol>
                    {sha256 && <code className="block break-all rounded-md bg-slate-50 px-2 py-1 font-mono text-[0.6875rem] text-slate-500">SHA-256 · {sha256}</code>}
                    {url ? (
                        <a href={url} target="_blank" rel="noreferrer" className="volumen-marca inline-flex h-10 items-center justify-center gap-2 rounded-lg px-4 text-sm font-semibold text-on-brand hover:brightness-105">
                            <Download className="h-4 w-4" aria-hidden="true" />
                            Descargar app (.apk)
                        </a>
                    ) : (
                        <p className="text-xs text-slate-500">App pendiente de publicación (configura PRINT_AGENT_ANDROID_URL).</p>
                    )}
                </div>
            </div>
        </Card>
    );
}

/** Código temporal de vinculación (llega una sola vez en la sesión flash). */
export function CodigoVinculacion({ codigo }) {
    // QR para la app del celular (Tiqo): al escanearlo se vincula sola, sin escribir nada.
    const [qr, setQr] = useState(null);
    useEffect(() => {
        let vigente = true;
        const enlace = `tiqo://vincular?url=${encodeURIComponent(window.location.origin)}&codigo=${encodeURIComponent(codigo.codigo)}`;
        if (window.location.protocol === 'https:') QRCode.toDataURL(enlace, { margin: 1, width: 160, errorCorrectionLevel: 'M' }).then((dato) => vigente && setQr(dato)).catch(() => setQr(null));
        return () => { vigente = false; };
    }, [codigo.codigo]);

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
            <div className="flex items-center gap-4">
                {qr && (
                    <figure className="text-center">
                        <img src={qr} alt="QR para vincular la app del celular" width={128} height={128} className="rounded-lg bg-white p-1 ring-1 ring-slate-200" />
                        <figcaption className="mt-1 text-[0.6875rem] text-slate-500">Escanéalo con el celular</figcaption>
                    </figure>
                )}
                <Button icon={Copy} onClick={copiar}>Copiar código</Button>
            </div>
        </Card>
    );
}
