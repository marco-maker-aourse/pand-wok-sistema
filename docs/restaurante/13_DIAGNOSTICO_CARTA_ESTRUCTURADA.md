# 13 · Diagnóstico: carta estructurada, opciones por sucursal y stock real

Auditoría previa a la evolución de noviembre de 2026 (rama `feat/carta-opciones-inventario`).
Las etapas anteriores siguen descritas en `02_AUDITORIA.md` a `12_POS_UNICO.md`; aquí solo
consta lo que se decidió reutilizar, extender o corregir.

## Qué había y qué se hizo con ello

| Área | Estado encontrado | Decisión |
|---|---|---|
| Modificadores (`grupos_modificadores`, `opciones_modificador`, `producto_grupo_modificador`, `pedido_detalle_modificadores`) | Grupos globales con mínimo/máximo; opciones con texto libre y precio único; una opción por unidad implícita | **Extender**: la opción puede ser un producto, recargo y disponibilidad por sucursal, obligatoriedad por plato, cantidad por selección. No se creó un segundo sistema de "grupos de opciones". |
| Precios por sucursal (`precios_sucursal`, `PrecioSucursalService`) | Precio propio o "no se vende aquí" por presentación | **Reutilizar** tal cual; el recargo de opciones sigue la misma regla (`opciones_sucursal`). |
| Agotados (`productos_agotados`) | Por producto y sucursal | **Reutilizar**: una sopa agotada deja de ofrecerse como opción. |
| Recetas (`receta_insumos`, `consumos_insumos`, `mermas`) | Receta por presentación y sucursal; consumo al enviar a cocina; reversión al anular | **Extender**: el consumo suma la receta del plato y la de sus opciones en un único movimiento por insumo. |
| Inventario / kardex (`GestionarExistenciaService`) | Stock por producto y sucursal, conversión por `factor_conversion` | **Extender**: `precio_producto.id_producto_stock` para variantes físicas. |
| POS único (`VentaPosService`, `Pos.jsx`, `DialogoPlato.jsx`) | Un toque, modal de presentación/opciones, idempotencia por clave | **Extender**: selección por unidad, stock por presentación, sugerencias. |
| Cocina, comandas, agente e impresión CloudPRNT | Envío idempotente por estación; el ticket recibe `opciones` como lista de textos | **Conservar**: el formato no cambia; solo el texto indica reparto ("1 × Sopa wantán"). |
| SUNAT (`SunatEmisionService`, `DescripcionItems`) | Descripción del ítem incluye opciones | **Conservar**: misma lógica y correlativos; solo cambia el texto de opciones repartidas. |
| Datos Generales (marca) | `color_primario` aplicado en cada respuesta (`Utils/theme.js`) | **Sin cambios**: ya es la única fuente; no se agregó otra configuración de marca. |
| Skills del repositorio (`skills-lock.json`) | Declarados (laravel-specialist, backend-patterns, frontend-design, impeccable, postgres-pro…) pero no instalados en esta máquina | Se siguieron sus convenciones (controladores delgados, Form Requests, servicios con transacción). |

## Correcciones encontradas en la auditoría

- La base SQLite de pruebas (`PreparaBaseRestaurante`) no tenía Kardex; la pantalla del POS ahora
  consulta existencias, así que la tabla pasó a la base común de pruebas.
- El orden de las opciones en cocina dependía del motor de base de datos; ahora es estable
  (orden del grupo en el plato y orden de la opción).

## Decisiones que quedan para el usuario

1. **Almacenes**: el sistema lleva stock por sucursal; no existe una tabla de almacenes.
   Se trató cada sucursal como su almacén. Si se necesitan varios almacenes por sucursal es
   una migración aparte (afecta kardex, lotes e ingresos).
2. **Aplicar la migración `2026_11_24_000100`** en la base de desarrollo configurada en `.env`
   (`sistema_restaurante_dev` en 127.0.0.1). No se aplicó: falta confirmación.
3. **`php artisan restaurante:sopas-menu --menus=Menu`**: deja el grupo "Sopa del menú" en los
   platos de la categoría Menu. Reutiliza las sopas de la carta importada; ejecutarlo en
   producción requiere autorización.
4. **Alertas de stock mínimo**: no existe un umbral por insumo en el esquema; quedó fuera.
