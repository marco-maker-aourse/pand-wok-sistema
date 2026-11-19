# Organización y rendimiento

## Estructura vigente

La aplicación conserva un monolito modular: una sola aplicación Laravel/Inertia, con límites claros por dominio.

```text
app/
  Http/Controllers/       Adaptadores HTTP e Inertia
  Http/Requests/          Validación y normalización de entradas
  Models/                 Modelos Eloquent por esquema PostgreSQL
  Services/
    Ventas/               Operaciones monetarias
    Ingresos/             Compras y entradas
    Salidas/              Ajustes y salidas
    Inventario/           Kardex, lotes y CPP
    Comprobantes/         Correlativos y reglas de emisión
    Dashboard/            Consultas agregadas cacheables
    External/             Proveedores externos (DNI/RUC)
resources/js/
  Pages/                  Pantallas Inertia, separadas por módulo
  Layouts/ Components/    Elementos compartidos de interfaz
routes/
  web.php                 Punto de composición autenticado
  web/                    Mapas de rutas por dominio para una migración gradual
database/
  migrations/             Contrato físico aprobado
  seeders/                Datos mínimos de arranque
  sql/postgresql/         Objetos SQL opcionales y versionables
```

No se deben mover controladores, modelos o páginas sólo por estética. Cada traslado debe conservar namespace, importaciones, rutas y pruebas; se hará por módulo al intervenirlo funcionalmente. Así se evita una refactorización riesgosa sin beneficio operativo.

## Procedimientos almacenados

PostgreSQL permite procedimientos y funciones. En este sistema deben reservarse para operaciones donde la base aporta una garantía adicional de concurrencia o agregación, no para CRUD normal:

1. **Correlativos de comprobantes**: `operaciones.reservar_correlativo` bloquea la configuración de una sucursal y devuelve serie, correlativo e IGV de forma atómica.
2. **Cierre de caja**: recomendable cuando se implemente el cierre definitivo; debe agrupar pagos, movimientos y turno en una única transacción.
3. **Reportes pesados**: vistas materializadas o funciones de lectura para consolidaciones históricas, actualizadas de forma controlada.

La función de correlativos está en `database/sql/postgresql/funciones_operativas.sql`. Aún no reemplaza el servicio Laravel: `ReservarCorrelativoService` ya usa `lockForUpdate` y es el mecanismo activo. La adopción debe aprobarse, ejecutarse en Supabase mediante migración revisada y probarse con concurrencia antes de activar su llamada desde PHP.

## Límites de responsabilidad

- Laravel conserva autorización, Form Requests, transacciones de negocio y respuestas HTTP.
- PostgreSQL protege bloqueos, integridad referencial, índices y funciones atómicas puntuales.
- React sólo presenta datos; no calcula stock, precio, impuestos, comisiones ni correlativos.

Esta división mantiene trazabilidad, reduce acoplamiento y evita duplicar reglas de negocio entre PHP y PL/pgSQL.
