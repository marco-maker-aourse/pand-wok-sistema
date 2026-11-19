# Rediseño y refactorización del frontend

Documento único de seguimiento del rediseño. Se actualiza en cada fase.

Stack: Laravel + Inertia (React 19, JSX sin TypeScript) + Tailwind CSS v4 + Vite 7. El proyecto no tiene ESLint ni TypeScript configurados.

## 1. Diagnóstico inicial

| Problema | Detalle |
| --- | --- |
| Color de marca desconectado de la BD | `Datos Generales` guarda `color_primario` en `datos_gen` y el servidor lo comparte como `organization`, pero `Utils/theme.js` aplicaba el tema leyendo `localStorage`. El color solo cambiaba en el navegador donde se guardó. |
| Caché del acceso | Al guardar se invalidaba `empresa_config_{sucursal}`, pero el login (sin sesión) lee `empresa_config_principal`, así que mostraba el color anterior hasta 10 minutos. |
| Colores fijos | 35 estilos en línea `var(--brand-primary, #0e7490)` con `text-white` fijo (ilegible con marcas claras), botones primarios `bg-emerald-800`, `bg-cyan-700`, `bg-slate-900` y acentos `blue-*` independientes de la marca. |
| Estética genérica | `rounded-3xl`, `font-black` (497 usos), textos de 9–11 px (353 usos), mayúsculas con `tracking-widest`, `backdrop-blur`, sombras de color, halos difuminados en el login y clases `animate-in` que no hacían nada (no existe el plugin). |
| Duplicación | 7 catálogos (marcas, unidades, categorías, presentaciones, tipos de documento, tipos de comprobante, métodos de pago) con unas 3.600 líneas casi idénticas y dos patrones distintos (formulario lateral y modal). |
| Monolitos | `Ventas/Create.jsx` (1.403 líneas) con tres selectores, catálogo, carrito, cobro y cuatro modales en un archivo. Otros archivos grandes: `Productos/Index` (1.386), `Usuarios/Index` (1.047), `Ingresos/Create` (1.000) y `Clientes/Index` (973). |
| Código muerto | Páginas no renderizadas por ninguna ruta (`Catalogs/*`, `Configuracion/Index`, `Welcome`, `*/Show.jsx` vacíos, `Kardex/Producto` vacío) y componentes sin importar (`Checkbox`, `Dropdown`, `NavLink`, `ResponsiveNavLink`, `FormField`). |
| Accesibilidad | Modales sin foco atrapado ni cierre con Escape, botones de solo ícono sin nombre accesible, etiquetas sin asociar a su campo y tarjetas de producto del POS como `div` clicables. |

## 2. Decisiones de diseño

- **Dirección:** interfaz empresarial sobria y densa (estilo suizo/minimalista). Superficies blancas con borde de 1 px, sin sombras decorativas, radio de 8 px en controles y 12 px en tarjetas, texto base de 14 px y cifras tabulares.
- **Tipografía:** fuentes del sistema (`Segoe UI Variable`, `system-ui`). Se evitan fuentes externas para no depender de la red en el punto de venta.
- **Mayúsculas:** títulos, etiquetas y botones en tipo oración. Los datos que el negocio guarda en mayúsculas (nombres, códigos) se conservan tal cual.
- **Patrón de catálogo único:** encabezado con acción principal, tabla filtrable y formulario en diálogo. Las confirmaciones usan un diálogo de confirmación y los resultados un aviso no bloqueante (toast), con lo que se elimina el clic extra de «Entendido» tras cada guardado.
- **Colores semánticos:** verde, ámbar, rojo y celeste se reservan para éxito, advertencia, error e información; nunca para acciones principales.
- **Movimiento:** solo transiciones de color y opacidad de 150 ms. Se respeta `prefers-reduced-motion`.
- **Modo oscuro:** el proyecto no tenía modo oscuro, así que no se agregó. Los tokens permiten añadirlo.

## 3. Origen de los colores (Datos Generales)

Fuente de verdad: `datos_gen.color_primario`, editado en **Configuración → Datos generales** y compartido por `HandleInertiaRequests` como `organization.color_primario`.

1. `resources/js/app.jsx` aplica el color antes del primer render y en cada navegación (`router.on('navigate')`), así un cambio guardado se ve sin recargar ni tocar código.
2. `resources/js/Utils/theme.js` (`aplicarColorMarca`) escribe tres variables:
   - `--brand-primary`: el color configurado.
   - `--brand-on`: blanco u oscuro, según el que dé más contraste sobre la marca.
   - `--brand-ink`: la marca oscurecida hasta un contraste de 4,5:1 sobre blanco, para texto e íconos de marca.
3. `resources/css/app.css` deriva `--brand-hover`, `--brand-soft`, `--brand-border` y `--brand-ring` con `color-mix` y los expone a Tailwind como `bg-brand`, `text-on-brand`, `hover:bg-brand-hover`, `bg-brand-soft`, `text-brand-ink`, `border-brand-border` y `ring-brand-ring`.
4. En Datos Generales, elegir un color lo aplica de inmediato como vista previa. Si se sale sin guardar, la siguiente navegación restablece el color guardado.

