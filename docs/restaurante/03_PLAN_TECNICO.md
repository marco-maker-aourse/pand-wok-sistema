# Etapa 3 — Diseño de entidades y plan técnico

Propuesta basada en `02_AUDITORIA.md`. **Nada de esto está implementado todavía.** Las decisiones marcadas ⚑ esperan tu confirmación.

## Principios

- Reutilizar sin reescribir: `RegistrarVentaService`, `GestionarExistenciaService`, `GestionarTurnoCajaService`, `ReservarCorrelativoService`, SUNAT, cola de impresión y RBAC quedan como base. Se **extienden**, no se duplican.
- Un pedido no es una venta. El pedido (comanda) vive mientras la mesa está abierta; al cobrar se genera una o varias `operaciones.ventas` con el servicio existente.
- Toda operación que cambia dinero o stock: transacción + `lockForUpdate` + clave de idempotencia.
- Sucursal y usuario salen siempre de la sesión; los servicios nuevos filtran `id_sucursal` explícitamente (el scope no actúa en consola).

## ⚑ Esquema nuevo `restaurante`

`ARQUITECTURA.md` limita el modelo a 4 esquemas. Propongo un quinto esquema `restaurante` para el dominio nuevo (mesas, pedidos, cocina, recetas) y solo **columnas** nuevas en tablas existentes. Mantiene separado lo heredado de lo nuevo.

## Entidades nuevas (esquema `restaurante`)

| Tabla | Propósito | Claves e integridad |
| --- | --- | --- |
| `areas` | Salón, terraza, barra… | `id_sucursal`; único `(id_sucursal, nombre)` |
| `mesas` | Código, capacidad, posición en el plano, estado (`DISPONIBLE`, `OCUPADA`, `RESERVADA`, `FUERA_SERVICIO`) | FK compuesta `(id_area, id_sucursal)`; único `(id_sucursal, codigo)` |
| `estaciones_preparacion` | Cocina caliente, fría, bar… con impresora opcional | FK compuesta a `catalogos.impresoras (id_impresora, id_sucursal)` |
| `grupos_modificadores` / `opciones_modificador` | "Término de la carne", "Adicionales" con precio extra, mín/máx | `opciones` con `precio_adicional` y, opcionalmente, `id_producto` insumo |
| `producto_grupo_modificador` | Qué grupos aplica cada plato | único `(id_producto, id_grupo)` |
| `pedidos` | Cabecera: tipo (`MESA`, `PARA_LLEVAR`, `DELIVERY`), mesa, mozo, estado (`ABIERTO`, `CERRADO`, `ANULADO`), `clave_idempotencia` única | Índice parcial único: **una sola cuenta abierta por mesa** |
| `pedido_detalles` | Ítem: `id_precio`, cantidad, precio unitario congelado, observación, estado (`PENDIENTE`, `ENVIADO`, `EN_PREPARACION`, `LISTO`, `ENTREGADO`, `ANULADO`), `cantidad_cobrada` | `cantidad_cobrada <= cantidad` (CHECK) |
| `pedido_detalle_modificadores` | Opciones elegidas con precio congelado | — |
| `comandas` | Cada envío a cocina por estación (número correlativo por sucursal/día) | `clave_idempotencia` única → no hay comandas duplicadas |
| `pedido_eventos` | Historial: creación, envío, cambio de estado, transferencia, anulación (usuario, motivo, antes/después en JSONB) | Solo inserción |
| `recetas` / `receta_insumos` | Insumo (`id_producto` tipo insumo), cantidad, unidad | Conversión vía `factor_conversion` existente |

## Cambios en tablas existentes (solo columnas/constraints nuevas)

- `catalogos.productos`: `tipo_producto` (`PREPARADO`, `DIRECTO`, `INSUMO`), `id_estacion` por defecto, `disponible`.
- `operaciones.ventas`: `id_pedido` (nullable), `clave_idempotencia` (única), `estado` (`EMITIDA`, `ANULADA`).
- `operaciones.detalle_ventas`: `id_pedido_detalle` (nullable) para saber qué se cobró.
- `operaciones.trabajos_impresion`: ampliar CHECK de `tipo` con `COMANDA`, `PRECUENTA`; `id_comanda` nullable.
- `inventario.kardex`: nuevos `tipo_operacion` (`CONSUMO_RECETA`, `REVERSION_RECETA`, `MERMA`) + índice único parcial `(tipo_operacion, id_referencia, id_producto)` para que una misma línea no descuente dos veces.

## Flujos clave

