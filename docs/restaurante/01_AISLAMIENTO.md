# Etapa 1 — Copia y aislamiento

Fecha: 2026-11-19. Origen: `C:\laravel\Sistema_web_MM` (no modificado). Destino: `C:\laravel\Sistema_restaurante`.

## Qué se copió

Copia completa del árbol de trabajo (incluye `vendor/` y `node_modules/`, y los cambios sin commit del original en ese momento) con `robocopy /XJ` (sin seguir enlaces simbólicos).

## Qué NO se copió (a propósito)

| Elemento | Motivo |
| --- | --- |
| `.git/` | El repositorio original tiene `origin` en GitHub y flujos de despliegue. La copia tiene un repositorio nuevo (`git init -b main`), **sin remoto** y sin commits. |
| `.env` | El del original apunta a la BD **PostgreSQL en Supabase** (pooler remoto) y contiene secretos reales (SUNAT, Groq, API Perú). |
| `public/storage` | Era un enlace simbólico al `storage` **del original**. Se recreó con `php artisan storage:link` apuntando a la copia. |
| `storage/app/private/sunat/` | XML/CDR/PDF de comprobantes reales emitidos por el sistema original. |
| `storage/app/keys/` | Certificado digital `.pfx`. |
| `storage/logs`, cachés, sesiones, vistas compiladas, `bootstrap/cache/*.php`, `public/build` | Estado de ejecución del original. |
| `storage/inertia-devtools/` | Capturas de páginas del original (podían contener datos reales). Eliminado de la copia. |
| `.claude/`, `.vite/`, `.phpunit.result.cache`, `.docx` en workflows | Artefactos locales. |

## Configuración propia de la copia (`.env`, generado desde `.env.example`)

- `APP_NAME="Sistema Restaurante"`, `APP_URL=http://localhost:8001`, `APP_KEY` **nueva**.
- `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_DATABASE=sistema_restaurante_dev` (**base aún no creada**; falta la contraseña local).
- `SESSION_COOKIE=restaurante_session` y `CACHE_PREFIX=restaurante_`: ambos sistemas pueden correr en `localhost` sin compartir sesión.
- `SUNAT_MODE=beta`, sin credenciales SOL ni certificado. `ASISTENTE_PROVEEDORES` vacío; sin claves de IA ni de API Perú.

## Despliegue automático desactivado

Se movieron a `.github/workflows-deshabilitados/`: `desplegar-produccion.yml` (SSH a producción con `secrets.*`), `deploy-dry-run.yml`, `test-ssh.yml`, `update-changelog.yml`, `issues.yml` y `pull-requests.yml`. Quedan activos solo `build.yml` y `tests.yml`. Además, el repositorio no tiene remoto.

## Verificación de la línea base (en la copia)

- `php artisan test` → **89 pruebas en verde** (SQLite en memoria según `phpunit.xml`; no toca ninguna BD real).
- `npm run build` → compila.
- `config('database.connections.pgsql.host')` → `127.0.0.1`.

## Riesgos de aislamiento identificados

1. **BD de producción del original (crítico).** Copiar `.env` habría apuntado el restaurante a Supabase. Mitigado: no se copió. **Regla:** antes de cualquier `migrate`/`db:seed`, ejecutar `php artisan config:show database.connections.pgsql` y confirmar host `127.0.0.1` y base `sistema_restaurante_dev`.
2. **Agente de impresión compartido.** `C:\laravel\agente-impresion` está fuera de ambos proyectos y **no es un repositorio Git**. Modificarlo para comandas afectaría al agente del sistema original. Decisión: en la Etapa 5 se trabaja sobre una copia (`C:\laravel\agente-impresion-restaurante`) con `appId`/`productName` propios, para que ambos agentes puedan instalarse en paralelo.
3. **El original está en edición activa.** Durante la copia se estaba desarrollando el *Kardex valorizado* (`KardexController`, `ExportarKardexService`, `Pages/Kardex/Index.jsx`, `routes/web.php`, `Pages/Kardex/Show.jsx`, `tests/Unit/ExportarKardexTest.php`). La copia tomó un estado intermedio coherente (las pruebas pasan), pero quedó desfasada en esos archivos. Si se quiere esa función, conviene volver a copiar solo esos archivos cuando el trabajo en el original termine.
4. **Cookies en `localhost`.** Las cookies no se separan por puerto. Mitigado con `SESSION_COOKIE` y `CACHE_PREFIX` propios.
5. **Certificado/serie SUNAT.** Si el restaurante emite con el mismo RUC que el comercio, las series (`B001`, `F001`) colisionarían con las del original. Usar series distintas por establecimiento antes de emitir en producción.
6. **`SucursalScope` no se aplica en consola** (`runningInConsole()`), lo que incluye las pruebas. Los aislamientos por sucursal deben probarse con aserciones explícitas, no confiando en el scope.

## Pendiente de tu parte

- Crear la base `sistema_restaurante_dev` en tu PostgreSQL local (puerto 5432) y poner `DB_PASSWORD` en `.env`. No se ha ejecutado ninguna migración ni seeder.