`--brand-light` y `--brand-light-text` siguen como alias de compatibilidad.

## 4. Componentes compartidos

`resources/js/Components/ui/` (primitivas genéricas; se mantienen en inglés por ser vocabulario estándar de UI, no de negocio):

| Componente | Uso |
| --- | --- |
| `Button`, `buttonClasses` | Variantes `primary`, `secondary`, `ghost`, `danger` y `danger-ghost`; tamaños `sm`, `md` y `lg`; estado `loading`; `href` para enlaces Inertia. |
| `IconButton` | Botón de solo ícono con `label` obligatorio (nombre accesible y tooltip). |
| `Card`, `CardHeader`, `CardBody`, `CardFooter` | Superficies. |
| `PageHeader` | Migas de pan, título, descripción, metadatos y acciones. |
| `Badge`, `StatusBadge` | Estados con tonos semánticos. |
| `Field`, `Input`, `Select`, `Textarea`, `CheckboxField` | Etiqueta, ayuda y error asociados (`aria-invalid` y `aria-describedby`). |
| `SearchInput` | Búsqueda con botón para limpiar. |
| `Table`, `THead`, `Th`, `TBody`, `Tr`, `Td`, `TableEmpty` | Tablas densas. |
| `Dialog`, `ConfirmDialog`, `NoticeDialog` | Basados en Headless UI: foco atrapado, Escape y título anunciado. |
| `Toaster`, `toast` | Avisos no bloqueantes; `Toaster` se monta en el layout. |
| `EmptyState`, `Stat` | Estados vacíos e indicadores. |

Componentes de negocio nuevos (en español):

- `Components/Catalogo/useCatalogoCrud.js`: hook de alta, edición, desactivación, validación en cliente y avisos. También exporta `useFiltroTexto` y `normalizarColeccion`.
- `Components/Catalogo/CatalogoPagina.jsx`: estructura común de catálogo, más `AccionesFila` y `SugerenciasRapidas`.
- `Components/Ventas/pos/`: `CatalogoPos`, `LineaCarrito`, `DialogoConfirmarCobro`, `SelectoresPos` (tipo de documento, lote y presentación) y `formato.js` (`formatoMoneda`).
- `Layouts/navegacion/`: `BarraLateral.jsx` y `filtrarNavegacionPorRol.js`. Este último conserva la misma lógica de menú por rol; la autorización real sigue en el servidor.
- `Pages/Configuracion/Partials/`: `SelectorColorMarca.jsx` y `VistaPreviaLogin.jsx`.

## 5. Archivos reorganizados o eliminados

| Cambio | Archivos |
| --- | --- |
| Reescritos sobre la base común | `Marcas`, `Unidades`, `Categorias`, `Presentaciones`, `TiposDocumento`, `TiposComprobante`, `MetodosPago` (de unas 3.600 a unas 700 líneas en total). |
| Divididos | `Ventas/Create.jsx` (1.403 → 841 líneas), `Configuracion/DatosGenerales.jsx`, `Layouts/AuthenticatedLayout.jsx`. |
| Rediseñados | `Auth/Login`, `Operations/Index`, `Modules/Placeholder`. |
| Encabezado común (`PageHeader`) | `Clientes`, `Proveedores`, `Productos`, `Usuarios`, `Roles`, `Ingresos/Index`, `Salidas/Index`, `Kardex`, `Ventas/Index`. |
| Eliminados (verificado que ninguna ruta los renderiza ni ningún archivo los importa) | `Pages/Catalogs/*` (6), `Pages/Configuracion/Index.jsx`, `Pages/Welcome.jsx`, `Pages/{Ingresos,Salidas,Ventas}/Show.jsx` (vacíos), `Pages/Kardex/Producto.jsx` (vacío), `Components/{Checkbox,Dropdown,NavLink,ResponsiveNavLink,FormField}.jsx`. |
| CSS heredado eliminado | Clases `.shell`, `.panel`, `.pos-grid`, `.product-card`, `.sale-*` y `.dashboard-*`, que ya no usaba ninguna pantalla viva. Se conserva el bloque de impresión `.sunat-printable`. |

En el resto de módulos se aplicó una normalización que solo toca cadenas de clases (scripts de una sola ejecución, no versionados): radios, pesos y tamaños de letra, mayúsculas decorativas, sombras y desenfoques, botones de colores fijos → marca, foco de los controles y espaciado de tablas. No se modificaron handlers, cálculos ni contenido de datos.

## 6. Cambio en el backend

`DatosGeneralesController@update` ahora también invalida `empresa_config_principal`. Es una sola línea: sin ella, el login mostraba la identidad anterior hasta que expiraba la caché.

## 7. Restricciones respetadas

Sin cambios en reglas de negocio, cálculos de venta, impuestos, inventario ni pagos (la lógica del POS se movió sin modificar), en rutas, contratos, permisos, autenticación, SUNAT, impresión (`SunatReceipt.jsx` intacto) ni dependencias. No se agregaron librerías.

