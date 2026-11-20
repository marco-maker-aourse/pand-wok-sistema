# 14 · Menús, combinaciones y configuración por sucursal

## Modelo de datos (3FN)

```
catalogos.productos ──< inventario.precio_producto (presentación, precio general)
        │                        │
        │                        ├──< restaurante.precios_sucursal   (precio propio / no se vende)
        │                        └──< restaurante.receta_insumos      (por sucursal)
        │
        └──< restaurante.producto_grupo_modificador >── restaurante.grupos_modificadores
                (orden, seleccion_minima/maxima del plato)        │
                                                                   └──< restaurante.opciones_modificador
                                                                          (nombre? | id_precio?, recargo general)
                                                                                 └──< restaurante.opciones_sucursal
                                                                                        (recargo propio, disponible)

restaurante.pedido_detalles ──< restaurante.pedido_detalle_modificadores
   (precio_base, precio_modificadores,      (id_opcion, id_precio, nombre_grupo, nombre_opcion,
    precio_unitario, subtotal congelados)    precio_adicional y cantidad congelados)
```

- **Identidad global, venta por sucursal**: el producto y sus presentaciones son únicos; el
  precio, la disponibilidad, los recargos, las recetas y los agotados son por sucursal.
- **Sin nombres duplicados**: una opción vinculada a un producto (`id_precio`) no guarda nombre;
  se muestra el del producto. `CHECK (nombre IS NOT NULL OR id_precio IS NOT NULL)` y únicos
  `(id_grupo, nombre)` y `(id_grupo, id_precio)`.
- **Sin listas en texto**: cada sopa es una fila de `opciones_modificador`, reutilizada por todos
  los menús que llevan el grupo; ninguna sopa se repite por menú.
- **Instantáneas**: el ítem guarda precio base, recargo por unidad y subtotal; cada opción elegida
  guarda grupo, nombre, recargo y cantidad. Cambiar la carta no altera cuentas abiertas.

## Menú con sopa incluida

Ejemplo: "Menú arroz chaufa" con el grupo **Sopa del menú** (mínimo 1, máximo 1). Las sopas
(pollo, kion, wantán, patita) son opciones vinculadas a sus productos, con recargo 0.

| Regla | Dónde se aplica |
|---|---|
| La sopa no se cobra aparte | Recargo 0: el ítem cuesta lo que el menú; no se crea otra línea ni otra línea de venta. |
| Obligatoria o no | `grupos_modificadores.seleccion_minima`, sobreescribible por plato en `producto_grupo_modificador`. |
| Activación por sucursal | `opciones_sucursal.disponible = false` la oculta y la rechaza solo en esa sucursal. |
| Agotada | `productos_agotados` del producto de la sopa: no se ofrece ni se acepta. Si un grupo obligatorio se queda sin alternativas, el plato sale como agotado en el POS. |
| Varias unidades | Cada selección lleva cantidad: 2 menús → 1 sopa wantán + 1 de pollo. Por grupo, la suma debe estar entre mínimo × cantidad y máximo × cantidad; una opción no supera la cantidad del ítem. |
| Editable antes de enviar | `PUT /pedidos/items/{id}` con `selecciones`; revalida y recotiza. Tras enviar a cocina se rechaza (hay que anular). |
| Cocina y tickets | `PedidoDetalleModificador::etiqueta()`: "Sopa wantán" si va en todas las unidades, "1 × Sopa wantán" si se repartió. |
| Receta | Al enviar, la receta de cada sopa elegida × su cantidad se suma a la del menú (ver `15_…`). |

Las sopas iniciales se configuran con `php artisan restaurante:sopas-menu [--menus=Menu]`: busca
cada sopa entre los productos existentes (sin distinguir mayúsculas ni tildes), solo crea las
que falten (sin precio y sin venderse solas) y no duplica opciones al repetirse.

## Combinaciones con acompañamientos

Un grupo como **Acompañamientos** (mínimo 0, máximo 2) se reutiliza entre combinaciones, menús o
cualquier plato, y sirve igual para salsas, carnes o bebidas.

| Estado de un acompañamiento | Configuración |
|---|---|
| Incluido | Recargo 0 (el POS lo marca "Incluido" cuando el grupo tiene opciones con recargo). |
| Con recargo | Recargo general en la opción; recargo propio por sucursal en `opciones_sucursal.precio_adicional` (p. ej. +3 en A, +4 en B; son ejemplos). |
| Opcional / obligatorio | Mínimo del grupo o del plato. |
| No disponible | `opciones_sucursal.disponible = false` o producto agotado. |

Total del ítem: `precio_unitario = precio_base_sucursal + Σ(recargo × veces) ÷ cantidad`;
`subtotal = round(cantidad × precio_unitario, 2)`. Ej.: 2 combinaciones de S/ 18 con un wantán
frito de +S/ 3 = S/ 39.

Restricción: una opción vinculada a un producto solo se admite en platos **preparados**, porque
su receta o stock se descuenta al enviar a cocina.

## Validación en el servidor (multisucursal estricto)

`ArmarItemPedidoService` + `OpcionesSucursalService` son la única autoridad. El navegador envía
solo `id_precio`, `cantidad`, `observacion` y `selecciones [{id_opcion, cantidad}]` (o `opciones`,
formato anterior); los Form Requests descartan cualquier precio. Se rechaza:

- una opción de un grupo que no pertenece al plato;
- una opción desactivada en la sucursal, inactiva o cuyo producto está inactivo o agotado allí;
- una presentación que no se vende en la sucursal o un producto agotado;
- cantidades fuera de las reglas del grupo.

La sucursal sale siempre del usuario autenticado (`idSucursalActiva()`), nunca de la petición.

## Administración (Carta)

- **Opciones y acompañamientos**: crear grupos; agregar opciones simples (nombre) o vinculadas a
  un producto de la carta; recargo general.
- **Ajuste por sucursal** en cada opción: recargo propio (vacío = general) y casilla "Aquí".
- **Opciones del plato**: grupos en orden y, por grupo, mínimo y máximo solo para ese plato.
- **Existencias** (productos que se venden tal cual): de qué existencia sale cada presentación.

Rutas nuevas (permiso `carta.gestionar`): `PUT /carta/opciones/{id}/sucursal`,
`PUT /carta/precios/{id}/variante`; `PUT /carta/productos/{id}/modificadores` acepta `reglas`.

## POS

- Un toque agrega lo simple; el modal se abre solo si hay grupos o varias presentaciones.
- Presentaciones con precio y existencias de la sucursal (sin stock = deshabilitada).
- Con cantidad > 1 cada opción tiene contador para repartir; el botón muestra el total.
- ± en el ticket escala las opciones si iban en todas las unidades; si estaban repartidas pide
  volver a elegir el plato.
- Sugerencias (`SugerenciasPosService`), solo con pedidos reales de la sucursal: **Frecuentes**
  (unidades de 30 días), **Mis últimos** (del usuario) y **Suele pedirse con** (misma cuenta,
  60 días). Sin historial no se muestran.

## Fiscal

No hay una segunda lógica de facturación: el cobro sigue en `CobrarPedidoService` →
`RegistrarVentaService` → `SunatEmisionService` con sus correlativos. La descripción del ítem
(`DescripcionItems`) incluye las opciones con su reparto: "Menú arroz chaufa (1 × Sopa de pollo,
1 × Sopa wantán)". Las pruebas usan `Bus::fake()`: no se envía nada a SUNAT.
