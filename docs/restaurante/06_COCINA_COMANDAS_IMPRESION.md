# Etapa 5 · Cocina, comandas e impresión

Todo el código de esta etapa vive en `C:\laravel\Sistema_restaurante`. La aplicación del restaurante conserva su repositorio, su configuración y su base de datos propios. La integración operativa elegida para cocina es **Star CloudPRNT por HTTPS**. El agente Electron compartido con el sistema comercial queda fuera de esta etapa. Las pruebas opcionales de compatibilidad con Electron buscan el agente independiente del restaurante.

## Uso diario

1. En **Estaciones de cocina**, configura las estaciones de cada sucursal y asigna categorías. Una categoría puede pertenecer a una estación por sucursal. Un plato preparado sin asignación va a la estación activa por defecto o a Cocina general. Un producto de venta directa sin estación queda entregado.
2. En el pedido, usa **Enviar a cocina** para los ítems pendientes o **Guardar y enviar** para registrar el borrador y despachar los pendientes en una sola transacción. La cuenta muestra las comandas, su estado y el último trabajo de impresión.
3. Abre **Cocina** en el menú. Puedes filtrar por estación y cambiar el estado a **En preparación**, **Lista** o **Entregada**. Se muestran las últimas 30 entregas de 12 horas, además de hasta 200 comandas activas. En celular, los enlaces de estado saltan a cada columna. El tablero se refresca cada cinco segundos mientras la página está activa. Toca **Sonido** en cada sesión para habilitar avisos en ese navegador.
4. Los ítems enviados requieren autorización y motivo para anularse. Si quedan ítems vigentes, el tablero identifica los anulados. Si se anula toda la comanda, sale del tablero. Una estación con impresora recibe el aviso de anulación. Al separar una cuenta, el tablero indica el destino de cada ítem movido.

## Permisos y sucursales

| Permiso | Acceso |
| --- | --- |
| `cocina.ver` | Ver el tablero de la sucursal activa |
| `cocina.gestionar` | Cambiar el estado y solicitar una reimpresión |
| `cocina.configurar` | Gestionar estaciones, categorías y sus destinos de impresión |
| `comandas.enviar` | Enviar un pedido a cocina y solicitar una reimpresión desde el pedido |

Enviar y guardar un pedido requiere además `pedidos.registrar`. Las rutas verifican el permiso y la sucursal activa de la sesión. La migración registra los permisos y el rol Cocina para instalaciones existentes, y añade el permiso de envío al rol Mozo si existe. Se conserva el acceso global del Administrador General.

## Configurar Star CloudPRNT

1. Aplica las migraciones de la Etapa 5 en la base exclusiva del restaurante. La base de desarrollo local configurada es `sistema_restaurante_dev`.
2. Registra la impresora en el catálogo del restaurante y comprueba que pertenece a la sucursal correcta. En el servidor del restaurante, genera su credencial con `php artisan restaurante:cloudprnt:clave ID_IMPRESORA`. Solo se almacena el hash. La consola muestra la contraseña una vez. Repetir el comando permite rotarla.
3. Configura CloudPRNT HTTP en la impresora con la URL HTTPS generada (`/api/restaurante/cloudprnt/ID_IMPRESORA`), el usuario y la contraseña que emitió el comando. Usa un firmware con soporte de `jobToken` y `text/plain`. Si TLS termina en un proxy, configura la aplicación y el servidor web para que Laravel reconozca el esquema HTTPS y reciba el encabezado de autenticación Basic.
4. En **Estaciones de cocina**, elige la impresora marcada **CloudPRNT** y deja la terminal en **CloudPRNT directo**. Cada envío imprime una copia automática. La impresora consulta la cola por red y el celular se usa solo para operar el pedido y el tablero.
5. Valida con la impresora del local la descarga, el papel, el corte y la confirmación. El ticket es texto ASCII con cantidades, modificadores y notas, sin precios. Si el trabajo se descargó y faltó su confirmación, pasa a **Por revisar** cuando la impresora vuelve a consultar sin un trabajo en curso. Revisa el papel antes de tocar **Reimprimir**.

[Configuración de clientes Star](https://star-m.jp/products/s_print/sdk/StarCloudPRNT/manual/en/client.html) · [Protocolo HTTP CloudPRNT](https://star-m.jp/products/s_print/sdk/StarCloudPRNT/manual/en/protocol-reference/http-method-reference/index.html)

## Integridad de la cola

- Una clave de envío conserva el resultado original, incluso para pedidos con productos de entrega directa. Los cambios de los ítems y el alta de trabajos se confirman juntos. El envío físico ocurre fuera de la operación del pedido.
- Una comanda se encola con una clave por envío, aviso de anulación o solicitud de reimpresión. En CloudPRNT, cada trabajo permite una sola descarga. La terminal de un agente local y la impresora CloudPRNT tienen colas separadas.
- Las confirmaciones válidas repetidas son idempotentes. Los resultados de impresión se registran sobre la impresora identificada en el trabajo. Una descarga sin confirmación requiere revisión y admite una confirmación tardía. Una nueva impresión es una acción del operador.
- Los cambios de estado de cocina guardan las horas de inicio, lista y entrega, y no modifican líneas anuladas.

## Verificación local

La suite automatizada usa SQLite en memoria y cubre permisos, sucursales, transferencias, transiciones, anulaciones, ruteo, reimpresión y el intercambio HTTP con una impresora simulada. Las migraciones se comprobaron en una instancia PostgreSQL temporal y aislada. Queda pendiente validar un equipo Star físico con el firmware y la red del local.
