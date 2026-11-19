# Flujo de trabajo: desarrollo → producción

| Rama | Para qué | Qué pasa al hacer push |
| --- | --- | --- |
| `desarrollo` | Trabajo diario: nuevas funciones, correcciones, pruebas | Pruebas (PHP 8.2–8.4) y compilación. **No publica nada.** |
| `main` | Lo que está en producción (`chifapandwok.online`) | Pruebas, compilación y **despliegue automático** al host. Se detiene si hay migraciones nuevas. |

## Día a día

```powershell
git switch desarrollo
git pull
# ... cambios ...
git add -A
git commit -m "feat: ..."
git push
```

Revisa en **Actions** que la ejecución de `desarrollo` esté en verde.

## Pasar a producción

1. En GitHub: **Pull requests → New pull request**, base `main` ← compare `desarrollo`. Las pruebas corren sobre el pull request.
2. Si todo está en verde, **Merge pull request**. Eso es un push a `main` y despliega.
3. Si el cambio trae migraciones, el despliegue se detiene sin publicar: respalda la base y ejecuta **Desplegar restaurante → Run workflow** con `aplicar_migraciones` y `respaldo_bd_confirmado`.

## Recomendado en GitHub

**Settings → Branches → Add branch ruleset** para `main`: exigir pull request y que pasen las verificaciones (*Tests* y *Pruebas y compilación*). Así nadie publica en producción con un push directo por error.