## 8. Validación

| Comprobación | Resultado |
| --- | --- |
| `npm run build` (Vite) | Correcto tras cada fase. |
| `php artisan test` | 21 pruebas, 90 aserciones, todas en verde. |
| TypeScript y ESLint | No configurados en el proyecto; no se ejecutaron. |
| Renderizado de pantallas | Las 19 páginas modificadas se montaron con datos simulados, fuera del repositorio y sin tocar la base de datos, sin errores en ejecución. |
| POS | Se probó agregar productos, cantidades y totales (2 × 24,50 + 4,20 = 53,20) y el diálogo de cobro con efectivo recibido precargado. No se confirmó ninguna venta. |
| Catálogos | Se probó apertura del diálogo, validación en línea y sugerencias. |
| Color de marca | Se probó con petróleo, borgoña, índigo y amarillo; con amarillo el texto del botón pasa a oscuro y la navegación activa se oscurece. |
| Responsive | Revisado a 375 px (menú lateral móvil, tabla con desplazamiento horizontal) y a 1366 px. |

No se probó contra la base de datos real: está configurada en un servidor remoto y no se inició sesión ni se enviaron formularios.

## 9. Fase 2: división de módulos grandes

| Módulo | Antes | Después | Cambios |
| --- | --- | --- | --- |
| `Productos/Index` | 1.379 líneas | 109 líneas | La lógica del asistente pasó sin cambios a `Components/Productos/useFormularioProducto.js`. Asistente en `asistente/AsistenteProducto`, con `PasoIdentificacion`, `PasoAlmacen` y `PasoPrecios` (incluye `FilaEmpaque`). Además: `TablaProductos`, `DialogoAltaRapida` (anidado en el asistente para que el foco funcione en ambos) y `SelectorBusqueda`. Los éxitos se notifican con toast; las validaciones siguen en diálogo. |
| `Clientes/Index` | 949 líneas | 789 líneas | Formulario, carga, confirmación y aviso migrados a `Dialog`, `ConfirmDialog` y `NoticeDialog`. Teléfono con el componente compartido. |
| `Proveedores/Index` | 849 líneas | 689 líneas | Igual que Clientes. |

Componente nuevo `Components/Contactos/CampoTelefono.jsx` (y `limpiarTelefono`): reemplaza dos copias idénticas en Clientes y Proveedores. Depura el número igual que antes; se comprobó que `064-24ab1122xx99` da `064-2411229` en ambos módulos.

Errores corregidos:

- `Kardex/Index.jsx` usaba `AlertCircle` sin importarlo, ya desde `HEAD`, así que el aviso fallaba al mostrarse. Se agregó el import.
- 34 etiquetas de formulario en `text-slate-400` pasaron a `text-slate-600` para alcanzar el contraste mínimo.

Validación de esta fase:

- `npm run build` correcto y `php artisan test` con 21 pruebas en verde.
- Un script, fuera del repositorio, revisó todos los archivos en busca de componentes JSX o funciones usados sin importar ni declarar. Solo quedó un falso positivo: un parámetro llamado `Icon`.
- Productos: se recorrió el asistente completo con validaciones, selección de categoría, alta rápida anidada, el paso 3 con cálculo de margen (32,2 %) y un empaque «Docena» sugerido a S/ 142,80 con S/ 34,80 de ganancia. No se registró nada.
- Clientes y Proveedores: se probaron el diálogo de alta, el formato de teléfono y la confirmación, sin errores en ejecución.

## 10. Fase 3: ventanas, confirmaciones y avisos unificados

- **24 ventanas hechas a mano migradas a `Dialog`:** Usuarios (4), Roles (4), Ingresos/Create (3), Salidas/Create (4), Kardex (4), Productos/PresentacionesModal (2), y los detalles de Ingresos, Salidas y Ventas (1 cada uno). La migración (script de una sola ejecución, no versionado) solo cambia el contenedor: el contenido interior no cambia. Escape y el clic fuera usan el mismo manejador de cierre que ya tenía cada ventana; las de carga y las de éxito que redirigen no se pueden descartar.
- **Error encontrado y corregido en la propia migración:** el patrón original `cond && (...)` evitaba evaluar el contenido de una ventana cerrada. Al pasar a `<Dialog open>`, el contenido se evaluaba con `null` (por ejemplo, `usuarioAEliminar.nombres`) y la página quedaba en blanco. Se restauró la condición dentro de cada diálogo. La compilación no lo detectaba; se encontró al renderizar.
- **`AperturaCajaModal` migrado a `Dialog`.** `Modal.jsx` (Breeze) es ahora un adaptador de `Dialog`, usado por el tablero, el perfil y el modal de empaques.
- **SweetAlert2 eliminado:** Ventas, Caja y `DocumentLookupError` usan ahora `confirmar()` (`Components/ui/Confirmacion.jsx`, una confirmación basada en promesas que se monta una vez en el layout) y `toast`. Se desinstaló la dependencia `sweetalert2`. En la vista previa, el cierre de caja muestra esperado S/ 450,50, contado S/ 440,50 y diferencia −S/ 10,00 antes de confirmar.
- **Sin migrar a propósito:** `Ventas/VentaRegistradaModal` contiene el ticket imprimible. Su CSS de impresión posiciona `.sunat-printable` respecto al contenedor fijo, y moverlo a un panel con desplazamiento podría recortar tickets largos.
- **Nota de verificación:** con el panel del navegador de pruebas oculto, `requestAnimationFrame` no corre y las transiciones de Headless UI se quedan a medias. Las aperturas y cierres se confirmaron con el panel visible (capturas de pantalla, Escape real en Roles y Marcas).

