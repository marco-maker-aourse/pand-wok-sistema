# Contrato API v1

Base local: `http://127.0.0.1:8000/api/v1`. Todas las respuestas usan JSON. La autenticación de `seguridad.usuarios` se conectará antes de liberar la API; por ahora esta capa sirve para integrar React durante el bootstrap.

## Productos

`GET /catalogos/productos?buscar=arroz&per_page=15` lista productos paginados. El filtro se evalúa sin distinción entre mayúsculas y minúsculas en PostgreSQL.

`POST /catalogos/productos` y `PUT /catalogos/productos/{id}` reciben:

```json
{
  "id_categoria": 1,
  "id_marca": 1,
  "id_unidad_base": 1,
  "codigo_barras": "7750000000012",
  "nombre": "Producto de ejemplo",
  "afecta_igv": true,
  "es_servicio": false,
  "usa_vencimiento": false,
  "estado": true
}
```

`DELETE /catalogos/productos/{id}` realiza una baja lógica (`estado=false`).

## Venta

`POST /ventas` recibe los identificadores de sucursal, cliente y usuario, el comprobante, detalles y pagos. Los precios se toman del servidor desde `catalogos.producto_presentaciones`.

```json
{
  "id_sucursal": 1,
  "id_cliente": 1,
  "id_usuario": 1,
  "tipo_comprobante": "BOLETA",
  "serie": "B001",
  "correlativo": 1,
  "descuento_global": 0,
  "detalles": [
    { "id_prod_pres": 1, "cantidad": 2, "lote": null }
  ],
  "pagos": [
    { "id_metodo": 1, "monto": 20, "nro_referencia": null }
  ]
}
```

Una respuesta `422` comunica campos inválidos, falta de stock, importe de pagos inconsistente o referencia requerida. Una respuesta `201` devuelve la venta con sus detalles y pagos.

## Comisión de pago

El backend usa el método configurado en `catalogos.metodos_pago`:

- `BILLETERA_DIRECTA`: comisión `0.00`; exige referencia si `requiere_referencia=true`.
- `PASARELA`: comisión = `monto × comision_porcentaje / 100 + comision_fija`.
- `EFECTIVO`: comisión `0.00`.

El resultado queda persistido en `operaciones.pagos_ventas.monto_neto` y `comision_pasarela`, por lo que los cierres de caja no recálculan condiciones históricas de Culqi.
