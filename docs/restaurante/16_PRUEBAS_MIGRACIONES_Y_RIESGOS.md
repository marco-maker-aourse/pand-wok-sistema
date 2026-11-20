# 16 · Pruebas, migración y riesgos de despliegue

## Pruebas

Se ejecutan sobre SQLite en memoria (`phpunit.xml`); nunca tocan la base de `.env` ni SUNAT
(`Bus::fake()` en los cobros).

| Archivo | Cubre |
|---|---|
| `MenusYCombinacionesTest` | Sopa incluida sin recargo y registrada en el detalle; obligatoria y opcional por plato; desactivada en sucursal; agotada; 2 menús con sopas distintas en cocina y comprobante; edición antes de enviar y bloqueo después; receta del menú + sopa en un solo consumo; acompañamiento incluido, con recargo, por sucursal, deshabilitado; total con cantidad; precio y opciones ajenas forjados; límites por unidad. |
| `InventarioRestauranteTest` | Variante física 1 L frente a 500 ml (y su stock en el POS); filtros por caja y consumo por taza y jarra; reenvío sin doble consumo; anulación con devolución; merma idempotente y fraccionaria; stock por sucursal. |
| `PosOpcionesTest` | Carta del POS por sucursal (opciones desactivadas, agotadas, recargos, reglas); menú sin sopas disponibles fuera de venta; envío HTTP que persiste elecciones, ignora precios y no duplica; opción desactivada rechazada; cobro con una sola línea por menú; sugerencias con historial real. |
| `CartaOpcionesAdministracionTest` | `restaurante:sopas-menu` reutiliza sopas sin duplicar y es repetible; vínculo de opciones, ajuste por sucursal, reglas por plato y variantes por HTTP. |

Resultado al cerrar esta etapa: **201 pruebas PHP en verde** (174 previas + 27 nuevas, sin
regresiones), pruebas JS en verde y `npm run build` correcto.

```powershell
php artisan test
```

```powershell
npm run build
```

## Migración `2026_11_24_000100_estructurar_opciones_y_variantes`

Única migración nueva. Solo agrega:

- `opciones_modificador.id_precio` (FK, nula) y `nombre` pasa a nulo con `CHECK` nombre o producto;
- `restaurante.opciones_sucursal` (PK sucursal + opción);
- `producto_grupo_modificador.seleccion_minima/maxima` (nulas);
- `pedido_detalle_modificadores.cantidad` (se completa con la cantidad del ítem) e `id_precio`;
- `precio_producto.id_producto_stock` (FK, nula, distinta del propio producto).

Es compatible con los datos existentes y reversible: `down()` se niega a borrar si ya hay
opciones sin nombre, variantes u opciones configuradas por sucursal.

Se probó en un PostgreSQL 18 temporal: todas las migraciones desde cero, `up` → `down` → `up`
de esta, y el flujo completo (menú con sopas repartidas, sopa desactivada rechazada, envío a
cocina, carta del POS y sugerencias). No hay base de desarrollo: se aplica directamente en
producción, siempre con respaldo previo. En producción se aplica con el flujo manual de
`10_DESPLIEGUE_AUTOMATICO.md` ("Run workflow" + respaldo confirmado); el despliegue automático
se detiene solo si detecta migraciones nuevas.

## Riesgos y cómo mitigarlos

| Riesgo | Mitigación |
|---|---|
| El código nuevo llega al servidor sin la migración | El POS consulta `opciones_sucursal` y `id_producto_stock`: desplegar código y migración juntos (el workflow ya bloquea publicar con migraciones pendientes). |
| Carta real sin sopas vinculadas | Hasta ejecutar `restaurante:sopas-menu`, los menús se venden como antes (sin grupo). |
| Variantes mal configuradas | Solo se aceptan insumos activos como existencia; revisar Carta → Existencias antes de vender. |
| Recetas incompletas | Un plato sin receta no descuenta insumos (igual que antes); revisar Recetas por sucursal. |
| Cuentas abiertas durante el despliegue | Sus modificadores reciben `cantidad = cantidad del ítem`: el comportamiento es idéntico al anterior. |
| Clientes con la versión anterior del POS en caché | El backend sigue aceptando `opciones` (lista de ids) además de `selecciones`. |
