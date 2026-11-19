# Facturación electrónica con SUNAT

## Alcance y arquitectura

La emisión electrónica está integrada al POS existente. No es un servicio
independiente: Laravel registra ventas y pagos en PostgreSQL, genera un XML
UBL 2.1, lo firma con el certificado digital del emisor, lo envía por SOAP a
SUNAT y procesa la Constancia de Recepción (CDR).

```text
POS React
  ├─ POST /ventas ──> VentaController
  │                    └─ RegistrarVentaService (transacción)
  │                         ├─ correlativo y cabecera
  │                         ├─ detalle y descuento de inventario
  │                         └─ pago, efectivo recibido y vuelto
  │
  └─ respuesta al cajero (estado actualizado)
       └─ EmitirComprobanteSunatJob (ejecución local en desarrollo)
            └─ SunatEmisionService
                 ├─ valida y lee datos de la venta/emisor
                 ├─ genera UBL 2.1 y firma XML
                 ├─ guarda XML/ZIP y llama sendBill por SOAP
                 └─ lee el CDR y actualiza el estado fiscal

Si SUNAT acepta:
  SunatReceipt (React) ──> QR en el navegador ──> imprimir 80 mm o guardar PDF
```

La venta y su emisión son dos operaciones separadas deliberadamente. Si el
servicio externo no responde, la venta, el pago y los movimientos de inventario
ya confirmados se conservan; el comprobante queda pendiente de revisión o
reintento.

## Lectura rápida: qué hace el sistema hoy

| Funcionalidad | Situación actual |
| --- | --- |
| Registrar boleta o factura desde el POS | Implementado; usa el cliente, productos y precios guardados. |
| Descontar inventario y registrar el pago | Implementado en la misma transacción de la venta. |
| Enviar el comprobante a SUNAT al confirmar | En desarrollo se ejecuta con `sync` después de guardar la venta. |
| Reintentar una emisión pendiente | Disponible desde el detalle de la venta en el historial. |
| Confirmar si SUNAT aceptó | Se lee el código de respuesta del CDR; código `0` significa aceptado. |
| Mostrar QR y comprobante | Implementado en el navegador cuando el estado es `ACEPTADO`. |
| Imprimir o guardar como PDF | Manual: el usuario abre el diálogo de impresión del navegador. |
| Imprimir sin intervención del usuario | No implementado. |
| Enviar el comprobante al correo del cliente | No implementado. |
| Crear y guardar PDF desde Laravel | Disponible bajo demanda para comprobantes aceptados. |

La palabra **electrónico** describe el comprobante enviado a SUNAT como XML
firmado. El ticket que ve el cliente es su representación impresa: en este
proyecto se dibuja en React y se imprime desde el navegador. No son el mismo
archivo ni se producen en el mismo paso.

## Conceptos en palabras sencillas

- **Venta registrada:** la operación comercial quedó guardada en la base de
  datos; se reservó su número y se registraron pago y stock. Esto no confirma,
  por sí solo, que SUNAT haya aceptado el comprobante.
- **XML UBL:** documento estructurado con los datos fiscales y comerciales de
  la factura o boleta. Es lo que el servicio firma y empaqueta para enviar.
- **Firma digital:** sello criptográfico producido con la clave privada del
  certificado del emisor. Permite verificar integridad y origen del XML.
- **ZIP de envío:** archivo que contiene el XML firmado y se transmite a SUNAT.
- **SOAP:** protocolo que el servicio actual usa para invocar `sendBill`.
- **CDR:** archivo de respuesta que SUNAT devuelve. El código dentro del CDR es
  el resultado que el sistema usa para clasificar la emisión.
- **Hash:** resumen calculado como parte de la firma del XML. El ticket lo
  muestra y el QR actual lo incluye.
- **Correlativo:** número secuencial dentro de la serie del comprobante. Se
  reserva durante el registro de la venta, no al recibir la respuesta de SUNAT.
- **Pendiente:** todavía no hay confirmación de aceptación. Puede haber un
  problema temporal o de configuración; no significa que la venta desapareció.