## 11. Pendientes y limitaciones

- **Archivos grandes sin dividir en componentes** (ya con estilo y diálogos unificados): `Usuarios/Index` (1.040 líneas), `Ingresos/Create` (996), `Kardex/Index` (668), `Productos/PresentacionesModal` (662), `Usuarios/Roles` (616), `Salidas/Create` (559). El contenido interior de sus diálogos conserva su marcado original.
- `Profile/*` y las páginas de autenticación de Breeze, salvo el login, siguen con textos en inglés.
- `PanelPagos.jsx` y `VentaRegistradaModal.jsx` solo recibieron la normalización de clases.
- `Components/Panel/AsistenteOperativo.jsx` (asistente desactivado por configuración) mantiene su panel propio.
- Falta una prueba manual con sesión en un entorno de pruebas: alta y edición en cada catálogo, una venta completa con emisión y el guardado del color en Datos Generales.

## 12. Fase 4: experiencia de uso (interacción, navegación y POS)

Se perfeccionó el diseño existente, sin rediseñarlo. Todo usa los tokens de marca de Datos Generales.

**Color e íconos**
- `IconoTile` (nuevo): ícono sobre fondo tonal (marca, éxito, advertencia, peligro, información). Lo usan `PageHeader`, `Stat`, `CardHeader` y `EmptyState`.
- `PageHeader` muestra automáticamente el ícono del módulo según la URL (`iconoDeRuta` en `modules.js`), así que todas las páginas lo tienen sin editarlas una por una.
- Barra lateral: el elemento activo va en color de marca sólido, los íconos tienen más contraste y los grupos llevan rótulos en versalitas.
- Tablero: los indicadores usan `Stat` con íconos tonales, el resumen del período muestra íconos de tendencia y el selector de período resalta la opción activa.

**Tablas**
- `useTablaLocal` (`resources/js/hooks/`): ordenación con intercalación en español y paginación en el cliente para listados que el servidor ya entrega completos. `ThOrdenable` usa `aria-sort` y `Paginacion` anuncia la página actual.
- Aplicado a los 7 catálogos (columnas con `campo`) y a Productos (30 por página).
- La búsqueda y los filtros se conservan al volver con el navegador (`useRemember` de Inertia) en catálogos y Productos.

**Navegación**
- Paleta de comandos `Ctrl+K` / `⌘K` (`Layouts/navegacion/PaletaComandos.jsx`), también abrible desde el botón «Ir a un módulo…». Lista solo los módulos permitidos para el rol, ignora tildes al filtrar, se navega con flechas y Enter, y tiene roles ARIA de combobox y listbox.
- Entrada breve del contenido en cada página (`animate-entrada`, 180 ms), anulada con `prefers-reduced-motion`.
- Barra de progreso de Inertia con el color de marca, 200 ms de retardo y sin spinner.
- Aviso de «Sin conexión» (`useConexion`) en todas las pantallas autenticadas.

**POS**
- Atajos (`useAtajoTeclado`): `F2` enfoca la búsqueda y `F9` cobra. Se desactivan mientras hay un diálogo abierto y se muestran en pantalla.
- Enter en la búsqueda agrega el producto si el código de barras coincide exactamente o si queda un único resultado (flujo del lector); Escape limpia la búsqueda.
- Bloqueo de doble envío (`enviandoVentaRef`) al registrar la venta.
- Si el servidor responde sin `success`, ahora se cierra el indicador «Emitiendo» y se muestra un aviso. Antes quedaba abierto indefinidamente.
- Skeleton del catálogo mientras llegan los productos diferidos, y entrada animada de cada línea nueva del carrito.
- No se cambiaron cálculos, emisión, inventario, caja ni impresión.

**Verificación**
- `npm run build` correcto (bundle principal de 415 kB, 134 kB comprimido; las páginas se cargan bajo demanda) y `php artisan test` con 21 pruebas en verde.
- 25 de 26 páginas renderizan sin errores con datos simulados. `Operations/Index` falla solo en la simulación, porque no se le pasa `filters`, que el backend siempre envía.
- POS: el código `7751271001111` + Enter agregó «Leche Gloria» (S/ 4,20) y `F9` abrió el cobro.
- Ordenación: Marcas ordena de forma ascendente y descendente respetando la «É».
- Paleta: el filtro y la navegación con flechas funcionan.
- En el navegador de pruebas, `Ctrl+K` lo captura el propio panel; en la página el atajo funciona (comprobado con el evento) y el botón de la barra superior es la alternativa.

