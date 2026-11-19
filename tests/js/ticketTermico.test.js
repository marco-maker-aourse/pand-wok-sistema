/**
 * Comprobante térmico: vista previa de Ventas ⇔ agente de impresión Windows.
 *
 *   node --test tests/js        (npm run test:js)
 *
 * Usa datos ficticios (fixtures): no consulta la base de datos ni emite nada a SUNAT.
 * Si el agente compilado está disponible (../agente-impresion-restaurante/build o AGENTE_IMPRESION_RESTAURANTE_DIR),
 * se compara su salida con la del sistema web para los mismos datos.
 */
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import {
    construirModeloTicket, contenidoDesdeComprobante, cuerpoTicketHtml, ESTILOS_TICKET, modeloVistaPrevia,
} from '../../resources/js/lib/comprobante/ticketTermico.js';

const aqui = path.dirname(fileURLToPath(import.meta.url));
const fixture = (nombre) => JSON.parse(readFileSync(path.join(aqui, 'fixtures', `${nombre}.json`), 'utf8'));
const boleta = fixture('boleta');
const factura = fixture('factura');

/** Mismo `contenido` que arma CrearTrabajoImpresionService::ticket() en Laravel. */
function trabajoComoLaravel(comprobante, anchoPapelMm) {
    return {
        id: 1,
        tipo: 'TICKET_VENTA',
        contenido: {
            version: 2,
            comprobante,
            representacion_fiscal: true,
            estado_sunat: comprobante.estado_sunat,
            qr_fiscal: comprobante.qr_fiscal,
            documento: comprobante.documento.numero,
            titulo: comprobante.documento.titulo,
            emitido_en: comprobante.documento.emitido_en,
            cliente: comprobante.cliente.nombre,
            documento_cliente: `${comprobante.cliente.tipo_documento}: ${comprobante.cliente.numero_documento}`,
            total: String(comprobante.totales.total),
            moneda: 'PEN',
            copias: 1,
            impresora: { id: 1, conexion: 'SISTEMA', identificador: 'POS', puerto: null, ancho_papel_mm: anchoPapelMm, escpos: false, corte_automatico: true, abre_cajon: false },
            items: comprobante.items.map((d) => ({
                nombre: d.nombre, presentacion: d.presentacion, unidad: d.unidad, codigo: d.codigo,
                cantidad: String(d.cantidad), precio_unitario: String(d.precio_unitario), subtotal: String(d.subtotal),
            })),
        },
    };
}

const texto = (html) => html.replace(/<[^>]+>/g, ' ').replace(/&amp;/g, '&').replace(/\s+/g, ' ').trim();

// ------------------------------------------------------------------ Vista previa

test('boleta 80 mm: jerarquía, columnas y formato S/ 0.00', () => {
    const m = modeloVistaPrevia(boleta, { anchoPapelMm: 80 });
    assert.equal(m.anchoMm, 72);
    assert.equal(m.detalleEnColumnas, true);
    const t = texto(cuerpoTicketHtml(m, '<svg></svg>'));
    const orden = ['BODEGA ÑAÑA'.toLowerCase(), 'comercial ñaña s.a.c.', 'ruc 20123456789', 'boleta de venta electrónica', 'b001-00000010',
        'fecha: 18/11/2026', 'cliente: clientes varios', 'descripción', 'azúcar rubia', 'op. gravadas', 'igv (18%)', 'importe total',
        'son: treinta y siete', 'efectivo', 'cajero: maría lópez', 'representación impresa', 'resumen: ab3+xyz/q9=', '¡gracias por su compra!'];
    const minus = t.toLowerCase();
    let desde = -1;
    for (const fragmento of orden) {
        const posicion = minus.indexOf(fragmento, desde + 1);
        assert.ok(posicion > desde, `"${fragmento}" aparece en su lugar`);
        desde = posicion;
    }
    assert.match(t, /2 BOLSA X 1 KG S\/ 4\.50 S\/ 9\.00/);
    assert.match(t, /0\.5 KILOGRAMO S\/ 7\.00 S\/ 3\.50/, 'cantidad fraccionada');
    assert.match(t, /IMPORTE TOTAL S\/ 37\.80/);
    assert.match(t, /Recibido S\/ 50\.00 Vuelto S\/ 12\.20/);
    assert.doesNotMatch(t, /Unidad base/);
});

