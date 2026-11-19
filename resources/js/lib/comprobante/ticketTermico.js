/**
 * Composición del comprobante térmico (boleta/factura) para la vista previa de Ventas y la
 * impresión desde el navegador.
 *
 * Es una copia fiel, en JavaScript, de la composición del agente de impresión Windows
 * (agente-impresion/src/infrastructure/printers: ModeloTicket.ts, HtmlTicket.ts y
 * EstilosTicket.ts). Mismo contrato de entrada (PresentarComprobanteService), mismas reglas,
 * mismo marcado y la misma hoja de estilos. tests/js/ticketTermico.test.js compara ambas
 * implementaciones con los mismos datos: si cambias algo aquí, cámbialo también en el agente.
 *
 * Solo presenta: los importes, el QR fiscal y el monto en letras llegan ya calculados por
 * Laravel. Aquí jamás se recalcula IGV, totales ni el contenido del QR.
 */

// ---------------------------------------------------------------- Formato (FormatoComprobante.ts)

export const limpiarTexto = (value) => String(value ?? '').replace(/[\x00-\x1F\x7F]/g, ' ').replace(/\s+/g, ' ').trim();

export const simboloMoneda = (moneda) => (moneda?.toUpperCase() === 'PEN' ? 'S/' : limpiarTexto(moneda) || 'S/');

/** Normaliza solamente para impresión a dos decimales, incluso si Laravel serializa un entero. */
export function formatearImporte(value) {
    const importe = Number(value ?? 0);
    return Number.isFinite(importe) ? (Math.round((importe + Number.EPSILON) * 100) / 100).toFixed(2) : '0.00';
}

export function formatearCantidad(value) {
    const cantidad = Number(value ?? 0);
    if (!Number.isFinite(cantidad)) return '0';
    return Number.isInteger(cantidad) ? String(cantidad) : String(Math.round((cantidad + Number.EPSILON) * 1000) / 1000);
}

/**
 * Ancho imprimible real: 58 mm imprime ~48 mm (384 puntos) y 80 mm imprime 72 mm (576 puntos)
 * o 64 mm (512 puntos) según el cabezal. La fuente A de ESC/POS ocupa 12 puntos (1,5 mm).
 */
export function anchoImprimible(papelMm, preferido) {
    if (Number(papelMm) === 58) return 48;
    return preferido === 64 ? 64 : 72;
}

export const columnasPara = (anchoMm) => Math.floor((anchoMm * 8) / 12);

/** Desde 42 columnas (80 mm) el detalle va en tres columnas: cantidad, precio e importe. */
export const DETALLE_EN_COLUMNAS_DESDE = 42;

function pagosPresentables(comprobante) {
    return (comprobante?.pagos ?? []).map((pago) => ({
        metodo: limpiarTexto(pago.metodo) || 'No especificado',
        monto: formatearImporte(pago.monto),
        ...(pago.monto_recibido !== null && pago.monto_recibido !== undefined ? { montoRecibido: formatearImporte(pago.monto_recibido) } : {}),
        ...(pago.vuelto !== null && pago.vuelto !== undefined && Number(pago.vuelto) > 0 ? { vuelto: formatearImporte(pago.vuelto) } : {}),
        ...(pago.referencia ? { referencia: limpiarTexto(pago.referencia) } : {}),
    }));
}

/** Fecha y hora de emisión en la zona horaria de Perú, sin depender de la zona del equipo. */
function fechaHoraEmision(valor) {
    if (!valor) return null;
    const fecha = new Date(valor);
    if (Number.isNaN(fecha.getTime())) return null;
    const partes = Object.fromEntries(new Intl.DateTimeFormat('es-PE', {
        timeZone: 'America/Lima', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
    }).formatToParts(fecha).map((parte) => [parte.type, parte.value]));
    return { fecha: `${partes.day}/${partes.month}/${partes.year}`, hora: `${partes.hour}:${partes.minute}` };
}

// ---------------------------------------------------------------- Contenido (CrearTrabajoImpresionService::ticket)

/**
 * Arma el mismo `contenido` que Laravel adjunta al trabajo de impresión del agente, a partir
 * del contrato de PresentarComprobanteService. `fiscal` es falso mientras SUNAT no acepte el
 * comprobante: entonces no se muestran QR, hash ni leyenda de representación impresa.
 */