## Recorrido de una venta electrónica

### 1. Preparar POS y datos comerciales

El usuario inicia sesión y necesita el permiso `ventas.registrar`. El POS
(`resources/js/Pages/Ventas/Create.jsx`) consulta sus datos de sucursal, turno,
clientes, tipos de documento, métodos de pago y catálogo.

Para registrar una venta se requiere un turno de caja abierto para el usuario y
la sucursal actuales. El tipo seleccionado en el POS se traduce a los códigos
SUNAT usados por el catálogo:

| Comprobante en el POS | Código SUNAT | Serie configurada |
| --- | --- | --- |
| Boleta | `03` | `serie_boleta` |
| Factura | `01` | `serie_factura` |

Para una factura, la emisión exige cliente con RUC de 11 dígitos y código de
tipo de documento `6`. La boleta admite el tipo de cliente que se haya
registrado y validado en el catálogo.

Antes de probar una emisión deben estar configurados, para la sucursal
correspondiente:

- En `operaciones.datos_gen`: RUC del emisor, razón social, dirección fiscal,
  porcentaje de IGV y series de boleta/factura.
- En `inventario.sucursales`: `codigo_establecimiento`, cuatro dígitos. El
  código se edita junto con Datos Generales, pero se guarda en la sucursal.
- En productos: unidad con código SUNAT y descripción. El código SUNAT del
  producto se incluye en el XML si está disponible.
- En el entorno local del servidor: usuario/clave SOL, ruta/contraseña del
  certificado y URL del servicio. No guardar credenciales ni certificados en
  Git, JavaScript o este documento.

### 2. Confirmar y registrar la venta

Al confirmar la operación, el POS envía `POST /ventas` con:

- Cliente, método de pago, referencia si aplica y tipo de comprobante.
- Líneas identificadas por presentación y cantidad.
- Efectivo recibido cuando el método es efectivo.

El navegador no es la fuente de verdad para precios o importes. `VentaController`
valida la entrada y `RegistrarVentaService` trabaja dentro de una transacción:

1. Bloquea y vuelve a comprobar el turno abierto.
2. Recupera los precios configurados en `inventario.precio_producto`.
3. Reserva serie y correlativo mediante `ReservarCorrelativoService`.
4. Calcula operaciones gravadas/exoneradas, IGV, descuento y total.
5. Inserta cabecera y detalle de venta.
6. Descuenta existencias para productos (no para servicios).
7. Guarda el pago. Si es efectivo, valida que el monto recibido cubra el total
   y calcula el vuelto.

Si una etapa falla, la transacción revierte los cambios de venta, pago e
inventario. La respuesta correcta de `POST /ventas` incluye el ID, serie,
correlativo, fecha, importes, cliente, líneas y pago persistidos. El POS usa esa
respuesta para continuar.

Una forma práctica de entender este límite:

```text
Antes de confirmar:
  no hay venta persistida

POST /ventas termina correctamente:
  la venta ya existe en el sistema
  el correlativo ya quedó reservado
  el stock y el pago ya se registraron

Después se inicia la emisión:
  SUNAT puede aceptar, rechazar o no responder
  un problema de SUNAT no deshace la venta ya registrada
```

### 3. Envío automático a SUNAT

Después de confirmar `POST /ventas`, el backend inicia
`EmitirComprobanteSunatJob` con `afterCommit()`. En el entorno de desarrollo
la conexión `sync` lo ejecuta dentro de la misma petición; la venta ya está
guardada antes de iniciar la emisión.

La ruta está en `routes/web.php`, dentro del grupo autenticado, y requiere
`permission:ventas.registrar`. `SunatController` valida el identificador y
comprueba que la venta pertenezca a la sucursal del usuario; si no corresponde,
responde como recurso no encontrado.

En el historial (`resources/js/Pages/Ventas/Index.jsx`) se conserva una acción
manual de reintento para ventas pendientes, con confirmación previa. No se
permite volver a enviar desde el servicio una venta ya aceptada ni un
comprobante marcado como rechazado con el mismo número.