1. **Abrir mesa → pedido.** `AbrirPedidoService` bloquea la mesa, crea el pedido y cambia el estado. Reintento con la misma clave devuelve el mismo pedido.
2. **Enviar a cocina.** `EnviarComandaService` toma los ítems `PENDIENTE`, los agrupa por estación, crea una `comanda` por estación y un `trabajo_impresion` tipo `COMANDA` (con la impresora de la estación) con clave `comanda:{id}`.
3. **KDS.** La pantalla consulta comandas activas de su estación y cambia estados. ⚑ Propuesta: *polling* cada 3–5 s con respuestas ligeras (sin dependencias nuevas). Reverb/WebSockets se puede añadir después si el volumen lo pide.
4. **Transferir / unir / separar.** Servicios que mueven `pedido_detalles` entre pedidos dentro de una transacción con bloqueo de ambos pedidos, registrando el evento.
5. **Cobrar.** `CobrarPedidoService` arma el carrito a partir de los ítems no cobrados (todos o una selección para cuenta dividida) y llama al `RegistrarVentaService` existente, que ya gestiona turno, correlativo, pagos mixtos y comprobante. Idempotencia por `clave_idempotencia` en la venta. Al quedar todo cobrado, el pedido se cierra y la mesa se libera.
6. **Inventario.** ⚑ Momento del descuento de insumos: (a) al **enviar a cocina** — refleja el consumo real; una anulación posterior es una merma o una reversión autorizada; (b) al **cobrar** — más simple, pero el stock va por detrás de la cocina. Recomiendo (a). Los productos `DIRECTO` (bebidas embotelladas) descuentan como hoy.
7. **Anulaciones.** Ítem no enviado: se anula libremente. Ítem enviado: requiere permiso `comandas.anular` + motivo; revierte el consumo según la regla elegida. Venta emitida: queda para nota de crédito (fase posterior).
8. **Descuentos.** Permiso `pos.descuentos`; el `descuento_global` ya existe en el servicio de ventas.

## Permisos nuevos

`mesas.ver`, `mesas.gestionar`, `pedidos.registrar`, `pedidos.transferir`, `comandas.anular`, `cocina.ver`, `cocina.gestionar`, `carta.gestionar`, `recetas.gestionar`, `mermas.registrar`, `pos.descuentos`, `reportes.restaurante`.

## Frontend (React, por módulos)

`Pages/Salon/` (plano de mesas por área), `Pages/Pedidos/` (toma de pedido táctil reutilizando `CatalogoPos`, `LineaCarrito`, `PanelPagos`), `Pages/Cocina/` (KDS a pantalla completa), `Pages/Carta/`, `Pages/Recetas/`. Componentes compartidos en `Components/Restaurante/`. Toda la lógica de importes y estados queda en el backend.

## Agente de impresión

Copia en `C:\laravel\agente-impresion-restaurante` (con `appId` distinto). Cambios: aceptar `tipo: "COMANDA" | "PRECUENTA"` y un renderizador de comanda (mesa, mozo, hora, ítems, modificadores y notas en letra grande, sin precios). La impresora destino ya viaja en `contenido.impresora`, así que un agente puede imprimir en las impresoras de red de cada estación. Se mantiene la protección existente: un trabajo entregado y no confirmado pasa a `REQUIERE_REVISION`, no se reimprime solo.

## Orden de implementación y verificación

| Etapa | Entregable | Verificación |
| --- | --- | --- |
| 4 | Migraciones `restaurante`, áreas, mesas, carta (tipo de producto, disponibilidad, modificadores), pedidos y comandas | Pruebas de servicio: abrir/cerrar mesa, una cuenta abierta por mesa, idempotencia |
| 5 | Estaciones, KDS, trabajos `COMANDA`, agente adaptado | Pruebas: sin comanda duplicada, cambios de estado, aislamiento por sucursal |
| 6 | Cobro de pedidos sobre `RegistrarVentaService`, cuentas divididas, descuentos con permiso | Pruebas: cobro parcial/total, no doble cobro, turno cerrado |
| 7 | Recetas, consumo de insumos, mermas, reversión, costo por plato | Pruebas: descuento único, reversión, conversión de unidades |
| 8 | Permisos, reportes y dashboard con datos reales | Pruebas de acceso por rol y sucursal |
| 9 | Regresión completa, `npm run build`, revisión de rendimiento | 89 pruebas heredadas + nuevas en verde |

## Requisito previo

Base `sistema_restaurante_dev` creada en PostgreSQL local y `DB_PASSWORD` en `.env`. Las pruebas automatizadas corren en SQLite en memoria, pero el esquema `restaurante`, los índices parciales y los CHECK deben validarse además contra PostgreSQL real antes de cerrar cada etapa.