export function contenidoDesdeComprobante(comprobante, { anchoPapelMm = 80, fiscal = true } = {}) {
    const vigente = fiscal ? comprobante : { ...comprobante, hash_cpe: null, leyendas: null };
    return {
        comprobante: vigente,
        representacion_fiscal: Boolean(fiscal),
        estado_sunat: comprobante.estado_sunat,
        qr_fiscal: fiscal ? comprobante.qr_fiscal : null,
        documento: comprobante.documento.numero,
        titulo: comprobante.documento.titulo,
        emitido_en: comprobante.documento.emitido_en,
        cliente: comprobante.cliente.nombre,
        documento_cliente: `${comprobante.cliente.tipo_documento}: ${comprobante.cliente.numero_documento}`,
        total: String(comprobante.totales.total),
        moneda: 'PEN',
        impresora: { ancho_papel_mm: Number(anchoPapelMm) === 58 ? 58 : 80 },
        items: (comprobante.items ?? []).map((item) => ({
            nombre: item.nombre, presentacion: item.presentacion, unidad: item.unidad, codigo: item.codigo,
            cantidad: String(item.cantidad), precio_unitario: String(item.precio_unitario), subtotal: String(item.subtotal),
        })),
    };
}

/**
 * Modelo para la vista previa de Ventas. Mientras SUNAT no acepte el comprobante no hay
 * representación fiscal: sin QR, sin hash y sin leyenda de "Representación impresa".
 * (El agente nunca recibe ese caso: Laravel solo encola comprobantes aceptados.)
 */
export function modeloVistaPrevia(comprobante, { anchoPapelMm = 80, fiscal = true } = {}) {
    const modelo = construirModeloTicket({ tipo: 'TICKET_VENTA', contenido: contenidoDesdeComprobante(comprobante, { anchoPapelMm, fiscal }) });
    return fiscal ? modelo : { ...modelo, qr: null, hash: '', leyendas: [] };
}

// ---------------------------------------------------------------- Modelo (ModeloTicket.ts)

/** "Unidad base" no aporta información al cliente: se imprime como UND. */
function unidadVisible(item) {
    const candidata = limpiarTexto(item.unidad) || limpiarTexto(item.presentacion);
    return !candidata || /^unidad( base)?$/i.test(candidata) ? 'UND' : candidata.toUpperCase();
}

/**
 * Orden de la representación impresa: emisor, documento, emisión, adquirente, detalle,
 * totales, pago, cajero/caja, QR y leyendas fiscales, mensaje del negocio.
 */
