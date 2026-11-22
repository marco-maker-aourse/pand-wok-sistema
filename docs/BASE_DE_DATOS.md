# Base de datos

Modelo de datos del sistema para **una empresa con varias sucursales** de restaurante. Está en tercera forma normal (3FN), con las excepciones que se indican al final.

- 49 tablas de negocio en 6 esquemas, más 3 tablas internas de Laravel (`sessions`, `cache`, `cache_locks`) en `public`.
- Las migraciones están en `database/migrations` (6 archivos, uno por grupo de esquemas). Los datos iniciales (empresa, sucursal, series, permisos, roles y administrador) los crea `DatabaseSeeder`.
- Instalación desde cero: `php artisan migrate:fresh --seed`, y luego `php artisan restaurante:importar-carta database/data/carta/pand-wok.json`.

## Esquemas

| Esquema | Tablas | Contenido |
| --- | --- | --- |
| `configuracion` | 3 | `empresa` (una sola fila), `sucursales`, `series_sucursal` |
| `seguridad` | 6 | `usuarios`, `usuario_sucursales`, `roles`, `permisos`, `rol_permisos`, `codigos_vinculacion_agente` |
| `catalogos` | 13 | `productos`, `producto_presentaciones`, `categorias`, `marcas`, `presentaciones`, `unidades_medida`, `clientes`, `proveedores`, `tipos_documento`, `tipos_comprobante`, `metodos_pago`, `impresoras`, `terminales_pos` |
| `restaurante` | 19 | salón (`areas`, `mesas`), carta (`categorias_carta`, `precios_sucursal`, `grupos_modificadores`, `opciones_modificador`, `opciones_sucursal`, `producto_grupo_modificador`, `productos_agotados`), cocina (`estaciones`, `estacion_categorias`, `comandas`), pedidos (`pedidos`, `pedido_detalles`, `pedido_detalle_modificadores`, `pedido_eventos`), recetas y mermas (`receta_insumos`, `consumos_insumos`, `mermas`) |
| `inventario` | 1 | `kardex` |
| `operaciones` | 8 | `turnos_caja`, `movimientos_caja`, `ingresos`, `detalle_ingresos`, `ventas`, `detalle_ventas`, `pagos_ventas`, `trabajos_impresion` |

## Decisiones principales

### Una sola empresa
`configuracion.empresa` guarda RUC, razón social, IGV, logo, colores y textos del login. Un índice único sobre una constante (`empresa_fila_unica`) impide que exista una segunda fila. Todas las sucursales emiten con ese RUC.

Lo propio de cada sucursal está en sus tablas: nombre, dirección, código de establecimiento SUNAT y carta pública en `sucursales`; series y correlativos en `series_sucursal`, que es la **única** fuente de numeración.

Si más adelante el sistema se ofrece como SaaS para varias empresas, esta tabla es el punto de partida: habría que agregar `id_empresa` a `sucursales` y a los catálogos.

### Productos, marcas y presentaciones
- `productos`: el artículo (Inca Kola, Lomo saltado, Papa amarilla), con su `marca`, `categoria`, unidad base y `tipo_producto`. Los tipos son `PREPARADO` (pasa por cocina), `DIRECTO` (se vende tal cual, con stock) e `INSUMO` (solo inventario y recetas).
- `presentaciones`: los tamaños o empaques ("500 ml", "1 L", "Personal", "Familiar").
- `producto_presentaciones`: cada variante vendible (producto × presentación) con su precio general. Su clave sigue llamándose `id_precio` porque la usan pedidos, ventas, recetas y el agente de impresión.
  - `factor_conversion`: cuántas unidades base contiene (caja ×12 → 12).
  - `id_producto_stock`: cuando un tamaño tiene stock propio (una botella de 1 L no se convierte en botellas de 500 ml), apunta al producto cuyo inventario se descuenta.
- `restaurante.precios_sucursal`: precio y disponibilidad de una variante en una sucursal. Si no hay fila, rige el precio general.

### Inventario
- El saldo de cada producto por sucursal es el `stock_actual` del último movimiento en `inventario.kardex`, con su costo promedio (`precio_costo`).
- Entradas: compras (`operaciones.ingresos`). Salidas: ventas, consumo de recetas al enviar a cocina, mermas y ajustes por conteo físico.
- Las **mermas** (`restaurante.mermas`) dan de baja insumos y productos de venta directa (rotura, vencido, consumo interno). Reemplazan al antiguo módulo de salidas.
- No hay control por lotes ni vencimientos: un restaurante rota sus insumos en pocos días, y lo que se pierde se registra como merma.

### Seguridad
`usuarios.id_sucursal` es la sede principal; `usuario_sucursales` contiene solo las sucursales **adicionales**, así que no repite la principal.

## Excepciones a la 3FN (intencionales)

| Dato | Por qué se guarda |
| --- | --- |
| Totales de `ventas` (gravadas, IGV, total) | Son la foto fiscal enviada a SUNAT y no deben cambiar. |
| `pedido_detalles.nombre_producto`, `tipo_producto`, precios; `pedido_detalle_modificadores.nombre_*` | Copias congeladas al tomar el pedido: la comanda, la precuenta y el historial no cambian si se edita la carta. |
| `detalle_ingresos.factor_conversion` | El factor vigente al comprar; la presentación puede cambiar después. |
| `kardex.stock_actual` y `precio_costo` | Saldo y costo promedio acumulados: el saldo se lee en una sola fila y queda la valorización histórica. |

Las columnas que se derivaban de otras se eliminaron: `pagos_ventas.monto_neto` se calcula como `monto − comision_pasarela`, y `porcentaje_utilidad` se calcula a partir del costo y el precio de venta.

## Qué cambió respecto al modelo anterior

El sistema nació como un POS de comercio. Se pasó de 57 a 52 tablas (49 de negocio + 3 de Laravel):

- **Eliminadas:** `inventario.lotes`, `operaciones.salidas`, `operaciones.detalle_salidas`, `inventario.ubigeo`.
- **Reemplazada:** `operaciones.datos_gen` (una fila por sucursal, con series duplicadas) → `configuracion.empresa` (una sola fila, sin series).
- **Renombrada/movida:** `inventario.precio_producto` → `catalogos.producto_presentaciones`; `inventario.sucursales` y `operaciones.series_sucursal` → esquema `configuracion`.
- **Columnas quitadas:** `id_ubigeo` (usuarios, clientes, proveedores, sucursales), `id_lote` (detalles de ingreso y venta), `productos.usa_vencimiento`, `consumos_insumos.lotes` (JSON), `kardex.porcentaje_utilidad`, `precio_producto.porcentaje_utilidad`, `pagos_ventas.monto_neto`, correlativos internos sin uso de `datos_gen`.
- **Configuración:** el `search_path` de PostgreSQL ahora es `public` + todos los esquemas del sistema. Así las tablas de Laravel quedan en `public` y `migrate:fresh` limpia todos los esquemas (antes solo limpiaba `catalogos`).

Los documentos de `docs/restaurante/` describen etapas anteriores y pueden mencionar tablas que ya no existen.
