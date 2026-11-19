# Etapa 4 (cierre) — Pantallas, precios por sucursal y carta digital

## Pantallas (menú **Restaurante**)

| Ruta | Pantalla | Permiso |
| --- | --- | --- |
| `/salon` | Plano del salón por área: mesas libres/ocupadas/reservadas, consumo y tiempo de cada cuenta, pedidos para llevar y delivery. Se refresca cada 15 s mientras la pestaña está visible. | `mesas.ver` |
| `/salon/configuracion` | Áreas y mesas de la sucursal (crear, editar, desactivar) | `mesas.gestionar` |
| `/pedidos/{id}` | Toma de pedido táctil: carta por categorías y búsqueda por nombre o número del plato, opciones del plato, borrador local que se guarda de una vez, editar o quitar ítems, anular, transferir/unir/separar, historial | `pedidos.registrar` (+ `pedidos.transferir`, `pedidos.anular`) |
| `/carta` | Tipo de producto, **precio de la sucursal**, "no se vende aquí", agotado del día y grupos de modificadores | `carta.gestionar` |

Decisiones de interfaz:
- La toma de pedido usa un **borrador local**: tocar un plato no envía nada; "Guardar" manda todas las líneas juntas, cada una con su clave de idempotencia. Si hay líneas sin guardar, la página avisa antes de salir.
- Los precios que se ven en el borrador son estimados; el servidor recalcula al guardar.
- Botones de 40 px o más en acciones de la cuenta, para uso en tablet.
- En pantallas angostas, la carta y la cuenta se alternan con pestañas.

## Precios por sucursal (sede = sucursal)

`restaurante.precios_sucursal` guarda, por sucursal y presentación, un precio propio o "no se vende aquí". Sin fila rige el precio general. La regla vive en `PrecioSucursalService` y la usan la toma de pedidos, la pantalla Carta y la carta pública. El catálogo de productos sigue siendo uno solo para toda la empresa.

## Importar una carta

```powershell
php artisan restaurante:importar-carta database/data/carta/pand-wok.json --simular
php artisan restaurante:importar-carta database/data/carta/pand-wok.json
```

- Cada sede del archivo se asocia a la sucursal con el mismo **identificador de carta** (`slug`, editable en Configuración → Sucursales). También se puede asociar a mano: `--sede=chilca=1 --sede=tambo=2`.
- Una sede sin sucursal asociada se informa y sus precios no se importan.
- Es repetible: actualiza por `codigo_externo` (id del plato en el archivo), no duplica.
- Categorías "Bebidas…" quedan como venta directa; el resto, como preparado.
- `database/data/carta/pand-wok.json` se extrajo de la carta publicada en pand-wok.vercel.app: 3 sedes, 14 categorías, 176 platos. Se extrajo leyendo los datos del archivo publicado, sin ejecutar su código.

## Carta pública (para la web de la carta)

`GET /api/carta-publica` y `GET /api/carta-publica/{slug}`: sin sesión, solo lectura, caché de 60 s y CORS abierto. Devuelve **el mismo formato que usa hoy la web** (`sedes`, `categorias`, `carta[].platos[].precios[slug]`), más `agotadoEn` por plato. Solo incluye sucursales activas con `slug`, productos activos que no son insumos y platos que se venden en esa sede.

Para conectar la web, basta con reemplazar las constantes de datos por una llamada (cambio en el repositorio de la web, que no está en este proyecto):

```js
const respuesta = await fetch('https://<dominio-del-sistema>/api/carta-publica');
const { sedes, categorias, carta } = await respuesta.json();
// Mismas estructuras que las constantes actuales; `agotadoEn` es opcional.
```

## Verificación

- 132 pruebas en verde: 89 heredadas y 43 del restaurante. Entre las del restaurante:
  - Importar la carta real de 176 platos y que la API pública devuelva exactamente los mismos precios por sede.
  - El flujo HTTP de punta a punta: abrir mesa → agregar ítems con precio de sucursal → ver la cuenta y el salón.
  - Un mozo sin permisos recibe 403, y una cuenta de otra sucursal da 404.
- `npm run build` compila y las pruebas JS pasan.
- **No verificado en navegador:** el sistema necesita PostgreSQL y aquí no hay una base disponible. Revisar en el host tras migrar.

## Pendientes conocidos

- Un mismo plato se cobra con un solo precio por presentación y sucursal; no hay precios por horario ni promociones (Etapa posterior).
- Separar cuentas mueve líneas completas; no divide la cantidad de una línea.
- El envío a cocina, la pantalla de cocina y la impresión de comandas son la Etapa 5.