export function construirModeloTicket(job, opciones = {}) {
    const contenido = job.contenido;
    const comprobante = contenido.comprobante;
    const anchoMm = anchoImprimible(contenido.impresora.ancho_papel_mm, opciones.anchoImprimibleMm);
    const moneda = simboloMoneda(contenido.moneda);
    const conMoneda = (valor) => `${moneda} ${formatearImporte(valor)}`;

    // ---------- Emisor
    const nombreComercial = limpiarTexto(comprobante?.emisor.nombre_comercial);
    const razonSocial = limpiarTexto(comprobante?.emisor.razon_social);
    const establecimiento = comprobante?.emisor.establecimiento;
    const direccionFiscal = limpiarTexto(comprobante?.emisor.direccion);
    const direccionLocal = limpiarTexto(establecimiento?.direccion);
    const direccion = direccionFiscal || direccionLocal;
    const mostrarLocal = Boolean(direccionLocal && direccionFiscal && direccionLocal.toUpperCase() !== direccionFiscal.toUpperCase());
    const telefono = limpiarTexto(establecimiento?.telefono);

    // ---------- Documento y emisión
    const titulo = limpiarTexto(contenido.titulo ?? comprobante?.documento.titulo);
    const emisionCalculada = fechaHoraEmision(contenido.emitido_en ?? comprobante?.documento.emitido_en);
    const fecha = limpiarTexto(comprobante?.documento.fecha) || emisionCalculada?.fecha || '';
    const hora = limpiarTexto(comprobante?.documento.hora) || emisionCalculada?.hora || '';
    const monedaTexto = limpiarTexto(comprobante?.documento.moneda) || (contenido.moneda?.toUpperCase() === 'PEN' ? 'SOLES' : limpiarTexto(contenido.moneda));
    const formaPago = limpiarTexto(comprobante?.documento.forma_pago);
    const emision = [
        ...(fecha ? [{ etiqueta: 'Fecha:', valor: fecha }] : []),
        ...(hora ? [{ etiqueta: 'Hora:', valor: hora }] : []),
        ...(monedaTexto ? [{ etiqueta: 'Moneda:', valor: monedaTexto }] : []),
        ...(formaPago ? [{ etiqueta: 'Forma de pago:', valor: formaPago }] : []),
    ];

    // ---------- Adquirente
    const datosCliente = comprobante?.cliente;
    const esFactura = comprobante?.documento.codigo_sunat === '01' || /FACTURA/i.test(titulo);
    const identificado = datosCliente?.identificado ?? !/^0*$/.test(limpiarTexto(datosCliente?.numero_documento));
    const tipoCorto = limpiarTexto(datosCliente?.tipo_documento_corto) || limpiarTexto(datosCliente?.tipo_documento);
    const cliente = datosCliente
        ? identificado
            ? {
                etiquetaNombre: esFactura ? 'Razón social:' : 'Cliente:',
                nombre: limpiarTexto(datosCliente.nombre),
                documento: `${tipoCorto || 'Doc.'}: ${limpiarTexto(datosCliente.numero_documento)}`,
                direccion: limpiarTexto(datosCliente.direccion),
            }
            // Boleta a consumidor final sin documento: la práctica estándar es "CLIENTES VARIOS".
            : { etiquetaNombre: 'Cliente:', nombre: 'CLIENTES VARIOS', documento: '', direccion: '' }
        : { etiquetaNombre: 'Cliente:', nombre: limpiarTexto(contenido.cliente), documento: limpiarTexto(contenido.documento_cliente), direccion: '' };

    // ---------- Detalle
    const columnas = columnasPara(anchoMm);
    const detalleEnColumnas = columnas >= DETALLE_EN_COLUMNAS_DESDE;
    const items = (contenido.items ?? []).map((item) => {
        const cantidad = formatearCantidad(item.cantidad);
        const unidad = unidadVisible(item);
        const precioUnitario = conMoneda(item.precio_unitario);
        const importe = conMoneda(item.subtotal);
        const cantidadUnidad = `${cantidad} ${unidad}`;
        const nombre = limpiarTexto(item.nombre) || 'Artículo';
        const detalle = `${cantidadUnidad} x ${precioUnitario}`;
        // 58 mm: si "cant. unidad x precio" e importe no caben en una línea (sangría de 2),
        // la unidad pasa a la descripción para que los importes nunca se partan.
        const cabe = detalleEnColumnas || 2 + detalle.length + 1 + importe.length <= columnas;
        return {
            descripcion: cabe ? nombre : `${nombre} (${unidad})`,
            cantidadUnidad,
            detalle: cabe ? detalle : `${cantidad} x ${precioUnitario}`,
            cantidad, unidad, precioUnitario, importe,
        };
    });

    // ---------- Totales
    const totales = comprobante?.totales;
    const porcentajeIgv = totales?.igv_porcentaje;
    const subtotales = totales ? [
        { etiqueta: 'OP. GRAVADAS', valor: conMoneda(totales.gravadas) },
        ...(Number(totales.exoneradas) > 0 ? [{ etiqueta: 'OP. EXONERADAS', valor: conMoneda(totales.exoneradas) }] : []),
        ...(Number(totales.descuento) > 0 ? [{ etiqueta: 'DESCUENTOS', valor: `-${conMoneda(totales.descuento)}` }] : []),
        { etiqueta: porcentajeIgv !== null && porcentajeIgv !== undefined ? `IGV (${formatearCantidad(porcentajeIgv)}%)` : 'IGV', valor: conMoneda(totales.igv) },
    ] : [];

    // ---------- Pago
    const pagos = pagosPresentables(comprobante).flatMap((pago) => [
        { etiqueta: pago.metodo, valor: `${moneda} ${pago.monto}` },
        ...(pago.montoRecibido ? [{ etiqueta: 'Recibido', valor: `${moneda} ${pago.montoRecibido}` }] : []),
        ...(pago.vuelto ? [{ etiqueta: 'Vuelto', valor: `${moneda} ${pago.vuelto}` }] : []),
        ...(pago.referencia ? [{ etiqueta: `N° operación: ${pago.referencia}`, valor: '' }] : []),
    ]);

    const cajero = limpiarTexto(comprobante?.operacion?.cajero);
    const caja = limpiarTexto(comprobante?.operacion?.caja);
    const operacion = [
        ...(cajero ? [{ etiqueta: 'Cajero:', valor: cajero }] : []),
        ...(caja ? [{ etiqueta: 'Caja:', valor: caja }] : []),
    ];

    // ---------- Leyendas
    const fiscal = contenido.representacion_fiscal || Boolean(comprobante);
    const representacion = limpiarTexto(comprobante?.leyendas?.representacion)
        || (titulo ? `Representación impresa de la ${titulo}` : 'Representación impresa del comprobante electrónico');
    const leyendas = job.tipo === 'PRUEBA'
        ? ['PRUEBA DE IMPRESIÓN - SIN VALOR FISCAL']
        : fiscal ? [representacion, limpiarTexto(comprobante?.leyendas?.consulta)].filter(Boolean) : [];

    return {
        anchoMm,
        columnas,
        detalleEnColumnas,
        moneda,
        emisor: {
            nombre: nombreComercial || razonSocial,
            razonSocial: nombreComercial && razonSocial && nombreComercial.toUpperCase() !== razonSocial.toUpperCase() ? razonSocial : '',
            ruc: comprobante?.emisor.ruc ? `RUC ${limpiarTexto(comprobante.emisor.ruc)}` : '',
            direccion,
            establecimiento: mostrarLocal ? `${limpiarTexto(establecimiento?.nombre) || 'Establecimiento'}: ${direccionLocal}` : '',
            telefono: telefono ? `Tel.: ${telefono}` : '',
        },
        documento: { titulo, numero: limpiarTexto(contenido.documento ?? comprobante?.documento.numero), fecha, hora },
        emision,
        cliente,
        items,
        subtotales,
        total: conMoneda(totales?.total ?? contenido.total),
        totalLetras: limpiarTexto(totales?.total_letras),
        pagos,
        operacion,
        qr: contenido.qr_fiscal ? limpiarTexto(contenido.qr_fiscal) : null,
        hash: limpiarTexto(comprobante?.hash_cpe),
        leyendas,
        pie: limpiarTexto(comprobante?.emisor.pie_ticket),
    };
}