test('boleta 58 mm: importes en una sola línea; una unidad larga pasa a la descripción', () => {
    const m = modeloVistaPrevia(boleta, { anchoPapelMm: 58 });
    assert.equal(m.anchoMm, 48);
    assert.equal(m.detalleEnColumnas, false);
    assert.deepEqual(m.items.map((i) => [i.descripcion, i.detalle, i.importe]), [
        ['Azúcar rubia (BOLSA X 1 KG)', '2 x S/ 4.50', 'S/ 9.00'],
        ['Café pilado de altura con una descripción comercial bastante extensa', '1 UND x S/ 25.30', 'S/ 25.30'],
        ['Piña golden (KILOGRAMO)', '0.5 x S/ 7.00', 'S/ 3.50'],
    ]);
    const html = cuerpoTicketHtml(m);
    assert.match(html, /class="tk tk-58" style="width:48mm"/);
    assert.match(html, /TOTAL<\/span><span class="tk-num">S\/ 37\.80/);
});

test('factura: razón social y RUC del adquirente, descuento, exoneradas y pagos mixtos sin duplicar', () => {
    const t = texto(cuerpoTicketHtml(modeloVistaPrevia(factura, { anchoPapelMm: 80 })));
    assert.match(t, /FACTURA ELECTRÓNICA F001-00000123/);
    assert.match(t, /Razón social: DISTRIBUIDORA ANDINA DEL CENTRO S\.A\.C\. RUC: 20100055237 Dirección: Av\. Argentina 4793, Callao/);
    assert.match(t, /OP\. GRAVADAS S\/ 42\.37 OP\. EXONERADAS S\/ 2\.00 DESCUENTOS -S\/ 0\.50 IGV \(18%\) S\/ 7\.63/);
    assert.match(t, /IMPORTE TOTAL S\/ 51\.50/);
    assert.match(t, /Yape S\/ 21\.50 N° operación: OP-998877 Efectivo S\/ 30\.00 Recibido S\/ 50\.00 Vuelto S\/ 20\.00/);
    assert.equal(t.match(/S\/ 21\.50/g).length, 1);
    assert.match(t, /0\.25 UND S\/ 8\.00 S\/ 2\.00/);
});

test('comprobante pendiente: sin QR, sin hash y sin leyenda de representación impresa', () => {
    const m = modeloVistaPrevia({ ...boleta, estado_sunat: 'PENDIENTE' }, { anchoPapelMm: 80, fiscal: false });
    assert.equal(m.qr, null);
    assert.equal(m.hash, '');
    assert.deepEqual(m.leyendas, []);
    const html = cuerpoTicketHtml(m, '');
    assert.doesNotMatch(html, /tk-fiscal|Resumen:|Representación impresa/);
    // Los importes siguen siendo los del backend.
    assert.match(texto(html), /IMPORTE TOTAL S\/ 37\.80/);
});

test('el QR usa exactamente el contenido fiscal de Laravel', () => {
    assert.equal(modeloVistaPrevia(boleta).qr, boleta.qr_fiscal);
    assert.equal(modeloVistaPrevia({ ...boleta, qr_fiscal: null }).qr, null, 'sin QR fiscal no hay QR');
});

test('el texto comercial nunca se interpreta como HTML', () => {
    const html = cuerpoTicketHtml(modeloVistaPrevia({ ...boleta, cliente: { ...factura.cliente, nombre: '<img src=x onerror=alert(1)>' } }));
    assert.doesNotMatch(html, /<img/);
    assert.match(html, /&lt;img src=x/);
});