**Pendientes**
- Paginación y filtros del lado del servidor para Ventas, Kardex e Ingresos si sus volúmenes crecen (hoy el backend envía los listados completos).
- Ordenación en las tablas de Clientes, Proveedores, Usuarios, Ventas e Ingresos, que conservan su marcado de tabla propio.

## 13. Fase 5: refinamiento premium (solo frontend)

Se trató como refinamiento del sistema visual vigente, no como reemplazo. Se conserva la identidad y los colores de Datos Generales. La skill `impeccable` no encontró `PRODUCT.md` ni `DESIGN.md` y tomó el código actual como autoridad visual. Su detector mecánico no reporta problemas en los archivos rediseñados. No se modificó ningún archivo de backend.

**Inventario de autenticación (rutas reales):** solo existen `GET/POST /login` y `POST /logout`. `ForgotPassword`, `ResetPassword`, `ConfirmPassword`, `VerifyEmail`, `Register` y `Profile/Edit` tienen controlador pero ninguna ruta, así que no son accesibles. No se rediseñaron ni se enlazaron, para no ofrecer acciones que el backend no atiende.

**Cambios**
- **Login:** composición en dos columnas. El panel de marca muestra solo datos reales de Datos Generales (logo, nombre comercial, título y mensaje de acceso, razón social) y la inicial de la empresa como forma tipográfica. El formulario incluye:
  - aviso general del error de credenciales;
  - detección de Bloq Mayús;
  - mostrar y ocultar contraseña con `aria-pressed`;
  - estado «Verificando…» y bloqueo de doble envío.

  En móvil queda en una sola columna con cabecera de marca, sin desbordamiento (comprobado a 375 px).
- **Cabecera:** menú de cuenta (`MenuUsuario`, Headless UI `Menu`) con nombre, rol, correo y cerrar sesión. Es la única acción de cuenta con ruta. La barra lateral deja abajo solo «Contraer menú».
- **Diálogos:**
  - entrada y salida con escala y desplazamiento, sombra de elevación con desplazamiento real y radio de 16 px;
  - `ConfirmDialog` no se puede cerrar mientras `loading` es verdadero, admite `error` para mostrar el fallo real dentro del diálogo y diferencia el ícono de acción destructiva;
  - en acciones destructivas, el foco inicial no cae sobre el botón peligroso.
- **Interruptores:** `CheckboxField` pasa a ser un interruptor accesible (Headless UI `Switch`, `role="switch"`, etiqueta y descripción asociadas) con la misma API (`checked`, `onChange(boolean)`). Se aplica en todos los formularios que lo usan.
- **Superficies del navegador:** barra de desplazamiento fina con neutros del sistema, `caret-color` y `accent-color` de marca, `text-underline-offset` y `text-wrap: balance` en títulos.
- **Tokens de elevación:** `--shadow-elevado` y `--shadow-flotante`.

**Verificación:** build correcto, 21 pruebas en verde, verificación de imports sin problemas (salvo el falso positivo conocido). Revisión visual en el navegador de pruebas:
- login a 1280 px con marca borgoña y a 375 px;
- menú de cuenta abierto;
- diálogo de alta con interruptor (`aria-checked` cambia de `true` a `false`);
- confirmación destructiva.

**Pendientes y limitaciones**
- Tipografía: se mantiene la fuente del sistema (Segoe UI Variable / system-ui). Una fuente propia autoalojada daría más identidad, pero agrega una dependencia y peso de descarga en el POS; queda como decisión de producto.
- Pantallas de autenticación sin ruta: si se habilitan en el backend, conviene rediseñarlas con el mismo panel de marca del login.
- El contenido interior de los diálogos heredados (Usuarios, Roles, Ingresos/Create, Salidas/Create, Kardex) conserva su marcado original, ya normalizado en clases. Pasarlos a `Field`/`Input`/`CheckboxField` es el siguiente paso de consistencia.
- No hay modo oscuro en el proyecto; los tokens lo permiten, pero no se agregó.

## 14. Login neutro de producto (SaaS) y manejo del límite de intentos

- **El acceso pertenece al producto, no a una empresa cliente.** Ya no muestra logo, nombre comercial, razón social, textos ni el color de Datos Generales. Datos Generales sigue gobernando el color dentro de la aplicación tras iniciar sesión.
- **Identidad de producto:** `resources/js/Utils/producto.js` define `NOMBRE_PRODUCTO` (toma `VITE_APP_NAME` del `.env` salvo que sea «Laravel»; si no, «SVAMM») y `DESCRIPCION_PRODUCTO`. `app.jsx` usa el mismo nombre en los títulos de pestaña; antes añadía «— Laravel».
- **Diseño:**
  - panel oscuro con marca geométrica propia (SVG de tres barras), titular y los cuatro módulos reales del sistema (POS, inventario y kardex, comprobantes SUNAT, caja), sin cifras ni testimonios;
  - formulario con campos de 48 px y botón con elevación;
  - paleta índigo fija del producto, aplicada redefiniendo los tokens `--brand-*` solo dentro de esta pantalla.