En producción se puede migrar a una cola de Laravel con worker y reintentos.
En desarrollo no es necesario: `QUEUE_CONNECTION=sync` simplifica las pruebas.
El historial conserva la opción de reintento manual para comprobantes pendientes.

### 4. Generación de UBL, firma y envío

`SunatEmisionService` vuelve a cargar los datos guardados en la base de datos;
no usa el carrito del navegador para construir el XML. Para el emisor une los
datos fiscales con la sucursal de la venta. Para el cliente y el detalle lee
las tablas de `catalogos`, `inventario` y `operaciones`.

El servicio realiza, en este orden, las siguientes tareas:

1. **Control de concurrencia:** obtiene un bloqueo de caché por venta para
   evitar emisiones concurrentes del mismo comprobante.
2. **Validación:** admite boleta (`03`) y factura (`01`), valida RUC del emisor,
   datos fiscales, código de establecimiento, requisitos de RUC en factura,
   líneas, cantidades y unidades SUNAT.
3. **Cálculo de líneas:** convierte la cantidad de la presentación a unidad
   base, distribuye el descuento global entre líneas gravadas, calcula base e
   IGV y verifica que las líneas coincidan con los totales guardados.
4. **XML:** crea un `Invoice` UBL 2.1 con identificación y fecha, emisor,
   cliente, modalidad de pago, impuestos, totales monetarios y líneas.
5. **Firma digital:** abre el certificado PKCS#12, firma el XML con XMLDSig
   (RSA-SHA256) y obtiene el digest usado como hash.
6. **Archivos previos al envío:** nombra el comprobante con RUC, código de tipo,
   serie y correlativo rellenado a ocho dígitos. Guarda XML y ZIP en el disco
   `local`, bajo `sunat/xml/`.
7. **SOAP:** invoca `sendBill` con el nombre y contenido del ZIP.
8. **CDR:** espera el ZIP de respuesta, extrae el XML y obtiene el código y la
   descripción de respuesta.
9. **Persistencia:** almacena el CDR en `sunat/cdr/` y actualiza el estado, hash
   y rutas asociadas en la venta.

El prefijo del nombre de archivo es:

```text
{RUC}-{codigo SUNAT}-{serie}-{correlativo de 8 dígitos}
```

El XML se firma antes de guardarlo y enviarlo; la versión serializada que se
transmite es la misma que se conserva. Los archivos se guardan en
`Storage::disk('local')`; no se sirven públicamente mediante una URL en este
flujo.

#### Qué representa cada archivo guardado

- `sunat/xml/{nombre}.xml`: copia del UBL firmado que el servicio intentó
  enviar.
- `sunat/xml/{nombre}.zip`: paquete preparado para `sendBill`.
- `sunat/cdr/R-{nombre}.zip`: respuesta de SUNAT, cuando se recibió y pudo
  guardarse.

La base de datos de la venta conserva el hash y las rutas internas del XML/CDR.
La existencia de un XML o ZIP no significa que SUNAT lo haya aceptado; hay que
consultar el estado y, si existe, el CDR.

### 5. Estados y respuesta al usuario

| Estado | Significado en el flujo actual |
| --- | --- |
| `PENDIENTE` | La emisión no pudo completarse o no se pudo confirmar la respuesta. La venta sigue registrada; se puede revisar/reintentar desde el historial. |
| `ACEPTADO` | El CDR contiene código `0`. Se conserva su XML y el hash de firma. |
| `RECHAZADO` | SUNAT respondió con un código de CDR distinto de `0`. El mismo comprobante no se reenvía desde el servicio. |
| `ERROR` | Estado usado en algunos controles/respuestas del servicio; los errores inesperados durante el intento normalmente quedan como `PENDIENTE`. |

Los errores inesperados se registran en `storage/logs/laravel.log` con el ID de
venta y datos técnicos de excepción. La interfaz muestra un mensaje controlado,
no el detalle completo de las credenciales ni la excepción interna.

Si la venta se registró correctamente pero la petición de emisión falla, el POS
muestra la venta con estado pendiente. El usuario puede buscarla en Historial de
Ventas y reintentar si su estado lo permite. Si se cierra el navegador entre la
respuesta de `/ventas` y la llamada a `/emitir`, la venta queda guardada y el
reintento se realiza desde el historial.

