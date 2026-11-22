# Despliegue automático (main → host del restaurante)

Flujo: `.github/workflows/desplegar-restaurante.yml`. El host del restaurante es una **cuenta Namecheap independiente** del sistema comercial; el flujo no conoce ni toca la cuenta, carpetas ni base de datos del sistema comercial.

| Elemento | Valor |
| --- | --- |
| Servidor | `premium26.web-hosting.com` (SSH de Namecheap: puerto `21098`) |
| Usuario | `chifkeyz` |
| Proyecto Laravel | `~/sistema` (`/home/chifkeyz/sistema`) |
| Carpeta pública | `~/public_html` |
| Dominio | `https://chifapandwok.online` |
| Base | PostgreSQL `chifkeyz_restaurante_dev` (definida solo en `~/sistema/.env` del servidor) |

## Qué hace en cada push a `main`

1. **Verifica**: pruebas (SQLite en memoria, sin base real) y compilación de Vite. Si algo falla, no se publica.
2. Si el despliegue está habilitado, **publica**:
   - Valida la huella SSH del servidor (fijada en un secreto).
   - Confirma que `~/sistema` es el restaurante: debe existir el archivo `~/sistema/.despliegue-restaurante` con el texto `pand-wok-sistema`, un `.env` con PostgreSQL, PHP ≥ 8.2 con `pdo_pgsql`, rsync y Composer.
   - **Si el cambio trae migraciones nuevas o modificadas, se detiene sin publicar nada** (ver abajo).
   - Respalda el código actual en `~/respaldos-restaurante/` (conserva los 10 últimos).
   - Pone el sitio en mantenimiento, sincroniza el código (sin tocar `.env`, `storage`, `vendor` ni la marca), copia los assets públicos a `~/public_html` conservando su `index.php` y `.htaccess`, instala dependencias de producción, regenera cachés, reinicia la cola y sale de mantenimiento.
   - Comprueba `https://chifapandwok.online/salud`. Si un paso falla, intenta sacar el sitio de mantenimiento.

## Migraciones (manuales, con respaldo)

Cuando un push trae migraciones, el despliegue queda en rojo con el mensaje *"Este cambio trae migraciones nuevas o modificadas. No se publicó nada."* Entonces:

1. Respalda la base `chifkeyz_restaurante_dev` (cPanel → phpPgAdmin → Exportar, o `pg_dump` por SSH).
2. En GitHub → **Actions → Desplegar restaurante → Run workflow** (rama `main`), marca **aplicar_migraciones** y **respaldo_bd_confirmado**.

## Reinstalar la base desde cero (solo en desarrollo)

Desde el modelo de datos nuevo (`docs/BASE_DE_DATOS.md`) las migraciones ya no se aplican sobre las tablas anteriores: la base del servidor se instala de nuevo. Se puede hacer desde el celular, en el navegador:

1. Una sola vez, en **Settings → Secrets and variables → Actions → New repository secret**, crea `SEED_ADMIN_EMAIL` (correo del administrador) y `SEED_ADMIN_PASSWORD` (16 caracteres o más).
2. Con los cambios ya en `main`: **Actions → Desplegar restaurante → Run workflow**, rama `main`, y marca:
   - `reinstalar_base`
   - `respaldo_bd_confirmado`
   - en `confirmar_reinstalacion` escribe `BORRAR TODO`
3. El workflow publica el código, ejecuta `migrate:fresh --seed` y `restaurante:preparar`: 3 sucursales (Chilca, El Tambo, Tingo María), la carta completa con precios por sucursal, la sopa a elección de los menús y series B001/F001, B002/F002 y B003/F003.

**Borra todos los datos de la base** (ventas, pedidos, usuarios). Después de salir a producción no debe volver a usarse. Entra con el correo y la contraseña de los secretos, y revisa en Datos generales el RUC y que las series sean las registradas ante SUNAT.

## Configuración única en GitHub

Repositorio → **Settings → Environments → New environment** `restaurante-produccion` (opcional: *Required reviewers* para aprobar cada publicación).

**Secrets** (Settings → Secrets and variables → Actions → *Secrets*):

| Secreto | Valor |
| --- | --- |
| `SSH_HOST` | `premium26.web-hosting.com` |
| `SSH_PORT` | `21098` |
| `SSH_USER` | `chifkeyz` |
| `SSH_PRIVATE_KEY` | Clave privada **exclusiva** de despliegue (ver abajo) |
| `SSH_HOST_FINGERPRINT` | Huella ED25519 del servidor, formato `SHA256:…` (ver abajo) |

