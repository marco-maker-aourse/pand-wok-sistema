# Guía de desarrollo

## Base tecnológica

El sistema utiliza Laravel 12 como aplicación monolítica, PostgreSQL en Supabase como base de datos, e Inertia con React para las pantallas internas. Inertia mantiene las rutas web, autenticación por sesión y protección CSRF de Laravel; React renderiza las páginas sin crear una segunda API pública innecesaria.

## Estructura

```text
app/Http/Controllers     Adaptadores HTTP: Inertia y API
app/Http/Requests        Contratos de entrada y validaciones
app/Models               Tablas PostgreSQL, agrupadas por esquema
app/Services             Flujos transaccionales de negocio
database/migrations      Esquema inicial versionado
resources/js/Pages       Pantallas React/Inertia
resources/js/Layouts     Navegación y marco compartido
resources/css/app.css    Tokens y componentes visuales del sistema
```

Los esquemas de PostgreSQL separan responsabilidades: `configuracion`, `seguridad`, `catalogos`, `restaurante`, `inventario` y `operaciones` (ver [`BASE_DE_DATOS.md`](BASE_DE_DATOS.md)). Los nombres de tabla se declaran explícitamente en los modelos para evitar depender de la convención plural de Laravel en un esquema distinto de `public`.

`catalogos.tipos_documento` normaliza DNI, RUC, CE y otros identificadores para Clientes y Usuarios; conserva límites mínimo/máximo para validar cada número y una descripción opcional. `catalogos.tipos_comprobante` normaliza factura, boleta, guía y documentos internos para Ventas e Ingresos; conserva el código técnico SUNAT, nombre y descripción opcional. Serie y correlativo pertenecen a cada operación, nunca al catálogo. La instalación inicial queda concentrada en seis migraciones, respetando el orden de sus relaciones.

## Flujos que ya son transaccionales

- Una venta guarda cabecera, detalle, pagos, existencias y Kardex en una única transacción.
- Un ingreso y una salida registran sus detalles y actualizan inventario en una única transacción.
- Los saldos de existencias se bloquean durante cada salida (venta, receta o merma) para no vender stock simultáneamente dos veces.
- El servidor obtiene el precio de la presentación y calcula comisión/pago; el navegador no es fuente de verdad.

El POS solo conserva el carrito local mientras se prepara el cobro. Al confirmar, envía identificadores, cantidades y el pago; `RegistrarVentaService` valida de nuevo catálogo, cliente activo, stock, totales, referencia requerida y comisión.

## Interfaz

La interfaz usa una paleta clara y corporativa: cian `#0891b2` como color de interacción, con superficies blancas y fondos neutros. Los íconos provienen de `react-icons`; no se incluyen imágenes o dependencias visuales externas.

La barra lateral inicia compacta y muestra sólo iconos. Al pasar el cursor se expande, reserva su ancho para el contenido y permite desplazar el listado de módulos sin superponerlo con el perfil. En pantallas pequeñas se reduce automáticamente, sin ocultar el acceso a los módulos.

## Rendimiento y módulos disponibles

El dashboard cachea sus indicadores por sucursal y día durante un minuto; una sede nunca reutiliza métricas de otra. El POS no descarga todo el catálogo y Clientes/Proveedores se consultan con búsqueda y paginación de 15 filas. Esta regla se mantiene para cada módulo nuevo: no cargar tablas completas ni calcular reportes desde React.

Actualmente funcionan las consultas de Productos, Clientes y Proveedores, además del flujo transaccional de Ventas. Inventario, Kardex, usuarios y reportes conservan sus rutas preparadas mientras se implementan sus casos de uso completos.

## Ejecución local

```powershell
composer install
npm install
php artisan migrate
php artisan db:seed
npm run dev
php artisan serve
```

El seeder crea datos iniciales sólo si se configuran `SEED_ADMIN_PASSWORD` (12 o más caracteres) y `SEED_CASHIER_PIN` (4 a 6 dígitos) en el entorno local. No contiene credenciales predeterminadas ni reutilizables.

No se necesita Apache para `php artisan serve`. Si se usa XAMPP, se utiliza su ejecutable PHP con la extensión `pdo_pgsql` habilitada; Apache sigue siendo opcional.

## Verificación antes de integrar

```powershell
php artisan test
npm run build
php artisan route:list
php artisan migrate:status
```

No ejecutar `migrate:fresh` contra Supabase si el entorno contiene información real. Para una instalación local descartable se puede usar `php artisan migrate:fresh --seed`; en Supabase se aplican sólo migraciones pendientes.

## Convenciones de continuación

- Las páginas nuevas se agregan bajo `resources/js/Pages` y se sirven por controladores Inertia.
- Las operaciones que actualicen más de una tabla van a un servicio y se cubren con una transacción.
- Las bajas de catálogos deben ser lógicas (`estado=false`) cuando ya existe historial.
- Las rutas se protegen con `auth` y el permiso específico; no se confía en que una pantalla oculta sea autorización.
- El rol `Administrador` tiene acceso funcional completo. Su relación `seguridad.usuario_sucursales` define las sedes que puede consultar; cada operación conserva una única sucursal activa para proteger stock y correlativos.
- Las APIs versionadas se reservan para clientes externos, integraciones o aplicaciones móviles. Para la interfaz interna, priorizar Inertia.