- **HTTP 429 (`throttle:5,1` en `POST /login`):** se intercepta con `onHttpException` de Inertia en lugar de mostrar la página cruda. Aparece un aviso con cuenta regresiva, que usa `Retry-After` o 60 s si no viene, y el botón se bloquea mientras dura. El backend no cambia.
- **Verificado en el navegador de pruebas:**
  - a 1280 px, con una empresa borgoña configurada, el login se mantiene en la paleta del producto;
  - con un 429 simulado (`Retry-After: 42`) no aparece la ventana cruda, el aviso muestra la cuenta regresiva y el botón queda deshabilitado;
  - a 375 px el panel se oculta y no hay desbordamiento.
- **Recomendación:** fijar `APP_NAME` y `VITE_APP_NAME` en `.env` con el nombre comercial del producto y recompilar.

## 15. Login centrado con la identidad de Datos Generales

A pedido del usuario se reemplaza el login neutro de la sección 14. El acceso vuelve a usar Datos Generales, ahora en una composición centrada:

- **Contenido:** logo de la empresa (o un ícono sobre el color de marca si no hay logo), nombre comercial en el color de marca, `texto_login_titulo` y `texto_login_descripcion` (con los mismos valores por defecto del backend) y, al pie, el nombre comercial y la razón social.
- **Composición:** tarjeta blanca centrada con elevación (`--shadow-flotante`), campos de 48 px y botón con sombra del color de marca. De fondo, un velo del tono suave de la marca y dos anillos concéntricos finos, ambos derivados del color configurado.
- **Se conserva:** detección de Bloq Mayús, mostrar u ocultar la contraseña, bloqueo de doble envío y el manejo del HTTP 429 con cuenta regresiva.
- `NOMBRE_PRODUCTO` (`Utils/producto.js`) queda solo como respaldo cuando no hay nombre comercial, y para el título de las pestañas.
- **Verificado en el navegador de pruebas:**
  - a 1280 px con una empresa borgoña, todo se tiñe con su color;
  - el 429 simulado muestra la cuenta regresiva y bloquea el botón sin la ventana cruda;
  - a 375 px la tarjeta queda con 16 px de margen lateral, sin desbordamiento.

## 16. Login minimalista (versión vigente)

A pedido del usuario («minimalista moderno, como de aplicaciones modernas») se reemplazan las variantes de las secciones 14 y 15.

- **Composición:** fondo blanco con un único resplandor radial muy tenue del color de marca en la parte superior, y una columna centrada de 22 rem con:
  - logo de Datos Generales (o un ícono sobre el color de marca);
  - título «Inicia sesión en {nombre comercial}», con el nombre en el color de marca;
  - `texto_login_descripcion`;
  - campos de 44 px sin íconos y botón «Continuar».

  Al pie, el nombre comercial y la razón social.
- **Color:** el de marca se reserva para el logo, el nombre, el foco de los campos y el botón.
- **Se conserva:** mostrar u ocultar la contraseña, aviso de Bloq Mayús, errores junto al campo, bloqueo de doble envío y el manejo del HTTP 429 con cuenta regresiva.
- **Verificado en el navegador de pruebas:**
  - a 1280 px con marca borgoña;
  - el 429 simulado muestra el aviso «Espera 40 s» con el botón deshabilitado;
  - a 375 px el formulario queda con 24 px de margen, sin desbordamiento.

## 17. Login con fondo de marca, formas 3D y vidrio (versión vigente)

Basado en la referencia visual que entregó el usuario, adaptada a Datos Generales. Reemplaza la sección 16.

- **Fondo:** degradado radial del color de marca (más claro al centro y más oscuro en los bordes), con un panel de vidrio amplio detrás de la tarjeta como plano intermedio.
- **Formas 3D** (`Components/Acceso/FormasFlotantes.jsx`): anillos, zigzag, lazo, ondas y una ola desenfocada, dibujados como SVG vectorial (tubos con degradado, brillo y sombra; sin imágenes). Se tiñen con el color de marca mediante `color-mix`, flotan con la animación lenta `flotar` (desactivada con `prefers-reduced-motion`) y en móvil solo se muestran las principales.
- **Tarjeta de vidrio esmerilado** con logo y nombre comercial, `texto_login_titulo` y `texto_login_descripcion`, campos blancos y botón en un tono oscuro de la marca. Al pie, el nombre comercial y la razón social. El texto usa `--brand-on`, así que es legible incluso con marcas claras: con amarillo pasa a oscuro.
- **Omitido a propósito** respecto de la referencia: «¿Olvidaste tu contraseña?», el registro y el acceso con Google, GitHub o Facebook, porque esas rutas no existen en el backend.
- **Se conserva:** mostrar u ocultar la contraseña, aviso de Bloq Mayús, errores junto al campo, bloqueo de doble envío y el manejo del HTTP 429.
- **Verificado en el navegador de pruebas:**
  - con marcas azul, amarilla y borgoña;
  - el 429 simulado muestra «Espera 40 s» y deshabilita el botón;
  - a 375 px no hay desbordamiento.

