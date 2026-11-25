# Rediseño de la interfaz — estado

Rama: `feat/rediseno-ui` (creada desde `feat/carta-opciones-inventario`, con sus cambios aún sin commit).

## Punto de partida (auditoría)

El sistema ya tenía una base de diseño sólida, que se conserva:

- Tokens en `resources/css/app.css` (Tailwind 4): el color de marca sale de `color_primario` de Datos Generales (`Utils/theme.js`) y los tonos hover, suave, borde y anillo se derivan con `color-mix`.
- Componentes en `Components/ui` (Button, Dialog, ConfirmDialog, `confirmar()`, Toast, Table, Field, EmptyState, Skeleton, PageHeader…).
- El POS ya trae del backend las opciones de los menús (sopas), los recargos por sucursal y las presentaciones con stock, y valida el importe en el servidor.

Problemas encontrados y ya corregidos:

1. Dos librerías de íconos: el menú usaba `react-icons/fi` y el resto Lucide.
2. Colores fijos (cian, teal, índigo) en el panel de pagos, la apertura de caja y los componentes Breeze de Perfil y Auth.
3. Un `window.confirm` en Pedido, y el POS no avisaba al salir con platos sin guardar.
4. `Pos.jsx` con el ticket y los diálogos dentro del mismo archivo.
5. En el celular no había accesos rápidos: había que abrir el menú lateral para pasar de Salón al POS o a Cocina.
6. Sugerencias rápidas de Categorías y Presentaciones con vocabulario de bodega (abarrotes, sacos).

## Hecho

| Área | Cambio | Archivos |
|---|---|---|
| Íconos | Solo Lucide; se desinstaló `react-icons` | `modules.js`, `package.json` |
| Marca | Panel de pagos, apertura de caja y Breeze usan los tokens de marca | `Ventas/PanelPagos.jsx`, `Caja/DialogoAperturaCaja.jsx`, `Components/Breeze/*` |
| Navegación móvil | Barra inferior con hasta 4 módulos operativos (Salón, Vender, Cocina, Caja…) sacados de los ítems que el rol ya puede ver, más "Más" para el menú completo. El POS la oculta para dar el espacio al pedido | `Layouts/navegacion/BarraInferior.jsx`, `AuthenticatedLayout.jsx`, `AppLayout.jsx` |
| Sucursal | En el celular, el nombre de la sucursal activa aparece bajo el título | `AuthenticatedLayout.jsx` |
| Notificaciones | En el celular los toasts salen arriba, para no tapar la barra inferior | `ui/Toast.jsx` |
| Borradores | Hook `useAvisoBorrador`: diálogo propio al navegar y aviso del navegador al cerrar. Lo usan el POS y Pedido (reemplaza `window.confirm`) | `hooks/useAvisoBorrador.js` |
| POS | Dividido en `TicketPos`, `BarraTipoVenta`, `AvisoRequisitosCobro` y `DialogosPos`. Sin el permiso de cobrar, "A cocina" es la acción principal. Estado vacío con ícono. "Descartar" ahora está dentro del bloque "Sin guardar". Diálogo de venta registrada con el vuelto destacado | `Pages/Restaurante/Pos.jsx`, `Components/Restaurante/pos/*` |
| Carta | Íconos en Frecuentes y Mis últimos, "Quedan N" con el stock real cuando es de 5 o menos, etiqueta "Opciones" | `Restaurante/CartaPedido.jsx` |
| Cocina | Tarjeta de comanda propia: mesa en grande, cantidades destacadas, tiempo con aviso "Demorando" (15 min) y "Demorada" (25 min) con ícono y texto, botones grandes. En el celular, una columna a la vez con pestañas por estado | `Restaurante/cocina/TarjetaComanda.jsx`, `Pages/Restaurante/Cocina.jsx` |
| Salón | Los 4 indicadores grandes pasan a una franja compacta: las mesas se ven antes en el celular | `Pages/Restaurante/Salon.jsx` |
| Lenguaje | Sugerencias de restaurante en Categorías y Presentaciones | `Pages/Categorias`, `Pages/Presentaciones` |

### Caja y comprobantes (incluye notas de venta)