// ---------------------------------------------------------------- Marcado (HtmlTicket.ts)

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character] ?? character);

const fila = (f, clase = '') =>
    `<div class="tk-fila${clase ? ` ${clase}` : ''}"><span>${escapeHtml(f.etiqueta)}</span><span class="tk-num">${escapeHtml(f.valor)}</span></div>`;

const parrafo = (texto, clase = '') => (texto ? `<p${clase ? ` class="${clase}"` : ''}>${escapeHtml(texto)}</p>` : '');

const pares = (filas) => filas.map((f) => parrafo(`${f.etiqueta} ${f.valor}`.trim())).join('');

/** Marcado del comprobante, idéntico al de HtmlTicket.ts del agente. `qr` es el SVG del QR. */
export function cuerpoTicketHtml(m, qr = '') {
    const angosto = !m.detalleEnColumnas;

    const detalle = m.items.length ? [
        '<section class="tk-sec">',
        angosto
            ? '<div class="tk-cab"><div class="tk-fila"><span>DESCRIPCIÓN</span><span>IMPORTE</span></div><p>CANT. UNIDAD x P. UNIT.</p></div>'
            : '<div class="tk-cab"><p>DESCRIPCIÓN</p><div class="tk-cols"><span>CANT. UNIDAD</span><span>P. UNIT.</span><span>IMPORTE</span></div></div>',
        ...m.items.map((item) => `<div class="tk-item">${parrafo(item.descripcion)}${angosto
            ? fila({ etiqueta: item.detalle, valor: item.importe }, 'tk-sangria')
            : `<div class="tk-cols"><span>${escapeHtml(item.cantidadUnidad)}</span><span>${escapeHtml(item.precioUnitario)}</span><span>${escapeHtml(item.importe)}</span></div>`}</div>`),
        '</section>',
    ].join('') : '';

    const fiscal = qr || m.leyendas.length || m.hash ? [
        '<footer class="tk-sec tk-centro tk-fiscal">',
        qr ? `<div class="tk-qr">${qr}</div>` : '',
        ...m.leyendas.map((leyenda) => parrafo(leyenda, 'tk-leyenda')),
        m.hash ? parrafo(`Resumen: ${m.hash}`, 'tk-hash') : '',
        '</footer>',
    ].join('') : '';

    return [
        `<main class="tk ${angosto ? 'tk-58' : 'tk-80'}" style="width:${m.anchoMm}mm">`,
        '<header class="tk-centro">',
        m.emisor.nombre ? `<h1 class="tk-nombre">${escapeHtml(m.emisor.nombre)}</h1>` : '',
        parrafo(m.emisor.razonSocial), parrafo(m.emisor.ruc),
        parrafo(m.emisor.direccion, 'tk-chico'), parrafo(m.emisor.establecimiento, 'tk-chico'), parrafo(m.emisor.telefono, 'tk-chico'),
        '</header>',
        m.documento.titulo || m.documento.numero ? `<section class="tk-doc tk-centro">${parrafo(m.documento.titulo, 'tk-titulo')}${parrafo(m.documento.numero, 'tk-numero')}</section>` : '',
        m.emision.length ? `<section class="tk-sec tk-pares">${pares(m.emision)}</section>` : '',
        '<section class="tk-sec">',
        parrafo(`${m.cliente.etiquetaNombre} ${m.cliente.nombre}`),
        parrafo(m.cliente.documento),
        m.cliente.direccion ? parrafo(`Dirección: ${m.cliente.direccion}`) : '',
        '</section>',
        detalle,
        `<section class="tk-sec tk-totales">${m.subtotales.map((s) => fila(s)).join('')}</section>`,
        fila({ etiqueta: angosto ? 'TOTAL' : 'IMPORTE TOTAL', valor: m.total }, 'tk-total'),
        parrafo(m.totalLetras, 'tk-letras'),
        m.pagos.length ? `<section class="tk-sec">${m.pagos.map((pago) => (pago.valor ? fila(pago) : parrafo(pago.etiqueta))).join('')}</section>` : '',
        m.operacion.length ? `<section class="tk-sec tk-pares">${pares(m.operacion)}</section>` : '',
        fiscal,
        parrafo(m.pie, 'tk-pie tk-centro'),
        '</main>',
    ].join('');
}