### 6. Representación impresa, QR e historial

`resources/js/Components/SunatReceipt.jsx` es el componente común de la
confirmación del POS y del historial. Solo se muestra como comprobante SUNAT
cuando el estado es `ACEPTADO`.

El componente presenta emisor, cliente, número de comprobante, fecha,
descripción y cantidad de líneas, bases imponibles, IGV, descuentos, total,
total en letras, método de pago, efectivo recibido y vuelto cuando aplican,
hash y pie del ticket.

El QR se crea localmente en el navegador mediante el paquete `qrcode`. Su
contenido se construye con RUC emisor, código de comprobante, serie,
correlativo, IGV, total, fecha, tipo y número de documento del cliente y hash.
El botón de impresión espera a que el QR esté listo.

En el orden exacto que construye el componente, los valores se separan con el
carácter `|`:

```text
RUC emisor | código de comprobante | serie | correlativo de 8 dígitos
| IGV | total | fecha | tipo de documento del cliente
| número de documento del cliente | hash
```

Las líneas del ejemplo solo mejoran la lectura: el payload real se concatena
en una sola cadena, sin saltos de línea ni espacios añadidos.

`resources/css/app.css` define una impresión de 80 mm que oculta el resto de la
interfaz y deja visible el ticket. La opción de guardar como PDF usa el diálogo
de impresión del navegador. **No se genera ni se almacena un PDF en el
servidor**, aunque el modelo de venta tenga un campo `enlace_pdf`.

El historial reconstruye el ticket con la venta, detalles, pagos y datos del
emisor cargados por `VentaController::index`; por tanto, no depende del estado
del carrito después de registrar la operación.

#### Qué ocurre al pulsar imprimir

1. React genera la imagen QR en el navegador. El botón permanece deshabilitado
   hasta que el QR se ha generado.
2. `window.print()` abre el diálogo de impresión del navegador.
3. Las reglas `@media print` ocultan la pantalla POS y dejan visible el
   elemento `.sunat-printable`.
4. El navegador envía el trabajo a la impresora elegida o permite seleccionar
   “Guardar como PDF”.

El sistema no detecta qué impresora está conectada, si tiene papel o si terminó
de imprimir. Tampoco recibe una confirmación de impresión completada.

## Próximas integraciones posibles

Estas son extensiones futuras; ninguna se activa simplemente por tener el
comprobante aceptado. Se recomienda implementarlas después de estabilizar y
probar la emisión SUNAT en el ambiente de destino.

### A. Impresión automática en una impresora térmica

#### Por qué el servidor no imprime directamente en la impresora USB

Laravel se ejecuta en el servidor y la impresora normalmente está conectada a
la computadora de caja. El servidor no puede abrir de forma segura una
impresora USB local del navegador. Además, por seguridad, los navegadores no
permiten que una página web imprima silenciosamente sin permiso o intervención.

#### Alternativas

| Alternativa | Cómo funciona | Ventajas | Consideraciones |
| --- | --- | --- | --- |
| Modo kiosco del navegador | La computadora del POS se configura para imprimir sin mostrar el diálogo del navegador. | Cambio pequeño; aprovecha la página imprimible existente. | Configuración por computadora, limitada al equipo dedicado; requiere pruebas con navegador y driver. |
| Aplicación puente local | Un agente instalado en la computadora recibe un trabajo autenticado desde la aplicación y lo envía a la impresora térmica. | Permite seleccionar impresora, papel y estado del trabajo; puede soportar conexión USB o de red según el agente. | Requiere instalar y actualizar software en cada caja, autenticar solicitudes y definir cómo se recuperan fallos. |
| Impresora de red con servicio local | Un servicio controlado en la red local recibe el comprobante y lo imprime. | Puede centralizar varias impresoras y cajas. | Requiere infraestructura de red, control de acceso, disponibilidad y soporte operativo. |

#### Flujo recomendado