test('estilos térmicos: sin rellenos oscuros, sin bordes gruesos, solo negro sobre blanco', () => {
    assert.doesNotMatch(ESTILOS_TICKET, /[2-9]px solid|background:#(?!fff)/i);
    const colores = ESTILOS_TICKET.match(/#[0-9a-f]{3,6}\b/gi);
    assert.deepEqual([...new Set(colores)].sort(), ['#000', '#fff']);
});

test('la vista previa no altera importes ni redondea cantidades más allá de 3 decimales', () => {
    const m = modeloVistaPrevia({ ...boleta, items: [{ ...boleta.items[0], cantidad: 1.2345, precio_unitario: 3.333, subtotal: 4.11 }] });
    assert.equal(m.items[0].cantidad, '1.235');
    assert.equal(m.items[0].precioUnitario, 'S/ 3.33');
    assert.equal(m.items[0].importe, 'S/ 4.11');
});

test('contenidoDesdeComprobante arma el mismo trabajo que Laravel envía al agente', () => {
    for (const ancho of [58, 80]) {
        const web = construirModeloTicket({ tipo: 'TICKET_VENTA', contenido: contenidoDesdeComprobante(boleta, { anchoPapelMm: ancho }) });
        const laravel = construirModeloTicket(trabajoComoLaravel(boleta, ancho));
        assert.deepEqual(web, laravel);
    }
});

// ------------------------------------------------------------------ Equivalencia con el agente

const dirAgente = process.env.AGENTE_IMPRESION_RESTAURANTE_DIR || path.resolve(aqui, '../../../agente-impresion-restaurante');
const impresoras = path.join(dirAgente, 'build/src/infrastructure/printers');
const hayAgente = existsSync(path.join(impresoras, 'HtmlTicket.js')) && existsSync(path.join(impresoras, 'EstilosTicket.js'));
const requerir = createRequire(import.meta.url);

test('agente: mismo modelo, mismo marcado y misma hoja de estilos para los mismos datos', { skip: !hayAgente && 'agente de impresión no compilado' }, () => {
    const agente = {
        ...requerir(path.join(impresoras, 'ModeloTicket.js')),
        ...requerir(path.join(impresoras, 'HtmlTicket.js')),
        ...requerir(path.join(impresoras, 'EstilosTicket.js')),
    };
    assert.equal(ESTILOS_TICKET, agente.ESTILOS_TICKET, 'la hoja de estilos es idéntica');

    for (const comprobante of [boleta, factura]) {
        for (const [papel, imprimible] of [[58, null], [80, null], [80, 64]]) {
            const opciones = imprimible ? { anchoImprimibleMm: imprimible } : {};
            const job = trabajoComoLaravel(comprobante, papel);
            const web = construirModeloTicket({ tipo: 'TICKET_VENTA', contenido: contenidoDesdeComprobante(comprobante, { anchoPapelMm: papel }) }, opciones);
            const impreso = agente.construirModeloTicket(job, opciones);
            const caso = `${comprobante.documento.numero} en ${papel} mm${imprimible ? ` (${imprimible} imprimibles)` : ''}`;
            assert.deepEqual(web, impreso, `modelo: ${caso}`);
            assert.equal(cuerpoTicketHtml(web, '<svg>QR</svg>'), agente.cuerpoTicketHtml(impreso, '<svg>QR</svg>'), `marcado: ${caso}`);
        }
    }
});

test('agente ESC/POS: los mismos importes y textos que la vista previa', { skip: !hayAgente && 'agente de impresión no compilado' }, () => {
    const { renderEscPos } = requerir(path.join(impresoras, 'EscPosTicket.js'));
    for (const papel of [58, 80]) {
        const bytes = renderEscPos(trabajoComoLaravel(factura, papel));
        const impreso = bytes.toString('latin1');
        const m = modeloVistaPrevia(factura, { anchoPapelMm: papel });
        for (const valor of [m.total, ...m.subtotales.map((s) => s.valor), ...m.items.map((i) => i.importe), ...m.pagos.map((p) => p.valor).filter(Boolean)]) {
            assert.ok(impreso.includes(valor), `${papel} mm imprime ${valor}`);
        }
        assert.ok(impreso.includes('F001-00000123') && impreso.includes('20100055237'));
    }
});
