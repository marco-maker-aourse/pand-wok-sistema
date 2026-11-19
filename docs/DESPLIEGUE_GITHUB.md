# Despliegue automático con GitHub Actions

El workflow `.github/workflows/deploy.yml` se activa en cada `push` a `main` o manualmente. No publica si falla una sola de estas validaciones:

1. Instalación de dependencias PHP.
2. Pruebas Laravel.
3. Compilación React/Vite y validación de `public/build/manifest.json`.
4. Validación del host SSH por huella ED25519 fijada.
5. Comprobación de estructura remota necesaria.

Solo luego publica código y assets. Conserva `.env`, `storage`, `vendor` durante la transferencia; después instala dependencias de producción, ejecuta migraciones con `--force`, reconstruye cachés de Laravel y comprueba la URL de salud.

## Configuración obligatoria en GitHub

Crear el Environment `production` y, de ser posible, configurar aprobación obligatoria. Agregar estos secretos:

| Secreto | Uso |
|---|---|
| `SSH_PRIVATE_KEY` | Clave privada exclusiva de despliegue. |
| `SSH_HOST` | Host SSH del hosting. |
| `SSH_PORT` | Puerto SSH. |
| `SSH_USER` | Usuario SSH. |
| `DEPLOY_HEALTHCHECK_URL` | URL HTTPS completa que responda correctamente, por ejemplo `https://dominio.example/up`. |

La huella ED25519 en el workflow debe coincidir con la del servidor. Si el proveedor cambia de servidor o clave, primero se valida la nueva huella por un canal confiable y recién luego se actualiza el workflow.

## Seguridad y recuperación

- No se transfieren `.env`, secretos, `storage`, `node_modules`, pruebas ni archivos de desarrollo.
- `rsync --delete-delay` elimina únicamente archivos de código obsoletos dentro de `~/Sistema`; las exclusiones evitan afectar datos persistentes.
- Si falla una prueba, compilación, migración o health check, el workflow queda fallido. La recuperación de una migración no reversible requiere un backup previo de la base de datos; por eso se recomienda aprobar el Environment `production` antes de cada publicación relevante.
- Antes de la primera ejecución, lanzar `Probar conexión SSH Namecheap` y `Simular despliegue Namecheap` manualmente desde GitHub Actions.