```text
Venta registrada
    ↓
SUNAT acepta (CDR código 0)
    ↓
crear trabajo de impresión asociado a la venta
    ↓
agente de impresión de la caja recibe el trabajo
    ↓
imprime una vez y reporta: completado / fallido
```

La integración debería imprimir solo después de la aceptación de SUNAT, salvo
que el negocio solicite explícitamente un ticket provisional distinto. Cada
trabajo necesita un identificador único para evitar duplicados cuando haya
reintentos. Un fallo de impresora no debe registrar otra venta ni volver a
emitir el comprobante a SUNAT.

Antes de implementarla se debe decidir el modelo y conexión de impresora, si
cada caja tiene impresora propia, cuántas copias se requieren, si se imprimirá
solo tras aceptación, quién puede reimprimir, y cómo se muestran estados como
“sin papel”, “desconectada” o “trabajo pendiente”.

### B. Envío del comprobante por correo

#### Qué falta para enviarlo

Actualmente el cliente tiene datos de identificación y dirección, pero la
emisión no envía correos y no produce un PDF de servidor. El modelo de cliente
tiene un campo para correo, pero el flujo POS actual no lo usa como un paso de
entrega del comprobante; se debe confirmar que esté poblado y vigente o pedir
al usuario que lo confirme durante la venta. Para enviar un correo fiable hace
falta decidir y construir:

1. **Destino:** guardar o confirmar el correo del cliente al vender. Validar
   sintaxis, permitir corregirlo y definir si se puede enviar a un destinatario
   diferente del correo habitual del cliente.
2. **Adjunto:** generar un PDF a partir de los datos persistidos del comprobante
   aceptado; opcionalmente adjuntar XML y/o CDR si el negocio lo requiere. No
   usar una captura de pantalla del navegador como archivo fiscal.
3. **Transporte:** configurar un proveedor SMTP o servicio de correo en Laravel
   mediante variables de entorno; las credenciales no deben viajar al
   navegador ni guardarse en código.
4. **Momento de envío:** crear el envío solo después de tener aceptación SUNAT,
   para no presentar como final un comprobante pendiente o rechazado.
5. **Registro y reintentos:** guardar destinatario, fecha, estado, número de
   intentos y error seguro. Usar una cola para reintentar fallas temporales sin
   repetir la venta ni reenviar a SUNAT.
6. **Experiencia del usuario:** mostrar “enviado”, “pendiente” o “fallido” y
   permitir reintentar o corregir el correo. Evitar envíos duplicados por doble
   clic.

#### Flujo recomendado

```text
SUNAT acepta
    ↓
generar/recuperar PDF a partir de la venta persistida
    ↓
encolar correo con destinatario y comprobante
    ↓
proveedor de correo intenta entrega
    ↓
guardar resultado y permitir reintento controlado
```

“Correo enviado” significa que el proveedor aceptó la solicitud; no garantiza
que el destinatario lo haya leído ni que el mensaje no termine en spam. Si se
necesita confirmar entrega, se debe elegir un proveedor que exponga eventos de
entrega y rebote, e integrar esos eventos.

### C. Cola central del servidor para emisión e integraciones

Hoy la pantalla espera a que termine el request a `/emitir`. Para evitar que
una desconexión del navegador interrumpa el inicio del proceso, una evolución
posible sería:

1. Registrar la venta como ahora.
2. Insertar en una cola un trabajo asociado al ID de venta.
3. Un worker Laravel emite a SUNAT y guarda resultado/CDR.
4. La interfaz consulta o recibe el estado actualizado.
5. Solo después de `ACEPTADO`, encolar tareas independientes de impresión y
   correo.

La emisión necesita controles de idempotencia para que dos workers no creen
resultados duplicados y debe manejar con cuidado los casos donde SUNAT recibió
el ZIP pero el navegador o servidor perdió la respuesta. Los trabajos de correo
e impresión deben tener estados separados del estado fiscal del comprobante:

| Estado fiscal | Estado del correo | Estado de impresión |
| --- | --- | --- |
| `ACEPTADO` | `PENDIENTE` / `ENVIADO` / `FALLIDO` | `PENDIENTE` / `IMPRESO` / `FALLIDO` |

