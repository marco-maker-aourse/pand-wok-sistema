# Etapa 4 — Migraciones (salón, carta y pedidos)

Estado: **escritas, sin ejecutar**. Se validaron con `php -l` y la suite existente sigue en verde (89 pruebas). El SQL no se ha ejecutado contra PostgreSQL; la primera ejecución será en el host.

| Migración | Crea / modifica |
| --- | --- |
| `2026_11_20_000100_crear_salon_restaurante` | Esquema `restaurante`; `areas`, `mesas` (estado con CHECK, código único por sucursal, FK compuesta área-sucursal) |
| `2026_11_20_000200_crear_carta_restaurante` | `catalogos.productos.tipo_producto` (`PREPARADO`/`DIRECTO`/`INSUMO`, por defecto `DIRECTO`); `grupos_modificadores`, `opciones_modificador`, `producto_grupo_modificador`, `productos_agotados` (por sucursal) |
| `2026_11_20_000300_crear_pedidos_restaurante` | `pedidos` (una cuenta ABIERTA por mesa, idempotencia, número por sucursal, unión de cuentas), `pedido_detalles` (precios congelados, `cantidad_cobrada`, anulación auditada, idempotencia por línea), `pedido_detalle_modificadores`, `pedido_eventos` (historial) |
| `2026_11_20_000400_registrar_permisos_restaurante` | Permisos `mesas.ver`, `mesas.gestionar`, `pedidos.registrar`, `pedidos.transferir`, `pedidos.anular`, `carta.gestionar`; los asigna al rol `Administrador` si existe |

## Decisiones

- Solo se **añaden** columnas a tablas existentes (`tipo_producto`); ninguna se modifica ni se elimina. Los productos existentes quedan como `DIRECTO`, que equivale a su comportamiento actual.
- `pedido_detalles.id_comanda` queda sin FK hasta que la Etapa 5 cree `restaurante.comandas`.
- Las migraciones son solo para PostgreSQL (como las existentes: esquemas, CHECK, índices parciales y JSONB).

## Antes de ejecutar en el host

1. Confirmar que el `.env` del host apunta a la base **del restaurante**, nunca a la del sistema comercial:
   `php artisan config:show database.connections.pgsql` (host, database, username).
2. Respaldo de la base si ya tiene datos.
3. `php artisan migrate --pretend` para revisar el SQL y luego `php artisan migrate`.

## Permisos en instalación desde cero

`DatabaseSeeder` incluye los 6 permisos (el rol `Administrador` nuevo los recibe todos) y un rol **Mozo** con `mesas.ver`, `pedidos.registrar`, `productos.ver`, `clientes.ver` y `dashboard.ver`. En una base existente, la migración `000400` los crea y los asigna al rol `Administrador`.

## Modelos y servicios (Etapa 4, segunda parte)

Modelos en `app/Models/Restaurante/` (`Area`, `Mesa`, `Pedido`, `PedidoDetalle`, `PedidoDetalleModificador`, `PedidoEvento`, `GrupoModificador`, `OpcionModificador`) y `Producto` con `tipo_producto` y `gruposModificadores()`.

Servicios en `app/Services/Restaurante/`:

| Servicio | Responsabilidad |
| --- | --- |
| `GestionarPedidoService` | Abrir cuenta (mesa / para llevar / delivery) de forma idempotente, agregar ítems (idempotente por línea), editar ítems pendientes, anular ítem o pedido, total vigente |
| `ArmarItemPedidoService` | Precio calculado en el servidor; valida producto activo, no insumo, no agotado en la sucursal y reglas mín./máx. de modificadores |
| `TransferirPedidoService` | Cambiar de mesa, unir cuentas, mover ítems a otra cuenta (separar) |
| `GestionarSalonService` | Áreas y mesas por sucursal; estados manuales (reservada, fuera de servicio) |
| `GestionarCartaService` | Tipo de producto, agotados por sucursal, grupos de modificadores por plato |
| `BloqueoSalonService` | Bloqueos de fila (`lockForUpdate`) filtrados por sucursal, en orden de id para evitar interbloqueos |
| `RegistrarEventoPedido` | Historial de solo inserción, dentro de la misma transacción |

Reglas clave verificadas con pruebas (`tests/Feature/Restaurante`, 30 pruebas):

- Reintentar abrir o agregar con la misma clave no duplica; una mesa ocupada, reservada por otra cuenta, fuera de servicio o de otra sucursal no recibe cuenta.
- Ítem **pendiente**: se edita o quita libremente. Ítem **enviado a cocina**: no se edita; anularlo exige `pedidos.anular` y motivo. Ítem con **cobro**: no se anula ni se mueve.
- Anular la cuenta exige motivo, anula sus ítems y libera la mesa. Unir cuentas deja el origen como `UNIDO` y libera su mesa.
- Ningún servicio opera pedidos, mesas o áreas de otra sucursal.

Limitaciones conocidas: mover ítems mueve líneas completas (no divide cantidades); el permiso `pedidos.anular` lo verifica el controlador y lo pasa al servicio como booleano. Las pruebas corren en SQLite; las restricciones propias de PostgreSQL (CHECK, FKs entre esquemas) se validarán al migrar en el host.

## Siguiente paso

Controladores, Form Requests, rutas y pantallas React (salón, toma de pedido, carta y modificadores).