| Área | Cambio | Archivos |
|---|---|---|
| Comprobantes | Reescrito con los componentes `ui`: tabla en escritorio y tarjetas tocables en celular, filtros por tipo y estado SUNAT, estado vacío y detalle con fecha, total y cliente | `Pages/Ventas/Index.jsx` |
| Notas de venta en Comprobantes | **Corrección:** el botón "Imprimir en la caja" solo aparecía para comprobantes aceptados por SUNAT, así que las notas de venta no se podían reimprimir. Ahora sigue la regla `Venta::imprimible()` (aceptado o `NO_APLICA`). El detalle explica que son documento interno, oculta XML/CDR y nunca ofrece "Enviar a SUNAT". Estado "No va a SUNAT" con su propio ícono | `Pages/Ventas/Index.jsx` |
| Filtro por tipo | **Corrección:** filtrar "Nota de venta" solo filtraba la página cargada. Ahora el servidor filtra todo el historial (`/ventas?tipo=NOTA_VENTA`), combinable con el estado SUNAT. Prop nueva `tipoComprobante` | `VentaController@index` |
| Caja | Reescrita con `Field`/`Input`/`Button`/`Table`: resumen compacto con el efectivo esperado destacado, movimiento ingreso/egreso con selector, arqueo con sobrante/faltante en vivo y historial. "Registrar venta" (iba a `/ventas/create`, que ya no existe) ahora es "Ir al punto de venta" | `Pages/Caja/Index.jsx` |
| Caja por comprobante | El resumen del turno incluye `ventas_por_comprobante`: cuántas boletas, facturas y notas de venta y por cuánto. Así el cajero ve al cerrar lo emitido sin valor tributario | `GestionarTurnoCajaService::resumen()` |
| Cobro | Selector de comprobante compartido, con su consecuencia en cada opción ("No va a SUNAT", "DNI o público", "Requiere RUC"). Lo usan el POS y la cuenta dividida, que antes tenía un `<select>`. Total destacado arriba | `Restaurante/SelectorComprobante.jsx`, `DialogoCobroPos.jsx`, `Pages/Restaurante/CobrarPedido.jsx` |
| Confirmación | **Corrección:** al confirmar el cobro de una cuenta dividida aparecía "NOTA_VENTA electrónica"; ahora dice "Nota de venta (sin valor tributario, no va a SUNAT)" | `Ventas/pos/DialogoConfirmarCobro.jsx` |

### Inventario

| Área | Cambio | Archivos |
|---|---|---|
| Salidas | **Corrección grave:** "Nueva salida" desactivaba todos los productos como "agotados", porque el controlador nunca enviaba `stock_actual` (fallaba desde la base del sistema). Ahora envía el saldo vigente de la sucursal activa (último movimiento de kardex), la unidad base y el tipo. Los platos preparados ya no aparecen | `SalidaController@create` |
| Existencias (antes "Kardex valorizado", menú "Movimientos de existencias") | Pestañas Insumos / Bebidas y venta directa / Platos preparados. El saldo va en unidad base con su equivalencia ("≈ 2 Caja x25 + 3 unidades"). Situación con texto: Con stock / Quedan pocos / Agotado / Por receta / En variantes. Antes los platos preparados y las bebidas con variantes salían como "0 unidades, stock bajo". Detalle con lotes y movimientos, edición de precios y toasts en lugar de modales de éxito | `Pages/Kardex/Index.jsx`, `KardexController@index`, `Services/Inventario/TipoExistencia.php` |
| Entrada de insumos | Dividida en `useIngresoCompra` (lógica, sin cambios de regla), `CatalogoIngreso`, `ProveedorComprobante` y `LineaIngreso` (de 1001 a 96 líneas la página). Las líneas de insumo no piden margen ni precio de venta: el backend solo cambia el PVP si llega un precio > 0. Para venta directa el precio queda en un desplegable. Los platos preparados no aparecen en el catálogo. En celular: pestañas Productos/Compra y botón flotante | `Pages/Ingresos/Create.jsx`, `Components/Ingresos/*` |
| Salida | Reescrita con componentes `ui` y `ConfirmDialog`. Cada línea muestra cuánto descuenta en su unidad y avisa si supera el saldo. Se quitó el "total de unidades", que sumaba kilos con botellas | `Pages/Salidas/Create.jsx` |
| Historiales | Entradas y Salidas con tabla en escritorio y tarjetas en celular. Se quitaron los indicadores de "monto en esta página" y "proveedores en esta página", que solo sumaban las 15 filas visibles | `Pages/Ingresos/Index.jsx`, `Pages/Salidas/Index.jsx` |
| Menú | "Movimientos de existencias" pasa a "Existencias" (con su filtro por rol actualizado) | `modules.js`, `filtrarNavegacionPorRol.js` |

### Administración