Así, “correo fallido” o “impresión fallida” no cambiarían un comprobante
aceptado por SUNAT.

### D. Orden sugerido para próximas integraciones

1. **Primero:** probar emisión repetible en Beta, revisar CDR, XML y escenarios
   de timeout/reintento. Definir el paso a producción separadamente.
2. **Segundo:** generar un PDF de servidor usando los mismos datos persistidos y
   el diseño aprobado para boleta y factura.
3. **Tercero:** integrar correo con cola, estado de envío y reintento; probar
   direcciones inválidas, rebotes y duplicados.
4. **Cuarto:** pilotear impresión automática en una sola caja y una impresora,
   medir fallos y recién después ampliarlo a las demás sucursales.
5. **Quinto:** evaluar una cola central para emisión SUNAT si se requiere que
   el proceso siga aun cuando el navegador se cierre.

No se deben acoplar correo o impresión al registro transaccional de la venta:
son entregas externas que pueden fallar y deben poder reintentarse por separado.

## Archivos involucrados

### Rutas y controladores

- `routes/web.php`: rutas protegidas de `/ventas`, `/ventas/create`,
  `POST /ventas` y `POST /emitir`; requieren autenticación y permiso de ventas.
- `app/Http/Controllers/Operaciones/VentaController.php`: carga la pantalla
  POS/historial, valida la entrada de venta, llama al servicio de registro y
  devuelve los datos persistidos para el ticket.
- `app/Http/Controllers/Operaciones/SunatController.php`: valida `id_venta`,
  aplica la comprobación de sucursal y llama al servicio de emisión.
- `app/Http/Controllers/Configuracion/DatosGeneralesController.php`: carga y
  persiste datos fiscales de la sucursal; guarda `codigo_establecimiento` en
  `inventario.sucursales`.
- `app/Http/Requests/Configuracion/ActualizarDatosGeneralesRequest.php`:
  valida RUC y formato de cuatro dígitos del código de establecimiento.

### Servicios y modelos

- `app/Services/Ventas/RegistrarVentaService.php`: transacción que registra
  venta, pagos, líneas y movimientos de inventario.
- `app/Services/Comprobantes/ReservarCorrelativoService.php`: bloquea la
  configuración de la sucursal y reserva serie/correlativo para boleta o
  factura.
- `app/Services/Ventas/SunatEmisionService.php`: validación fiscal, UBL,
  firma digital, ZIP, SOAP, lectura del CDR, persistencia de estado y log.
- `app/Services/Inventario/GestionarExistenciaService.php`: descuento de stock
  que se invoca desde el registro de ventas.
- `app/Models/Venta.php`: cabecera y relaciones del comprobante.
- `app/Models/DetalleVenta.php`: líneas de venta.
- `app/Models/PagoVenta.php`: pagos, monto recibido y vuelto.
- `app/Models/DatosGen.php`: datos fiscales, IGV, series y numeración por
  sucursal.
- `app/Models/Sucursal.php`: sucursal y código SUNAT de establecimiento.
- `app/Models/Cliente.php`, `app/Models/TipoComprobante.php` y los modelos de
  catálogo relacionados: datos del receptor, tipo y unidad de productos.

### Interfaz y estilos

- `resources/js/Pages/Ventas/Create.jsx`: captura venta/pago, registra la
  operación, dispara automáticamente `/emitir` y presenta el resultado.
- `resources/js/Pages/Ventas/Index.jsx`: historial, detalle persistido,
  reintento manual para estados que lo permiten y reimpresión.
- `resources/js/Components/SunatReceipt.jsx`: ticket compartido, QR y botón de
  impresión.
- `resources/css/app.css`: reglas de impresión a 80 mm.
- `resources/js/Pages/Configuracion/DatosGenerales.jsx`: configuración
  administrativa de los datos del emisor, series, tasa IGV y establecimiento.

### Base de datos y dependencias

- `database/migrations/2026_10_30_170000_agregar_codigo_establecimiento_a_sucursales_table.php`:
  agrega `inventario.sucursales.codigo_establecimiento`.
