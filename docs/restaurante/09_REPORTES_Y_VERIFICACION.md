# Etapas 8 y 9 · Reportes y verificación

**Reportes del restaurante** está disponible desde el menú y el tablero principal. El rango de consulta se limita a 90 días y usa días de Lima. El filtro de sucursal se limita a las sedes autorizadas para el usuario, incluso al elegir todas.

- Ventas diarias y por sucursal usan el total final de cobros enlazados a pedidos.
- Platos y categorías muestran los importes brutos de sus líneas. El descuento global de un comprobante se refleja en los totales de venta.
- El costo registrado usa los consumos congelados y las salidas de productos directos en Kardex. Los costos de receta se prorratean según la cantidad cobrada. El margen usa los ingresos sin IGV menos el costo y queda pendiente si existen platos vendidos sin costo registrado.
- Las salidas y devoluciones de ingredientes se toman del Kardex. La merma de cocina se reporta por la fecha en que se resolvió la anulación. La tabla de turnos muestra el volumen de ventas del período y el cierre declarado disponible.
- El tiempo de preparación usa las marcas de inicio y lista de las comandas. Los pedidos anulados muestran el motivo registrado por el usuario.

Migración para el host: `2026_11_22_000300_permisos_y_reportes_restaurante.php`. Da acceso a reportes, recetas y descuentos a Administración, mermas a Almacén y vista de Salón a Caja según los roles del restaurante. Las rutas verifican el permiso en el servidor.

## Comprobaciones

Las pruebas nuevas operan con tablas creadas en SQLite en memoria, sin ejecutar las migraciones. Cubren cantidades parciales, doble cobro, descuentos con permiso, pagos mixtos, turno y cierre de caja, aislamiento entre sucursales, conversiones de ingredientes, devolución de lote, mermas, falta de stock, costos y reportes. El flujo de emisión se comprueba hasta el despacho del trabajo SUNAT. La verificación en PostgreSQL, con credenciales SUNAT y equipos físicos corresponde al host del restaurante.