// ---------------------------------------------------------------- Estilos (EstilosTicket.ts)

/** Copia exacta de ESTILOS_TICKET del agente (la prueba las compara carácter por carácter). */
export const ESTILOS_TICKET = `
.tk{background:#fff;color:#000;margin:0 auto;padding:2mm 0 4mm;font-family:Arial,"Segoe UI",Helvetica,sans-serif;line-height:1.3;text-align:left;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.tk,.tk *{box-sizing:border-box}
.tk *{margin:0;padding:0}
.tk-80{font-size:12px}
.tk-58{font-size:11px}
.tk p{overflow-wrap:break-word}
.tk-centro{text-align:center}
.tk-nombre{font-size:1.25em;font-weight:700;line-height:1.2;text-transform:uppercase;overflow-wrap:break-word}
.tk-chico{font-size:.92em}
.tk-doc,.tk-sec{border-top:1px dashed #000;margin-top:1.5mm;padding-top:1.5mm}
.tk-titulo{font-weight:700}
.tk-numero{font-weight:700;font-size:1.17em;letter-spacing:.3px}
.tk-pares{display:flex;flex-wrap:wrap;justify-content:space-between;column-gap:3mm}
.tk-58 .tk-pares{flex-direction:column}
.tk-fila{display:flex;justify-content:space-between;align-items:baseline;gap:2mm}
.tk-fila>span:first-child{min-width:0;overflow-wrap:break-word}
.tk-num{white-space:nowrap;text-align:right;font-variant-numeric:tabular-nums}
.tk-cab{font-weight:700;border-bottom:1px dashed #000;padding-bottom:1mm;margin-bottom:1mm}
.tk-cols{display:grid;grid-template-columns:minmax(0,1fr) 24% 24%;align-items:baseline}
.tk-cols>span:first-child{overflow-wrap:break-word}
.tk-cols>span:not(:first-child){text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.tk-item{margin-bottom:1mm;break-inside:avoid}
.tk-sangria{padding-left:3mm}
.tk-80 .tk-totales .tk-fila{padding-left:30%}
.tk-total{border-top:1px dashed #000;margin-top:1mm;padding-top:1mm;font-size:1.33em;font-weight:700}
.tk-letras{font-size:.92em;margin-top:1mm}
.tk-fiscal p{margin-top:1mm}
.tk-qr{display:flex;justify-content:center;margin:1mm 0}
.tk-qr svg,.tk-qr img{display:block}
.tk-leyenda{font-size:.92em}
.tk-hash{font-size:.84em;overflow-wrap:anywhere}
.tk-pie{margin-top:2mm}
`.trim();

/** Lado del QR en milímetros: legible con teléfono y con margen de silencio dentro del papel. */
export const ladoQrMm = (anchoMm) => (anchoMm < 64 ? 24 : 28);
