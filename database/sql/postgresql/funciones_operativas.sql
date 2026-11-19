-- Funciones opcionales para Supabase/PostgreSQL.
-- Ejecutar mediante una migración aprobada por el equipo o en el SQL Editor de Supabase.
-- No se ejecuta automáticamente durante php artisan migrate.

CREATE OR REPLACE FUNCTION operaciones.reservar_correlativo(
    p_id_sucursal BIGINT,
    p_codigo_sunat VARCHAR(5)
)
RETURNS TABLE (serie VARCHAR(4), correlativo BIGINT, igv_porcentaje NUMERIC)
LANGUAGE plpgsql
SECURITY INVOKER
AS $$
DECLARE
    v_configuracion operaciones.datos_gen%ROWTYPE;
    v_correlativo BIGINT;
BEGIN
    SELECT *
      INTO v_configuracion
      FROM operaciones.datos_gen
     WHERE id_sucursal = p_id_sucursal
     FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'La sucursal % no tiene configuración de comprobantes.', p_id_sucursal;
    END IF;

    IF p_codigo_sunat = '03' THEN
        IF v_configuracion.serie_boleta IS NULL OR v_configuracion.serie_boleta = '' THEN
            RAISE EXCEPTION 'La serie de boleta no está configurada.';
        END IF;
        v_correlativo := v_configuracion.ult_nro_boleta + 1;
        UPDATE operaciones.datos_gen
           SET ult_nro_boleta = v_correlativo
         WHERE id_datos_gen = v_configuracion.id_datos_gen;
        RETURN QUERY SELECT v_configuracion.serie_boleta, v_correlativo, v_configuracion.igv_porcentaje;
    ELSIF p_codigo_sunat = '01' THEN
        IF v_configuracion.serie_factura IS NULL OR v_configuracion.serie_factura = '' THEN
            RAISE EXCEPTION 'La serie de factura no está configurada.';
        END IF;
        v_correlativo := v_configuracion.ult_nro_factura + 1;
        UPDATE operaciones.datos_gen
           SET ult_nro_factura = v_correlativo
         WHERE id_datos_gen = v_configuracion.id_datos_gen;
        RETURN QUERY SELECT v_configuracion.serie_factura, v_correlativo, v_configuracion.igv_porcentaje;
    ELSE
        RAISE EXCEPTION 'Tipo SUNAT % no soportado.', p_codigo_sunat;
    END IF;
END;
$$;

REVOKE ALL ON FUNCTION operaciones.reservar_correlativo(BIGINT, VARCHAR) FROM PUBLIC;
