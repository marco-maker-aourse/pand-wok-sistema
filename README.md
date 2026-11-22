# Sistema para restaurantes

Aplicación monolítica modular para una empresa de restaurantes con varias sucursales: salón, pedidos, cocina, punto de venta, caja, compras, inventario con recetas y mermas, y facturación electrónica.

## Stack

- Laravel 12 y PHP 8.2+
- Inertia.js y React
- PostgreSQL/Supabase
- Tailwind CSS

## Inicio local

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
```

Configure PostgreSQL/Supabase en `.env`. Para poblar datos de desarrollo, defina el correo del administrador, una contraseña segura (16 caracteres o más) y un PIN de caja antes de ejecutar el seeder:

```dotenv
SEED_ADMIN_EMAIL=
SEED_ADMIN_PASSWORD=
SEED_CASHIER_PIN=
```

Después:

```powershell
php artisan migrate:fresh --seed
php artisan restaurante:sucursales --completar --horarios=database/data/carta/pand-wok.json
php artisan restaurante:importar-carta database/data/carta/pand-wok.json
npm run dev
php artisan serve
```

## Procesos principales

1. Un administrador configura los datos de la empresa, sus sucursales con sus series, y los catálogos.
2. El cajero abre turno con fondo inicial y PIN de caja.
3. Una venta reserva correlativo, registra pago, actualiza Kardex y confirma en una transacción.
4. La emisión SUNAT se procesa en cola; el POS no espera SOAP. Ejecute un worker:

```powershell
php artisan queue:work --tries=3 --timeout=60
```

5. Al cierre, el cajero revalida su PIN e ingresa el efectivo contado; el sistema conserva cualquier diferencia.

## Arquitectura

- `app/Services`: lógica de dominio y transacciones.
- `app/Http/Controllers`: frontera HTTP/Inertia.
- `app/Http/Requests`: validación de entrada.
- `app/Jobs`: procesos externos y diferidos, incluida emisión SUNAT.
- `database/migrations`: seis esquemas de negocio (`configuracion`, `seguridad`, `catalogos`, `restaurante`, `inventario` y `operaciones`). El modelo está descrito en [`docs/BASE_DE_DATOS.md`](docs/BASE_DE_DATOS.md).

La documentación de arquitectura, integración externa y facturación está en [`docs`](docs).

## Seguridad

No suba `.env`, certificados digitales, contraseñas, PINes, tokens ni archivos generados. Use permisos por rol, variables de entorno y una cola activa para las integraciones externas.
