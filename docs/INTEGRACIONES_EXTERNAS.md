# Integraciones externas

Las claves del sistema se guardan exclusivamente en el archivo local `.env`. El
repositorio conserva solo nombres de variables en `.env.example`.

## Consultas de DNI y RUC

Las consultas de documentos pertenecen únicamente a los formularios CRUD de
**clientes**, **usuarios** y **proveedores**. No deben ejecutarse en listados,
dashboard ni procesos masivos.

Antes de habilitarlas el equipo define el proveedor contratado y sus URLs
oficiales. Las variables previstas son:

```dotenv
API_PERU_DNI_URL=
API_PERU_RUC_URL=
API_PERU_TOKEN=
```

No se incluyen tokens ni URLs de prueba en código JavaScript, seeders o Git.
Laravel será responsable de validar DNI (8 dígitos) o RUC (11 dígitos), aplicar
autorización, tiempo máximo de respuesta y registrar errores sin revelar datos
sensibles.

## Asistente de dashboard

El asistente es opcional. Se activa únicamente cuando existen estas variables
en el entorno local:

```dotenv
GROQ_API_URL=
GROQ_API_KEY=
GROQ_MODEL=openai/gpt-oss-20b
```

Mientras la configuración esté vacía, el frontend no muestra el asistente y
Laravel no realiza solicitudes externas.