- `database/migrations/2026_10_30_230000_agregar_monto_recibido_table.php`:
  agrega `operaciones.pagos_ventas.monto_recibido` y `vuelto`.
- `composer.json` / `composer.lock`: `robrichards/xmlseclibs` para la firma XML.
- `package.json` / `package-lock.json`: `qrcode` para la imagen QR generada en
  el navegador.
- `tests/Unit/SunatEmisionServiceTest.php`: verifica el código de
  establecimiento en el XML, la precisión del valor unitario y el orden de
  secciones de pago/impuestos/totales/líneas.

## Configuración operativa

El servicio lee estas variables del entorno Laravel:

```dotenv
SUNAT_USUARIO_SOL=
SUNAT_CLAVE_SOL=
SUNAT_ENDPOINT_CPE=
SUNAT_CERTIFICATE_PATH=
SUNAT_CERTIFICATE_PASSWORD=
```

La URL predeterminada en el servicio es el endpoint de **Beta**. Para cambiar
de ambiente se debe configurar el endpoint autorizado correspondiente en el
entorno, validar los datos SOL y usar el certificado correcto. La ruta del
certificado puede ser absoluta o relativa a `storage/app`. Nunca pegar valores
reales de estas variables en documentación, logs compartidos o Git.

Requisitos de ejecución usados por el servicio: PHP 8.2 o superior, extensiones
PHP `soap`, `openssl`, `dom` y `zip`, acceso de red al endpoint SOAP y permisos
de escritura en el disco local de Laravel. La base de datos debe tener aplicadas
las migraciones de establecimiento y campos de efectivo/vuelto.

## Verificación y límites actuales

Pruebas locales:

```powershell
php artisan test
npm run build
```

Las pruebas unitarias actuales comprueban partes aisladas de la construcción
UBL; no realizan una llamada real a SUNAT ni sustituyen la validación del CDR de
un envío real. Para declarar una emisión aceptada, el flujo requiere un CDR con
código `0`.

Aspectos que deben considerarse antes de pasar a producción:

- La configuración predeterminada apunta a SUNAT Beta; no asumir ambiente de
  producción por el solo hecho de que una emisión sea aceptada.
- La representación del ticket se imprime en cliente; no existe generación
  PDF del lado servidor ni descarga de XML/CDR desde una pantalla.
- El XML declara la forma de pago como `Contado` en `appendFormaPago`; los
  métodos de pago y el efectivo recibido se guardan y muestran en el ticket,
  pero no se incorporan como medio específico en ese bloque XML.
- El QR refleja los datos construidos por `SunatReceipt`; su presencia visual
  no reemplaza la comprobación de que SUNAT haya aceptado el comprobante.
- Confirmar cumplimiento integral de UBL y reglas de negocio con pruebas
  autorizadas del ambiente destino y documentación vigente de SUNAT. Las
  pruebas unitarias de este repositorio no certifican ese cumplimiento.

## Diagnóstico rápido

| Síntoma | Revisar |
| --- | --- |
| No se guarda la venta | Turno abierto, permiso, cliente/tipo, precio activo, stock, método de pago y migraciones de pagos. |
| Estado pendiente tras guardar | Mensaje mostrado en el modal, `storage/logs/laravel.log`, conectividad SOAP, configuración SOL, certificado y endpoint. La venta permanece guardada. |
| Rechazo de SUNAT | Mensaje del CDR y XML almacenado. Corregir el dato origen antes de emitir un nuevo comprobante; el servicio bloquea reenviar un registro ya marcado como rechazado con el mismo número. |
| Falta código de local anexo | Configurar cuatro dígitos en Datos Generales para la sucursal que emitió la venta. |
| No aparece ticket o QR | Confirmar estado `ACEPTADO`, hash guardado, datos de emisor disponibles y que el navegador haya terminado de generar el QR. |
| Impresión cortada | Configurar papel de 80 mm y revisar escala/márgenes del diálogo del navegador; la vista imprimible usa ancho de contenido de 74 mm. |