**Variables** (pestaña *Variables*):

| Variable | Valor |
| --- | --- |
| `DEPLOY_APP_DIR` | `sistema` |
| `DEPLOY_PUBLIC_DIR` | `public_html` |
| `DEPLOY_PHP_BIN` | Ruta del PHP 8.2+ de la cuenta, p. ej. `/usr/local/bin/php` (comprobar con `/usr/local/bin/php -v` por SSH) |
| `DEPLOY_COMPOSER_BIN` | `composer` si existe, o la ruta absoluta `/home/chifkeyz/composer.phar` |
| `DEPLOY_HEALTHCHECK_URL` | `https://chifapandwok.online/salud` |
| `DEPLOY_HABILITADO` | `true` **solo cuando todo lo anterior esté listo**. Mientras no exista, cada push solo verifica. |

## Preparación única en el servidor

1. **Clave de despliegue** (en tu PC): `ssh-keygen -t ed25519 -C "deploy-restaurante" -f deploy_restaurante`. En cPanel → **SSH Access → Manage SSH Keys → Import Key**, pega `deploy_restaurante.pub` y pulsa **Authorize**. El contenido de `deploy_restaurante` (privada) va al secreto `SSH_PRIVATE_KEY`; después borra la copia local.
2. **Huella del servidor**: `ssh-keyscan -t ed25519 -p 21098 premium26.web-hosting.com | ssh-keygen -lf -`. Comprueba el valor contra el que muestra el soporte o cPanel antes de guardarlo en `SSH_HOST_FINGERPRINT`.
3. **Marca del restaurante** (por SSH o el Terminal de cPanel): `echo pand-wok-sistema > ~/sistema/.despliegue-restaurante`
4. Comprueba que `~/public_html/index.php` carga `../sistema/vendor/autoload.php` y `../sistema/bootstrap/app.php`.

## Primera ejecución

1. **Actions → Desplegar restaurante → Run workflow** con **solo_probar_conexion** marcado: valida SSH, huella, marca, `.env`, PHP, `pdo_pgsql`, rsync y Composer sin publicar nada.
2. Si pasa, crea `DEPLOY_HABILITADO = true`. Desde ese momento cada push a `main` publica.
3. Como el servidor ya tiene una versión subida a mano, la primera publicación puede detenerse por migraciones distintas: sigue el procedimiento de migraciones con respaldo.

## Recuperación

- Código anterior: `~/respaldos-restaurante/codigo-<run_id>.tar.gz` (sin `vendor`, `storage` ni `.env`). Para volver atrás: modo mantenimiento, extraer sobre `~/sistema`, `composer install --no-dev` y regenerar cachés. O revertir el commit en GitHub y dejar que el flujo publique.
- Una migración aplicada se revierte restaurando el respaldo de la base.
- Si el sitio queda en mantenimiento: `cd ~/sistema && /usr/local/bin/php artisan up`.

## Advertencia

`--delete-delay` elimina en `~/sistema` los archivos de código que ya no están en el repositorio. Archivos agregados a mano en el servidor fuera de `.env`, `storage`, `vendor` y `bootstrap/cache` se borrarán en la primera publicación: muévelos a `storage` antes de habilitar el despliegue.

## Corrección: páginas Inertia en Linux (2026-11-19)

- **Fallo:** en GitHub Actions 6 pruebas fallaban porque `assertInertia` no encontraba `Restaurante/Salon`, `Cocina`, `Estaciones`, `Recetas` y `CobrarPedido`. `inertia-laravel` v3.3.4 busca por defecto en `resources/js/pages` (minúsculas) y el proyecto usa `resources/js/Pages`. Windows no distingue mayúsculas, por eso localmente pasaban.
- **Corrección:** `config/inertia.php` redefine solo `pages` (ruta `resources/js/Pages`, extensión `.jsx`) y mantiene `testing.ensure_pages_exist = true`. El resto de la configuración sigue la del paquete.
- **Prevención:** `tests/Feature/PaginasInertiaTest.php` compara nombres reales de carpetas y archivos, así detecta el error también en Windows (falla sin la corrección, pasa con ella).
- **Workflow:** `solo_probar_conexion` ahora se ejecuta aunque `DEPLOY_HABILITADO` no esté activo; ese modo nunca publica.
- **Resultado local:** `php artisan test` → 157 pruebas en verde. La confirmación en Linux es la ejecución de GitHub Actions de este commit.