## 18. Estilo de tablero de referencia y material 3D en todos los módulos

Basado en la referencia de tablero entregada por el usuario («EzMart»), con los colores de Datos Generales y solo datos reales.

**Sistema visual (todos los módulos)**
- Lienzo `#f5f6f8`.
- Tarjetas blancas con radio de 16 px, contorno `ring-slate-900/5` y `--shadow-tarjeta` en lugar del borde gris. Se aplicó en `Card`, `Stat` y, por script (solo clases), en 57 contenedores de 21 archivos y en las superficies del POS. `VentaRegistradaModal` quedó fuera por contener el ticket imprimible.
- Cabecera sobre el lienzo, sin borde, con título de sección grande y buscador en píldora.
- Menú lateral con elementos de 40 px y la opción activa en píldora del color de marca.
- Tintes de la marca para series de gráficos: `--brand-serie-1` a `--brand-serie-5`.

**Material 3D sin costo de render continuo**
- `.volumen-marca` (botones primarios y recuadros de íconos sólidos) y `.volumen-suave` (recuadros de ícono tonales): degradado de luz, brillo interior y sombra del color de marca con desplazamiento.
- `.elevable`: las tarjetas de indicadores suben 2 px al pasar el cursor; solo usan `transform` y `box-shadow`.
- `FormaTubo` (`Components/ui`): tubo vectorial con volumen, reutilizado en el fondo del login y como acento estático en la tarjeta destacada del tablero. No hay librerías 3D, WebGL ni animaciones constantes fuera del login.

**Tablero (solo datos reales del backend)**
- Cuatro indicadores. El primero es el destacado (ventas, con la variación real `insights.salesVariation`); los demás son ticket promedio con clientes atendidos, compras e ingresos, y productos sin existencias.
- Curva de ingresos (`GraficoVentas`, SVG propio): datos de `metrics.hourlySales`, línea suave, área con degradado, guías con máximo redondo, franja y etiqueta al pasar el cursor, y resumen accesible.
- Donut de cobros por método (`CobrosPorMetodo`), con `metrics.paymentMethods`, total al centro y leyenda con porcentajes.
- Fila inferior: últimas ventas (con insignia de documentos SUNAT por revisar), atención de inventario y turno de caja (con lotes por vencer).
- **No se agregaron** las secciones de la referencia sin datos en el backend: meta mensual, tasa de conversión, fuentes de tráfico, usuarios activos por país y notificaciones.

**Validación**
- `npm run build` correcto (bundle principal de 415 kB, 134 kB comprimido; sin dependencias nuevas).
- `php artisan test`: 21 pruebas en verde.
- Detector de `impeccable` sin hallazgos y verificación de imports limpia.
- Ningún archivo de backend modificado.
- 26 de 26 pantallas renderizan sin errores en la vista previa con datos simulados (los de la prueba, no los de la app).
- Revisión visual del tablero con marca naranja (etiqueta de la curva «16:00 · S/ 980,00»), de Caja y de Productos.

**Pendientes**
- Caja conserva su encabezado propio y la tarjeta oscura de «Efectivo esperado»; conviene alinearlos con `PageHeader`/`Stat`.
- La vista previa de acceso en Datos Generales (`VistaPreviaLogin`) aún muestra el diseño anterior del login.

## 19. Módulos al estilo de referencia, paginación real y organización de archivos

**Problema funcional previo corregido (solo frontend):** Ventas, Ingresos y Salidas reciben un paginador de Laravel (`->paginate(15)`), pero no mostraban controles de paginación. Solo se veían los 15 registros más recientes.
- **Paginación:** nuevo `Components/ui/PaginacionServidor.jsx`. Usa los enlaces del propio paginador (`links`, `prev_page_url`, `next_page_url`) con `preserveScroll` y `preserveState`, muestra «desde–hasta de total» y marca la página actual con `aria-current`. No carga más datos de los que envía el servidor.
- **Métricas honestas:** Ingresos y Salidas sumaban montos y motivos solo de la página visible y los presentaban como totales. Ahora usan `Stat`, con el total global tomado de `paginador.total` y las sumas rotuladas «en esta página».

**Caja alineada al sistema:** `PageHeader` (con ícono del módulo y estado del turno), indicadores con `Stat` («Efectivo esperado» destacado en lugar de la tarjeta oscura) y botones del sistema.

**Acabado de la referencia:**
- cabecera de tablas blanca con texto gris y filas con resaltado del tono de marca (componente `Table` y 8 páginas con tablas propias);
- celdas más aireadas;
- píldora activa del menú con `volumen-marca`.

**Organización de archivos** (imports actualizados y compilación verificada):

| Antes | Después |
| --- | --- |
| `Components/Panel/*` | `Components/Tablero/*` |
| `Components/AperturaCajaModal.jsx` | `Components/Caja/DialogoAperturaCaja.jsx` |
| `Components/DocumentLookupError.js` (`showDocumentLookupError`) | `Utils/consultaDocumento.js` (`avisarErrorConsultaDocumento`) |
| `Components/{ApplicationLogo,DangerButton,PrimaryButton,SecondaryButton,TextInput,InputLabel,InputError}.jsx` | `Components/Breeze/*`: legado usado solo por las pantallas de autenticación y perfil sin ruta |

