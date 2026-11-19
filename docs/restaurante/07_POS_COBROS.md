# Etapa 6 · POS, cobros y comprobantes

- El cajero abre un turno en **Caja** y entra a una cuenta desde **Salón**. También puede entrar desde **Cobrar cuenta** en el pedido si tiene permiso para operar ventas.
- En **Dividir cuenta**, ajusta las cantidades por cobrar. Cada cobro genera una venta y uno o varios pagos existentes. Al cobrar todos los ítems vigentes se cierra el pedido y se libera la mesa.
- El servidor conserva el precio del pedido, incluidos los modificadores. Cada fracción usa la diferencia entre subtotales acumulados para conservar el total en céntimos. Una clave de cobro identifica el intento y la cantidad ya cobrada evita aplicar una vista desactualizada.
- El permiso `pos.descuentos` habilita descuentos sobre el monto gravado. El comprobante del cobro debe tener un importe positivo. Para facturas, selecciona un cliente activo con RUC registrado. La boleta puede usar el cliente de público general existente.
- La emisión la procesa el servicio SUNAT del POS después de confirmar el cobro. Un error de emisión se consulta en el historial sin volver a cobrar. Para comprobantes aceptados, la cuenta permite descargar el PDF desde el celular. La impresión fiscal utiliza el flujo de caja ya existente. CloudPRNT corresponde a las comandas en esta etapa.
- Las líneas con cobro se conservan para la trazabilidad. Una devolución fiscal o nota de crédito es una operación posterior al cobro y queda fuera de este flujo.

Migración para el host: `2026_11_22_000100_vincular_cobros_a_pedidos.php`. El sistema del restaurante debe usar su propia base y sus propias series, configuración y credenciales de emisión.
