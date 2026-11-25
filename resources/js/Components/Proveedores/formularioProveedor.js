import { PREFIJOS_RUC } from '../../Utils/documentos.js';

/** Datos vacíos del formulario (contrato de StoreProveedorRequest). */
export const PROVEEDOR_VACIO = { ruc: '', razon_social: '', direccion: '', telefono: '', email: '', motivo_cambio_documento: '', estado: true };

/** Tipo de teléfono según cómo quedó guardado el número. */
export function tipoTelefonoGuardado(telefono) {
    const tel = String(telefono ?? '').trim();
    if (!tel) return 'movil_pe';
    if (tel.startsWith('+') && !tel.startsWith('+51')) return 'internacional';
    if (tel.length <= 8 || tel.includes('-') || !tel.startsWith('9')) return 'fijo_pe';
    return 'movil_pe';
}

/**
 * Proveedor del listado → formulario. El listado entrega `num_documento` y `direccion_fiscal`
 * (ProveedorController@index); leer otra clave enviaría la dirección vacía y la borraría.
 */
export function formularioDesdeProveedor(proveedor) {
    return {
        ...PROVEEDOR_VACIO,
        ruc: String(proveedor.num_documento ?? proveedor.ruc ?? ''),
        razon_social: String(proveedor.razon_social ?? '').toUpperCase(),
        direccion: String(proveedor.direccion_fiscal ?? proveedor.direccion ?? '').toUpperCase(),
        telefono: String(proveedor.telefono ?? '').trim().replace('+51 ', ''),
        email: proveedor.email ?? '',
        estado: Boolean(proveedor.estado ?? true),
    };
}

/** Validación previa al envío; el servidor vuelve a validar. Devuelve { campo: mensaje }. */
export function validarProveedor(datos, tipoTelefono) {
    const errores = {};
    const ruc = String(datos.ruc ?? '').trim();
    if (ruc.length !== 11) errores.ruc = 'El RUC debe tener 11 dígitos.';
    else if (!PREFIJOS_RUC.includes(ruc.slice(0, 2))) errores.ruc = 'El RUC debe empezar con 10, 15, 16, 17 o 20.';

    if (String(datos.razon_social ?? '').trim().length < 3) errores.razon_social = 'Escribe la razón social (mínimo 3 caracteres).';

    const tel = String(datos.telefono ?? '').trim();
    if (tel && tipoTelefono === 'movil_pe' && (tel.length !== 9 || !tel.startsWith('9'))) {
        errores.telefono = 'El celular debe tener 9 dígitos y empezar con 9.';
    }
    if (tel && tipoTelefono === 'fijo_pe') {
        const digitos = tel.replace(/\D/g, '').length;
        if (digitos < 7 || digitos > 9) errores.telefono = 'El teléfono fijo debe tener entre 7 y 9 dígitos.';
    }
    return errores;
}