Estructura resultante de `Components/`: `ui` (sistema de diseño), `Acceso`, `Caja`, `Catalogo`, `Contactos`, `Productos`, `Tablero` y `Ventas` (con `pos`), más `Breeze` (legado), `Modal.jsx` (adaptador) y `SunatReceipt.jsx`, que no se movió por contener el formato de impresión.

**Vista previa del acceso** en Datos Generales: ahora reproduce el login vigente (fondo de marca, formas 3D y tarjeta de vidrio).

**Validación:**
- build correcto (415 kB, 134 kB comprimido) y 21 pruebas en verde;
- detector de `impeccable` sin hallazgos;
- verificación de imports limpia; ahora también revisa los íconos pasados como prop (`icon={X}`);
- ningún archivo de backend modificado;
- 26 de 26 pantallas renderizan;
- paginación verificada con un paginador simulado: «1–15 de 42 comprobantes» y enlaces `?page=N`;
- revisión visual de Caja.

## 20. POS, Kardex y módulos restantes al estilo de referencia

- **POS:**
  - tarjetas de producto con la inicial del nombre en un recuadro con volumen (el catálogo no tiene imágenes), contorno sutil, elevación al pasar el cursor y botón «+» en 3D;
  - catálogo en 3 columnas (4 solo en pantallas muy anchas) para que los nombres no se corten;
  - «Total a cobrar» en un panel del tono de marca;
  - sin cambios en cálculos, cobro ni emisión.
- **Kardex:** cuatro `Stat` calculados sobre la lista completa que envía el backend (`->get()`):
  - productos;
  - inventario valorizado (stock × costo);
  - con saldo / sin existencias;
  - lotes activos, rotulado «se listan hasta 30 por producto» porque el backend limita la carga con `take(30)`.
- **Ficha de producto (`Productos/Show`):** `PageHeader` con migas enlazadas y acciones «Volver» y «Registrar entrada»; el degradado azul fijo pasa al tono de marca y se quita el relleno duplicado.
- **Impresoras:** se quitan la mancha difuminada decorativa y el rótulo sobre el título; el título lleva el recuadro de ícono del sistema. El contenido funcional no cambia.
- **Acabado común** (script de una sola ejecución que solo cambia clases; 48 ajustes en 18 archivos; excluye el ticket imprimible):
  - botones y enlaces heredados en color de marca plano → `volumen-marca`;
  - tarjetas de producto seleccionables con resaltado del tono de marca;
  - buscadores con la tipografía del sistema.
- **Validación:**
  - build correcto, 21 pruebas en verde y ningún archivo de backend modificado;
  - detector de `impeccable` sobre todo `Pages` y `Components`: 0 hallazgos;
  - 27 de 27 pantallas renderizan con datos simulados, incluida `Productos/Show`, verificada por primera vez;
  - revisión visual del POS y de Kardex (inventario valorizado S/ 792,00 = 40 × S/ 19,80).


## 21. Impresoras y terminales, entradas y ajustes de inventario

- **Impresoras y terminales (`Configuracion/Impresoras`):** la página única de ~400 líneas se divide en `Components/Impresoras/`:
  - `estado.jsx` contiene `EstadoConexion` (badge semántico), `estadoAgente` (misma regla de 70 s) y `fechaHora`;
  - `PanelAgente.jsx` contiene `PanelAgente` (pasos y descarga con versión y SHA-256 del servidor) y `CodigoVinculacion` (código flash con «Copiar» y aviso por toast);
  - `Listados.jsx` contiene `ListaImpresoras` (diagnosticar, prueba, editar, errores reales) y `ListaTerminales` (vincular y revocar);
  - `FormularioImpresora.jsx` y `FormularioTerminal.jsx` son diálogos montados con `key` por registro, de modo que el estado inicial sale del registro sin `useEffect` de sincronización.
- La página usa `PageHeader` y cuatro `Stat` con conteos reales: impresoras, conectadas, terminales y agentes vinculados o en línea.
- `window.confirm` se reemplaza por `confirmar()` al revocar un agente.
- Endpoints, forma de los datos y validaciones no cambian.
- **Entradas (`Ingresos/Create`) y ajustes (`Salidas/Create`):**
  - las tarjetas de producto pasan a ser `<button>` accesibles por teclado (antes eran `div` con `onClick`), con `elevable` y la inicial en `volumen-suave`;
  - los vacíos usan `EmptyState`, el retorno al historial usa `buttonClasses` y los botones de quitar usan `IconButton` con etiqueta;
  - el envío y la confirmación usan `Button`; la selección de motivo y de % de utilidad usan el tono de marca;
  - los chips de utilidad suben de 7,5 px a 11 px;
  - los emojis de los avisos se cambian por íconos.
- **Validación:** build correcto, 21 pruebas en verde, detector sin hallazgos y verificación visual en el arnés (formularios precargados, alta de líneas en ambos carritos).