| Área | Cambio | Archivos |
|---|---|---|
| Proveedores | **Corrección:** al editar, el formulario leía `direccion` y el listado entrega `direccion_fiscal`, así que guardar cualquier cambio **borraba la dirección fiscal**. **Corrección:** cambiar el RUC exigía un motivo en el servidor, pero el formulario no tenía ese campo, así que el cambio siempre fallaba. Se quitó el campo "Sitio web", que se descartaba porque esa columna no existe | `Pages/Proveedores/Index.jsx`, `Components/Proveedores/formularioProveedor.js` |
| Clientes | **Corrección:** el listado no trae `tipo_persona` y el formulario asumía NATURAL, así que **editar un cliente empresa (RUC 20) siempre fallaba** y el filtro "Jurídica" nunca mostraba nada. Ahora se deduce del documento, con la misma regla que valida el servidor. También se agregó el motivo al cambiar el documento | `Pages/Clientes/Index.jsx`, `Components/Clientes/formularioCliente.js` |
| Paginación | **Corrección:** Proveedores (25 por página), Clientes (25) y Usuarios (20) paginaban en el servidor, pero la pantalla no mostraba paginación y solo buscaba en lo cargado: no se podían ver ni buscar los registros siguientes. Ahora la búsqueda va al servidor (`?buscar=`, con una pausa breve al escribir) y hay paginación | `hooks/useBusquedaServidor.js`, `UsuarioPageController` (búsqueda nueva) |
| Usuarios | Dividido en `FormularioUsuario`, `SelectorPermisos` y la pestaña de permisos (de 1064 a 167 líneas). Errores junto a cada campo en lugar de modales en mayúsculas. Se corrigió el mensaje que decía que la cuenta se guardaba "en Supabase" | `Pages/Usuarios/Index.jsx`, `Components/Usuarios/*` |
| Roles | Usa el mismo `SelectorPermisos`: búsqueda, marcado por módulo con estado intermedio, contador y paginación (antes no tenía) | `Pages/Usuarios/Roles.jsx` |
| Todos | Toasts para éxito y errores de servicio, `ConfirmDialog` con carga para desactivar, tarjetas en celular y tabla en escritorio | — |

Configuración (Sucursales, Datos generales, Métodos de pago, Impresoras, catálogos) ya usaba `CatalogoPagina` y los componentes comunes: no requirió cambios.

Extensiones de backend (de solo lectura y aditivas, con pruebas): el filtro `tipo` en `/ventas` y la clave `ventas_por_comprobante` en el resumen de caja; `tipo_producto`, `controla_stock` y `stock_en` en Existencias; `tipo_producto` en la entrada de insumos; `stock_actual`, `unidad_base` y el tipo en la salida (corrección); y la búsqueda `buscar` en Usuarios. No cambian rutas, contratos existentes, la lógica fiscal ni la emisión SUNAT.

## Verificación

- `php artisan test`: 223 pasan (1128 aserciones). Pruebas nuevas: filtro de comprobantes por tipo, resumen de caja por comprobante, `TipoExistenciaTest` (5), tipo en la entrada de insumos, saldo real en la salida y búsqueda de usuarios en el servidor.
- `npm run test:js`: 21 pasan (nuevas: formulario de proveedores y de clientes).
- `npm run build`: compila.
- **Falta la revisión visual en el navegador.** No se pudo levantar la app local porque la conexión a PostgreSQL de `.env` no tiene contraseña, y `.env` no se modifica. Antes de integrar, hay que recorrer en el celular: POS (agregar, guardar, enviar, cobrar con boleta y con nota de venta, salir con borrador), Comprobantes (filtrar notas de venta, reimprimir una), Caja (movimiento y arqueo), Existencias, una entrada de insumos y una salida, editar un proveedor con dirección y un cliente con RUC 20, buscar en la página 2 de clientes, Cocina, Salón y la barra inferior con cada rol.

## Pendiente (siguientes fases)

- **Stock mínimo por producto**: "Quedan pocos" usa una referencia fija de 5 en unidad base. Para avisos reales hace falta un campo `stock_minimo` por producto y sucursal (migración nueva).
- **Recetas y Mermas** (`Restaurante/Recetas.jsx`): no se revisaron en esta tanda.
- **Filtros por página**: el filtro por tipo de persona (Clientes) y por rol (Usuarios) todavía actúa sobre la página cargada. Llevarlos al servidor es una extensión pequeña.
- **Búsqueda en Comprobantes**: sigue filtrando solo la página cargada (como antes). Llevarla al servidor requiere una consulta `ILIKE` sobre cliente y número.
- **Productos/PresentacionesModal.jsx**: quedan tonos azul y morado fijos.
- **Cuenta de mesa** (`Pedido.jsx`): aplicar la misma separación que en el POS.
- **Salón**: estados "pendiente de atención" y "cuenta pendiente": el backend no los expone hoy. Si se quieren, hace falta una extensión mínima del `resumen` y de las mesas en el servidor.
- **Navegación**: `filtrarNavegacionPorRol` usa nombres de rol fijos para los ítems sin `permiso`. Lo ideal es asignar un `permiso` a cada ítem de `modules.js`.
