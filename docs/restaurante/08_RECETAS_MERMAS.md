# Etapa 7 · Recetas, consumo y mermas

- Cada receta aplica a un plato, una presentación de venta y una sucursal. Los ingredientes se configuran con una presentación activa y una cantidad. El factor de conversión de esa presentación traduce la cantidad a la unidad base del Kardex.
- En **Recetas**, el stock y el costo promedio vienen del Kardex de la sucursal. El costo estimado de un plato suma los ingredientes configurados. Los modificadores agregan precio en el POS. Su costo requiere incluir el ingrediente en la receta correspondiente.
- Al enviar a cocina se descuenta una sola vez el ingrediente de cada ítem preparado con receta. Si falta stock, el pedido y la comanda quedan como estaban. Los platos sin receta activa siguen disponibles y quedan sin costo registrado. Los productos directos se descuentan en la venta, con el Kardex existente.
- Se conservan la cantidad base, el costo y los lotes efectivamente consumidos. El operador autorizado puede devolver al stock los ingredientes recuperables de una línea enviada cuya preparación aún no comenzó. La devolución usa los mismos lotes y el costo registrado. Si un lote ya no admite devolución, el operador puede registrar la anulación como merma.
- Si se anula cuando la preparación empezó o el operador no confirma la recuperación, el consumo se clasifica como **merma de cocina**. El Kardex conserva la salida original. **Mermas de insumos** registra una salida adicional solo para pérdidas físicas nuevas.

Migración para el host: `2026_11_22_000200_crear_recetas_y_movimientos_restaurante.php`.
