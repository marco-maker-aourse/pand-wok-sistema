# Impresoras térmicas y terminales POS

> El agente de impresión es un proyecto independiente. Su código, instalador y documentación técnica se mantienen fuera de este repositorio; este documento resume únicamente la integración administrativa de Laravel.

## Límites del módulo

El servidor Laravel no puede detectar ni controlar de forma directa un USB, una cola local o una impresora dentro de la red privada de una caja. La conectividad y la impresión se delegan al **agente local vinculado**. Sin agente configurado, el sistema conserva el estado `desconocido`; nunca comunica una impresión exitosa de forma ficticia.

## Modelo de datos

- `catalogos.impresoras`: configuración y último diagnóstico resumido del equipo.
- `catalogos.terminales_pos`: equipo de caja, impresora predeterminada y política de copias.
- `operaciones.turnos_caja.id_terminal`: contexto físico opcional del turno.

No se agrega terminal a `operaciones.ventas`: cada venta ya pertenece a un turno. Las claves foráneas compuestas `(id_impresora, id_sucursal)` y `(id_terminal, id_sucursal)` impiden relacionar equipos de otra sucursal.

## Contrato vigente del agente

El agente inicia solicitudes HTTPS al SaaS mediante la API `/api/agente-impresion/v1`; Laravel no se conecta a una URL global ni acepta URL de conectores desde formularios. La vinculación se realiza con un código temporal y genera un token revocable exclusivo de una terminal. El contrato del agente se mantiene junto a su repositorio independiente. El diseño del ticket (compartido con la vista previa de Ventas) está en [COMPROBANTE_TERMICO.md](COMPROBANTE_TERMICO.md).

## Flujos

1. Un administrador registra impresora y terminal en **Configuración → Impresoras y terminales**.
2. Al abrir caja, el cajero puede elegir una terminal activa de su sucursal.
3. Luego de confirmar una venta, se crea un trabajo persistente solo si la terminal tiene `impresion_automatica` activa.
4. El agente vinculado reclama el trabajo, lo procesa localmente y registra su resultado.
5. Desde historial de ventas puede solicitarse reimpresión manual. Solo se crea otro trabajo: nunca se recrea una venta, pago ni comprobante SUNAT.
6. La última conexión e impresión quedan en la impresora como diagnóstico resumido; `operaciones.trabajos_impresion` conserva la auditoría de solicitudes.

## Verificación segura

1. Ejecutar migraciones únicamente en entorno local o staging autorizado.
2. Registrar una impresora y una terminal de la misma sucursal.
3. Abrir un turno seleccionando la terminal.
4. Probar diagnóstico sin agente: no debe marcar la impresora como conectada.
5. Con agente real, validar que el resultado actualice `ultima_impresion` y `ultimo_resultado_impresion`.
6. Simular fallo del agente y verificar que la venta ya confirmada no cambie de estado.
