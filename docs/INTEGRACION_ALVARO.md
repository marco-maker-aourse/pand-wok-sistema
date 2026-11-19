# Integración de módulos de Álvaro

La rama de interfaz se integró como fuente de módulos, componentes y pantallas React. El sistema conserva como contrato de datos los esquemas PostgreSQL `seguridad`, `catalogos`, `inventario` y `operaciones` definidos por sus migraciones principales.

## Regla de acoplamiento

Ningún módulo nuevo debe acceder a tablas sin esquema, crear tablas en `public`, ni calcular correlativos con consultas sin bloqueo. Las rutas activas continúan utilizando controladores, `FormRequest` y servicios transaccionales.

Los controladores incorporados desde una rama visual permanecen como referencia funcional, pero no se registran en rutas hasta reemplazar sus consultas heredadas por modelos con nombres de esquema y validaciones del sistema. Esta medida evita que una pantalla visual nueva introduzca doble facturación, stock inconsistente o acceso no autorizado.

## Próxima adaptación por módulo

1. Productos, marcas y categorías: reutilizar `ProductoPageController` y los modelos `catalogos.*` con las vistas nuevas.
2. Clientes y proveedores: alimentar las vistas nuevas mediante `CatalogoPageController`, con búsqueda y paginación.
3. Ventas, ingresos y salidas: mantener `RegistrarVentaService`, `RegistrarIngresoService` y `RegistrarSalidaService`; sólo reemplazar el contrato visual.
4. Kardex y configuración: usar `OperacionConsultaController` y permisos existentes antes de habilitar acciones.

La compilación incorpora `sweetalert2` y `@headlessui/react`, dependencias necesarias para los componentes visuales incorporados.
