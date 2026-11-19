# Etapa 2 — Auditoría técnica del sistema base

Basada en el código copiado (2026-11-19). Leyenda: **A** reutilizable · **B** adaptable · **C** nuevo · **D** obsoleto para restaurante (se conserva hasta evaluar dependencias).

## Arquitectura real

- Monolito Laravel 12 + Inertia (React 19, JSX, no TypeScript) + Tailwind 4 + Vite 7. PHP 8.2.
- PostgreSQL con 4 esquemas: `seguridad`, `catalogos`, `inventario`, `operaciones`. 16 migraciones; 1 función SQL (`operaciones.reservar_correlativo`).
- Capas: rutas con `auth` + `permission:<codigo>` → controladores delgados → Form Requests → servicios transaccionales (`app/Services/*`) → modelos Eloquent.
- RBAC propio (`seguridad.roles/permisos/rol_permisos`, middleware `EnsurePermission`), no Policies de Laravel.
- Multisucursal: trait `BelongsToSucursal` + `SucursalScope` (filtra por `idSucursalActiva()` del usuario; **se desactiva en consola**). FKs compuestas `(id, id_sucursal)` en impresoras/terminales/trabajos para impedir cruces de sede.
- Pruebas: 89 (PHPUnit, SQLite en memoria) + `tests/js` con `node --test`.
- Frontend: `Components/ui/*` (Button, Dialog, Confirmacion, Toast, Table, EmptyState, Skeleton…), layout con barra lateral, paleta de comandos y selector de sucursal; tema de marca desde Datos Generales (`Utils/theme.js`).

## Inventario de módulos

| Módulo | Estado real | Clase | Nota para restaurante |
| --- | --- | --- | --- |
| Autenticación, perfil, PIN de cajero | Completo | A | — |
| Usuarios, roles, permisos | Completo, probado | A | Añadir permisos nuevos (mesas, comandas, cocina, anulaciones, descuentos). |
| Sucursales + sucursal activa | Completo, probado | A | Base del aislamiento por local. |
| Datos generales (empresa/marca/series) | Completo; **una fila por sucursal** (`datos_gen.id_sucursal` único) | A/B | Revisar: `ruc_empresa` también es único, lo que impide dos sucursales con el mismo RUC. Hay que validarlo antes de operar varios locales. |
| Categorías, unidades, presentaciones, marcas | CRUD completo | A/B | Categorías sirven para la carta. Marcas → D para platos (útil en bebidas/insumos). |
| Productos + `precio_producto` (presentaciones con factor) | Completo | B | Falta tipo (plato preparado / venta directa / insumo), disponibilidad, estación, modificadores. |
| Clientes, proveedores, tipos de documento, consulta DNI/RUC | Completo | A | — |
| Métodos de pago (comisión, referencia) | Completo | A | — |
| Caja: turnos, apertura/cierre con PIN, movimientos, arqueo | Completo, probado | A | Se integra tal cual con el cobro de pedidos. |
| POS de ventas (`RegistrarVentaService`) | Completo: turno bloqueado, precios desde BD, correlativo con bloqueo, pagos mixtos, Kardex | B | **No tiene clave de idempotencia** (doble clic/reintento = doble venta). **No hay anulación de ventas** (ni estado ni nota de crédito). Vende desde un carrito, no desde un pedido. |
| Comprobantes + SUNAT (job en cola, `ShouldBeUnique`) | Completo (beta) | A | Reutilizable sin cambios para boleta/factura. |
| Ticket térmico (web, PDF, agente) | Completo | A | Base del ticket de precuenta. |
| Ingresos (compras) con lotes y CPP | Completo | A | Compras de insumos. |
| Salidas/ajustes | Completo | A/B | Base para **mermas** (tipo de salida). |
| Kardex + `GestionarExistenciaService` (conversión a unidad base, CPP, FIFO por lote, bloqueo) | Completo; `origen` recién añadido (`tipo_operacion`, `id_referencia`) | A/B | Reutilizable para insumos. Añadir orígenes `CONSUMO_RECETA`, `REVERSION`, `MERMA`, y un índice único para impedir descuento duplicado por línea de pedido. |
| Kardex valorizado / exportación | En desarrollo en el original (copia parcial) | B | Ver riesgo 3 de `01_AISLAMIENTO.md`. |
| Impresoras, terminales POS, vinculación de agente | Completo | B | La impresora pertenece a la terminal; falta asociar impresoras a **estaciones de cocina**. |
| Cola de impresión (`trabajos_impresion`) | Completo: `clave_idempotencia` única, reclamo con bloqueo, `REQUIERE_REVISION` en lugar de reintento ciego | B | `tipo` limitado por CHECK a `TICKET_VENTA/PRUEBA/DIAGNOSTICO`; añadir `COMANDA` y `PRECUENTA`. El contenido ya viaja con la definición de impresora, así que un agente puede imprimir en la impresora de red de cocina. |
| Agente Electron (`C:\laravel\agente-impresion`, TS, v0.5.0) | Completo; fuera del repositorio | B | Añadir renderizado de comanda. Trabajar en una copia propia (ver aislamiento). |
| Dashboard (`DashboardMetricsService`) | Completo con SQL real | B | Añadir métricas de restaurante. |
| Reportes, Lotes | **Placeholder** (`ModulePageController`) | C | Reportes de restaurante desde cero. |
| Asistente IA (multi-proveedor) | Completo | A/D | Funciona; desactivado en `.env`. |
| Mesas, áreas, pedidos, comandas, KDS, modificadores, recetas, mermas, división de cuentas | No existen | C | Núcleo nuevo. |

## Hallazgos técnicos relevantes

1. **Cobro duplicado posible.** `ventas` no tiene clave de idempotencia. Para el restaurante es obligatoria: un pedido solo se puede cobrar una vez (por ítem o por monto).
2. **Sin anulación.** No existe flujo de anulación ni reversión de Kardex. Hay que construirlo para pedidos (antes de cobrar) y, para ventas emitidas, vía nota de crédito (fuera del alcance inicial).
3. **Tiempo real.** No hay WebSockets (`BROADCAST_CONNECTION=log`, sin Reverb/Pusher). La cola de impresión ya funciona por *polling* del agente.
4. **Rutas muertas.** `routes/web/catalogos.php`, `operaciones.php` y `seguridad.php` duplican rutas pero **no se cargan** desde ningún sitio (verificado con `route:list`: todas las rutas activas llevan `auth` + `permission`). Clase D: no editar; las rutas nuevas irán en archivos que sí se registren.
5. **Scope en consola.** `SucursalScope` no actúa en jobs ni pruebas; los servicios nuevos deben filtrar `id_sucursal` explícitamente.
6. **Restricción de BD:** `ARQUITECTURA.md` prohíbe tablas fuera de los 4 esquemas. El dominio de restaurante necesita un esquema nuevo o una excepción explícita (decisión en `03_PLAN_TECNICO.md`).
