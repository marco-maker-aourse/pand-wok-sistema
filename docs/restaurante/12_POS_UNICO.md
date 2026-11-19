# Punto de venta único (`/pos`)

Todo se vende desde una pantalla, como en Odoo POS, Loyverse o Fudo: el tipo de pedido es una opción del ticket, no otra pantalla.

| Tipo | Flujo |
| --- | --- |
| **Mostrador / para llevar** | Elegir platos y bebidas → **Cobrar**. En un paso: crea la cuenta, envía los platos a cocina (comanda, receta), entrega las bebidas (descuenta su stock) y emite boleta o factura. **Guardar** deja la cuenta abierta para cobrar después. |
| **Delivery** | Igual que mostrador; exige dirección de entrega (botón *Datos de entrega*). |
| **Mesa** | Elegir mesa → **Enviar** (rondas a cocina) → **Cobrar** al final, todo junto. *Dividir cuenta* abre el cobro por ítems. Al cobrar todo, la mesa queda libre. |

- **Cuentas abiertas** muestra mesas y pedidos pendientes para retomarlos. El **Salón** abre el POS: mesa ocupada → su cuenta; mesa libre → ticket nuevo de esa mesa.
- Precios: los de la sucursal (`precios_sucursal`); agotados del día deshabilitados.
- Cobro: boleta/factura, cliente (búsqueda por nombre, DNI o RUC), descuento con `pos.descuentos`, pagos mixtos y vuelto. Tras cobrar: **Imprimir ticket** y **Nueva venta**. El comprobante va a SUNAT en segundo plano.
- Seguridad: cada operación lleva una clave; un doble toque o reintento devuelve la misma venta. Si el pago no cuadra con el total del servidor, no queda nada registrado (todo en una transacción).
- Permisos: ver y tomar pedidos `pedidos.registrar`; enviar `comandas.enviar`; cobrar `ventas.registrar` (con caja abierta).

## Cambios relacionados

- `/ventas/create` (POS mayorista) redirige a `/pos`; en el menú, "Ventas" pasa a **Comprobantes** (historial).
- Presentaciones rápidas según el tipo de producto: platos (Porción, Personal, Familiar, Para compartir), bebidas (Unidad, Vaso, Jarra, Balde ×6) e insumos (Paquete, Caja, Saco).
- Nombre por defecto: "Sistema Restaurante" (la barra lateral muestra el nombre comercial de Datos generales).
- SUNAT: credenciales y certificado se leen con `config('services.sunat.*')`; con `config:cache` (despliegue) `env()` devolvía vacío. Una prueba impide volver a usar `env()` fuera de `config/`.

## Código

`app/Services/Restaurante/VentaPosService.php`, `app/Http/Controllers/Restaurante/PosController.php`, `resources/js/Pages/Restaurante/Pos.jsx`, `Components/Restaurante/{DialogoCobroPos,SelectorMesa,ClienteCobro}.jsx`. Pruebas: `tests/Feature/Restaurante/PosRestauranteTest.php`.
