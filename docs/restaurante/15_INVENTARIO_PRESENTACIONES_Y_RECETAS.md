# 15 · Inventario: presentaciones, variantes y recetas

Complementa `08_RECETAS_MERMAS.md` (recetas, consumo y mermas) con los tres casos de stock.

## Tres tipos de presentación

| Caso | Ejemplo | Cómo se configura | Qué se descuenta |
|---|---|---|---|
| (a) Convertible del mismo inventario | Six pack de agua frente a botella | Presentaciones del mismo producto con `factor_conversion` | `cantidad × factor` del inventario base del producto, al cobrar |
| (b) Variante física con stock propio | Inca Kola 500 ml y 1 L | Producto de carta "Inca Kola" (se vende tal cual) con dos presentaciones; cada una con `id_producto_stock` hacia su insumo ("Inca Kola botella 1 L") | Vender 1 L descuenta una botella de 1 L; la de 500 ml no se toca |
| (c) Consume insumos por receta | Mate de manzanilla: taza = 1 filtro, jarra = 2 | Producto preparado con presentaciones taza y jarra; receta por presentación y sucursal | La receta de la presentación × cantidad, al enviar a cocina |

`id_producto_stock` solo admite un insumo activo con stock y solo en productos que se venden tal
cual (`GestionarCartaService::fijarVariante`). `GestionarExistenciaService::decrease/increase`
usan `ProductoPresentacion::productoInventario()`, así que ventas del POS, salidas y entradas
respetan la variante sin otra lógica.

## Entrada por paquete, consumo por unidad

El insumo "Filtro de manzanilla" tiene unidad base *unidad* y la presentación "Caja x25"
(`factor_conversion = 25`). Una entrada de 2 cajas suma 50 filtros en el kardex; la receta de la
jarra descuenta 2. Las cantidades son decimales (`numeric(14,4)`), p. ej. 0.2 kg de pollo.

## Consumo (backend como autoridad)

`ConsumoRecetaService::descontar`, dentro de la transacción de envío a cocina:

1. Por cada ítem preparado, la receta de su presentación × cantidad.
2. Por cada opción con producto: si es preparado, su receta × veces elegida; si se vende tal cual,
   su propio stock (o el de su variante) × factor × veces.
3. Los aportes al mismo insumo se suman en **un** registro `consumos_insumos` por ítem.

Garantías: única `(id_detalle_pedido, id_producto)` en consumos, índice único parcial del kardex
por origen (`CONSUMO_RECETA`, `REVERSION_RECETA`, `MERMA`) y clave de envío de la comanda. Un
reintento no vuelve a descontar. Stock insuficiente revierte toda la comanda.

## Anulación, mermas y ajustes

- Anular un ítem enviado con "recuperable" devuelve exactamente lo consumido (costo y lotes); si
  ya estaba en preparación queda como merma (`estado = MERMA`).
- Merma manual: `RecetasService::registrarMerma` con clave idempotente y huella de datos.
- Ajustes y salidas: módulo existente de Salidas (kardex con origen `SALIDA`/`AJUSTE`).

## Pantallas (lenguaje de restaurante)

Menú **Inventario**: Recetas e insumos (existencias por sucursal), Entradas, Salidas y ajustes,
Mermas y Movimientos de existencias (kardex valorizado). Todas operan sobre el mismo kardex, con
transacciones y bloqueo del producto (`bloquearProducto`).

Pendiente: alertas de stock mínimo (no existe umbral por insumo) y almacenes dentro de una
sucursal (ver `13_…`).
