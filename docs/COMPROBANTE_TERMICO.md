# Comprobante térmico (boleta y factura)

Una sola representación del comprobante para la pantalla de Ventas, la impresión desde el
navegador y el agente de impresión Windows (cola gráfica USB/Bluetooth, raster y ESC/POS RAW).

## Fuente única de datos

```
PresentarComprobanteService::paraVenta()  ──►  PDF (resources/views/pdf/comprobante.blade.php)
            │
            ├──►  CrearTrabajoImpresionService::ticket()  ──►  agente (trabajo de impresión v2)
            │
            └──►  GET /ventas/{venta}/representacion      ──►  vista previa React
```

Laravel calcula y entrega todo: importes, IGV, monto en letras, hash y contenido del QR fiscal.
React y el agente **solo presentan**; ninguno recalcula impuestos, totales ni el QR.

## Composición compartida

| Pieza | Agente (`agente-impresion/src/infrastructure/printers`) | Sistema web (`resources/js/lib/comprobante/ticketTermico.js`) |
|---|---|---|
| Reglas de presentación (modelo) | `ModeloTicket.ts` + `FormatoComprobante.ts` | `construirModeloTicket` |
| Marcado HTML | `HtmlTicket.ts` → `cuerpoTicketHtml` | `cuerpoTicketHtml` |
| Hoja de estilos | `EstilosTicket.ts` → `ESTILOS_TICKET` | `ESTILOS_TICKET` |
| Texto ESC/POS | `EscPosTicket.ts` (lee el mismo modelo) | — |

Son dos copias porque el agente es un proyecto Electron aparte (TypeScript/CommonJS) y el
sistema web es React/Vite. Para que no se separen, `tests/js/ticketTermico.test.js` carga el
agente compilado y exige que, con los mismos datos, **el modelo, el marcado y la hoja de estilos
sean idénticos** (58 mm, 80 mm y 80 mm con 64 mm imprimibles). Cualquier cambio de diseño se
hace en ambos lados y se valida con:

```
npm run test:js                    # sistema web (compara con ../agente-impresion/build)
cd ../agente-impresion && npm test # agente
```

`AGENTE_IMPRESION_DIR` permite indicar otra ruta del agente. Si el agente no está compilado,
esas dos pruebas de equivalencia se omiten (las demás se ejecutan igual).

## Jerarquía

1. Emisor: nombre comercial (negrita), razón social, RUC, domicilio fiscal, establecimiento, teléfono.
2. Documento: BOLETA DE VENTA ELECTRÓNICA / FACTURA ELECTRÓNICA y serie-correlativo (negrita).
3. Emisión: fecha, hora, moneda y forma de pago.
4. Adquirente: «Cliente:» (boleta) o «Razón social:» (factura), documento y dirección; boleta sin documento → CLIENTES VARIOS.
5. Detalle (ver anchos).
6. Totales: OP. GRAVADAS, OP. EXONERADAS y DESCUENTOS solo si existen, IGV (%), TOTAL (negrita) y monto en letras.
7. Pago: cada método con su importe; Recibido, Vuelto y N° de operación cuando existen.
8. Cajero y caja.
9. Información fiscal: QR, leyenda de representación impresa, consulta SUNAT y «Resumen:» (hash).
10. Pie configurado en Datos Generales.

Todos los importes se muestran como `S/ 0.00`. Las cantidades conservan hasta 3 decimales.

## Anchos

| Papel | Imprimible | Columnas ESC/POS | Detalle |
|---|---|---|---|
| 58 mm | 48 mm (384 puntos) | 32 | Descripción en su línea; debajo `cant. unidad x P. unit.` e importe a la derecha. Si no cabe en una línea, la unidad pasa a la descripción: `Arroz (SACO X 5 KG)` / `1.5 x S/ 25.93   S/ 38.90`. |
| 80 mm | 72 mm (576 puntos) o 64 mm | 48 / 42 | Descripción en su línea; debajo tres columnas alineadas: CANT. UNIDAD · P. UNIT. · IMPORTE. |

El ancho imprimible de 64 mm se elige en el agente para cabezales de 512 puntos. La vista
previa usa el ancho de papel de la impresora de la caja de la venta (80 mm si no tiene) y
permite cambiarlo.

## Diseño térmico

- Fondo blanco, texto negro puro (sin grises: el cabezal los tramea).
- Negrita solo en nombre comercial, tipo y número de comprobante, cabecera del detalle y total.
- Separadores de 1 px discontinuos (`-` en ESC/POS). Sin reglas dobles, sin rellenos oscuros ni bordes gruesos.
- ESC/POS: doble alto únicamente en el total (antes también en nombre y número).
- QR de 24 mm (58) / 28 mm (80) en gráfico, módulo 5 / 6 puntos en ESC/POS, corrección M y margen de silencio.

## Estados SUNAT

- **ACEPTADO**: representación fiscal completa (QR, hash, leyenda). Se puede imprimir en la caja y desde el navegador.
- **PENDIENTE / ERROR**: vista previa con los importes reales pero **sin** QR, hash ni leyenda fiscal; aviso fuera del papel. No se imprime.
- **RECHAZADO**: misma vista de referencia, marcada fuera del papel como no válida y con el motivo de SUNAT cuando la pantalla lo recibió.

Laravel solo encola trabajos de impresión para comprobantes aceptados (sin cambios).

## Diferencias técnicas inevitables

- **ESC/POS RAW** usa la fuente monoespaciada de la impresora: misma información, orden y alineación por columnas, pero no la tipografía Arial de la vista previa. El doble alto del total no existe en HTML (allí es 1,33×).
- **QR**: el agente gráfico usa `qrcode-generator` y el navegador `qrcode`; ambos codifican el mismo texto con corrección M, pero el dibujo de módulos puede variar levemente. En ESC/POS el QR lo dibuja la propia impresora (`GS ( k`).
- **Tildes en ESC/POS** dependen del juego de caracteres elegido en el agente (PC850, Latinoamérica o sin tildes).
- **Logo**: hoy ninguno de los medios lo imprime (el contrato no lo incluye); no se agregó para no romper la equivalencia ni enviar imágenes densas al cabezal.
- **Impresión desde el navegador** depende del controlador elegido en el diálogo del sistema; la página se envía con el ancho del papel y el alto exacto del contenido.
