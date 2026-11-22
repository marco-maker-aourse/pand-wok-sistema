# Arquitectura del sistema

El sistema es un monolito modular construido con Laravel 12, Inertia, React y PostgreSQL en Supabase. La interfaz y el backend se despliegan como una aplicación, pero cada dominio mantiene contratos, servicios y esquemas de base de datos separados.

## Principios de diseño

- PostgreSQL es la fuente de verdad; React no calcula precios finales, IGV, comisiones, correlativos ni stock.
- Las operaciones que modifican cabecera, detalle, pagos y Kardex se ejecutan dentro de una transacción.
- La sucursal y el usuario se obtienen de la sesión autenticada. Nunca se confían al navegador. Los modelos operativos aplican `SucursalScope` por defecto; un reporte transversal debe solicitar explícitamente `withoutGlobalScope` y validar autorización.
- Las bajas son lógicas mediante `estado`; el historial transaccional no se elimina.
- El modelo de datos está en 3FN y se describe en [`BASE_DE_DATOS.md`](BASE_DE_DATOS.md). No se crean tablas fuera de los seis esquemas.

## Dominios y módulos

| Dominio | Esquema PostgreSQL | Módulos |
| --- | --- | --- |
| Configuración | `configuracion` | empresa (una sola), sucursales y series de comprobantes |
| Seguridad | `seguridad` | usuarios, roles, permisos, acceso por sucursal, vinculación de agentes |
| Catálogos | `catalogos` | productos y sus presentaciones, categorías, marcas, unidades, clientes, proveedores, comprobantes, métodos de pago, impresoras y terminales |
| Restaurante | `restaurante` | salón, carta, pedidos, cocina, recetas y mermas |
| Inventario | `inventario` | Kardex |
| Operaciones | `operaciones` | turnos y movimientos de caja, compras, ventas, pagos y cola de impresión |

Las migraciones crean 49 tablas de negocio: 3 en `configuracion`, 6 en `seguridad`, 13 en `catalogos`, 19 en `restaurante`, 1 en `inventario` y 8 en `operaciones`. Las tablas técnicas de Laravel (`public`) no forman parte del modelo de negocio.

## Capas

```text
React/Inertia
    ↓
Rutas web con auth + permisos
    ↓
Controladores de página o acción
    ↓
Form Requests por dominio y adaptadores de entrada
    ↓
Servicios de dominio transaccionales
    ↓
Modelos Eloquent por esquema
    ↓
PostgreSQL/Supabase
```

Los controladores sólo resuelven la solicitud, autorización contextual y respuesta. La lógica de negocio se mantiene en `app/Services`:

- `Ventas/RegistrarVentaService`: verifica el turno abierto, calcula importes desde `catalogos.producto_presentaciones`, reserva correlativo bajo bloqueo, registra detalle, pago y Kardex.
- `Ingresos/RegistrarIngresoService`: registra compra, detalle y entrada de Kardex en una sola transacción.
- `Restaurante/RecetasService`: guarda recetas y registra mermas de insumos y productos de venta directa con su salida de Kardex.
- `Inventario/GestionarExistenciaService`: convierte presentaciones a unidad base, bloquea el último movimiento, calcula el costo promedio ponderado en entradas y valida el saldo antes de cada salida.
- `External/DocumentLookupService`: encapsula DNI/RUC y toma sus secretos de variables de entorno.
- `Jobs/EmitirComprobanteSunatJob`: desacopla firma y SOAP de la respuesta del POS; se encola sólo después de confirmar la transacción comercial.

## Reglas de inventario híbrido

Un producto se registra una sola vez. Sus formas de venta se definen en `catalogos.producto_presentaciones` y el campo `factor_conversion` expresa cuántas unidades base contiene cada presentación. Un tamaño con stock propio (botella de 1 L) usa `id_producto_stock`. El Kardex conserva el saldo por producto y sucursal. La venta, el consumo de recetas y la merma convierten a unidad base antes de descontar.

Las entradas actualizan el costo promedio ponderado (CPP) con `(saldo anterior × costo anterior + entrada × costo de entrada) / saldo nuevo`. Las salidas no recalculan ese valor: preservan el último costo vigente para que el historial contable sea consistente.

## Contratos de compatibilidad

Algunas pantallas heredadas aún llaman `id_prod_pres`. El backend conserva ese alias únicamente en la frontera visual y lo traduce inmediatamente a `id_precio`, que es la clave física de `catalogos.producto_presentaciones`. Cualquier módulo nuevo debe usar `id_precio` directamente.

## Seguridad

- Sesión Laravel, regeneración al iniciar sesión y cierre seguro.
- Middleware `auth` y `permission` en cada módulo.
- RBAC mediante `seguridad.roles`, `seguridad.permisos` y `seguridad.rol_permisos`.
- Validación de entrada con `FormRequest` por dominio. Los controladores no contienen reglas extensas ni cálculos operativos.
- Secretos externos únicamente en `.env`; `.env.example` contiene valores vacíos.
- Consultas de DNI/RUC y asistente de IA con límites de frecuencia. La pantalla administrativa puede indicar si una integración está configurada, pero jamás lee o edita secretos.
- El PIN de cajero se almacena con hash y se solicita para abrir o cerrar turno. Tres intentos fallidos bloquean temporalmente esa verificación; no sustituye la sesión ni los permisos.

## Rendimiento y concurrencia

- Dashboard y listados deben usar agregaciones SQL, paginación y columnas mínimas; cualquier dato global de navegación se comparte mediante closures y caché por sucursal.
- Eager loading explícito para impedir N+1 en tablas operativas.
- Paginación y columnas mínimas en listados.
- Índices del modelo: producto/sucursal/fecha en Kardex; sucursal/fecha en ventas; método/fecha en pagos.
- `lockForUpdate` dentro de transacciones para correlativos y saldos de inventario.
- La emisión a SUNAT se procesa por cola tras el commit, para que una dependencia externa lenta no degrade el POS.
- El catálogo documental se resuelve desde su código SUNAT en la frontera de la interfaz y se persiste siempre por su llave foránea; no se crean registros de catálogo durante operaciones comerciales.

## Flujo de cambios

1. Crear o modificar una migración sólo cuando el equipo apruebe un cambio de modelo.
2. Actualizar primero modelo, request y servicio del dominio.
3. Luego adaptar controlador, recurso Inertia y pantalla.
4. Añadir prueba de servicio o feature para los flujos que cambian existencias o dinero.
5. Ejecutar `php artisan test`, `npm run build` y `php artisan route:list` antes de integrar.

## Configuración externa

Las claves se definen únicamente en `.env`:

```dotenv
API_PERU_TOKEN=
GROQ_API_KEY=
```

No se deben versionar tokens, contraseñas de Supabase, certificados SUNAT ni archivos `.env`.
