import { useEffect, useMemo, useState } from 'react';
import QRCode from 'qrcode';
import { cuerpoTicketHtml, ESTILOS_TICKET, ladoQrMm, modeloVistaPrevia } from '@/lib/comprobante/ticketTermico';

/** QR fiscal como SVG nítido del mismo tamaño físico que imprime el agente. */
function useQrSvg(contenido, ladoMm) {
    const [svg, setSvg] = useState('');

    useEffect(() => {
        let cancelado = false;
        setSvg('');
        if (!contenido) return undefined;

        QRCode.toString(contenido.slice(0, 700), { type: 'svg', errorCorrectionLevel: 'M', margin: 2, color: { dark: '#000000', light: '#ffffff' } })
            .then((marcado) => {
                if (!cancelado) setSvg(marcado.replace('<svg ', `<svg width="${ladoMm}mm" height="${ladoMm}mm" role="img" aria-label="Código QR" `));
            })
            .catch(() => { if (!cancelado) setSvg(''); });

        return () => { cancelado = true; };
    }, [contenido, ladoMm]);

    return svg;
}

/**
 * Modelo y marcado del comprobante para un ancho de papel. `listo` es falso mientras el QR
 * fiscal se genera: no se debe imprimir un comprobante aceptado sin su QR.
 */
export function useTicketTermico(comprobante, { anchoPapelMm = 80, fiscal = true } = {}) {
    const modelo = useMemo(
        () => (comprobante ? modeloVistaPrevia(comprobante, { anchoPapelMm, fiscal }) : null),
        [comprobante, anchoPapelMm, fiscal],
    );
    const lado = modelo ? ladoQrMm(modelo.anchoMm) : 0;
    const qr = useQrSvg(modelo?.qr ?? null, lado);
    const listo = Boolean(modelo) && (!modelo.qr || Boolean(qr));
    // Mientras el QR se genera se reserva su espacio para que el papel no salte.
    const qrMarcado = modelo?.qr ? (qr || `<div style="width:${lado}mm;height:${lado}mm"></div>`) : '';
    const html = useMemo(() => (modelo ? cuerpoTicketHtml(modelo, qrMarcado) : ''), [modelo, qrMarcado]);

    return { modelo, html, listo };
}

/**
 * Imprime desde el navegador el mismo documento que el agente envía a la cola gráfica de
 * Windows: página del ancho del papel, cuerpo del ancho imprimible y alto exacto del contenido.
 */
export function imprimirTicketEnNavegador(html, anchoPapelMm) {
    const marco = document.createElement('iframe');
    marco.setAttribute('aria-hidden', 'true');
    marco.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
    document.body.appendChild(marco);

    const doc = marco.contentDocument;
    doc.open();
    doc.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>Comprobante</title><style>html,body{background:#fff;margin:0;padding:0}${ESTILOS_TICKET}</style></head><body>${html}</body></html>`);
    doc.close();

    const imprimir = () => {
        const altoMm = Math.ceil((doc.documentElement.scrollHeight * 25.4) / 96) + 8;
        const pagina = doc.createElement('style');
        pagina.textContent = `@page{size:${anchoPapelMm}mm ${Math.max(altoMm, 40)}mm;margin:0}`;
        doc.head.appendChild(pagina);
        marco.contentWindow.focus();
        marco.contentWindow.print();
        // El diálogo de impresión es bloqueante en la mayoría de navegadores; se limpia después.
        setTimeout(() => marco.remove(), 1000);
    };

    if (doc.readyState === 'complete') imprimir();
    else marco.addEventListener('load', imprimir, { once: true });
}

/** Papel del comprobante: solo lo imprimible. Estado SUNAT y botones van fuera. */
export default function TicketTermico({ html, className = '', style }) {
    return (
        <div className={className} style={style}>
            <style>{ESTILOS_TICKET}</style>
            {/* cuerpoTicketHtml escapa todo texto comercial; el QR es SVG generado localmente. */}
            <div dangerouslySetInnerHTML={{ __html: html }} />
        </div>
    );
}
