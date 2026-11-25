import { PREFIJOS_RUC } from '../../Utils/documentos.js';

export const CLIENTE_VACIO = {
    tipo_persona: 'NATURAL', tipo_documento: '', num_documento: '', nombre_razon_social: '',
    direccion_fiscal: '', telefono: '', email: '', motivo_cambio_documento: '', estado: true,
};

/** RUC que empieza en 20 = persona jurídica; cualquier otro documento = natural (StoreClienteRequest). */
export function tipoPersonaDe(cliente) {
    const codigo = String(cliente.tipo_documento?.codigo_sunat ?? cliente.tipo_documento ?? '');
    return codigo === '6' && String(cliente.num_documento ?? '').startsWith('20') ? 'JURIDICA' : 'NATURAL';
}

/** Cliente del listado (con su relación `tipo_documento`) → datos del formulario. */
export function formularioDesdeCliente(cliente) {
    return {
        ...CLIENTE_VACIO,
        tipo_persona: tipoPersonaDe(cliente),
        tipo_documento: String(cliente.tipo_documento?.codigo_sunat ?? '1'),
        num_documento: String(cliente.num_documento ?? ''),
        nombre_razon_social: String(cliente.nombre_razon_social ?? '').toUpperCase(),
        direccion_fiscal: String(cliente.direccion_fiscal ?? '').toUpperCase(),
        telefono: String(cliente.telefono ?? '').trim().replace('+51 ', ''),
        email: cliente.email ?? '',
        estado: Boolean(cliente.estado ?? true),
    };
}

/** Validación previa al envío; el servidor vuelve a validar. Devuelve { campo: mensaje }. */
export function validarCliente(datos, tipoDocumento, tipoTelefono) {
    const errores = {};
    const numero = String(datos.num_documento ?? '').trim();
    const codigo = String(tipoDocumento?.codigo_sunat ?? '');

    if (!numero) errores.num_documento = 'Escribe el número de documento.';
    else if (codigo === '1' && numero.length !== 8) errores.num_documento = 'El DNI debe tener 8 dígitos.';
    else if (codigo === '6' && numero.length !== 11) errores.num_documento = 'El RUC debe tener 11 dígitos.';
    else if (codigo === '6' && !PREFIJOS_RUC.includes(numero.slice(0, 2))) errores.num_documento = 'El RUC debe empezar con 10, 15, 16, 17 o 20.';
    else if (tipoDocumento?.longitud_exacta && !['1', '6'].includes(codigo) && numero.length !== Number(tipoDocumento.longitud_exacta)) {
        errores.num_documento = `El ${tipoDocumento.nombre} debe tener ${tipoDocumento.longitud_exacta} dígitos.`;
    }

    if (String(datos.nombre_razon_social ?? '').trim().length < 3) errores.nombre_razon_social = 'Escribe el nombre o la razón social (mínimo 3 caracteres).';

    const tel = String(datos.telefono ?? '').trim();
    if (tel && tipoTelefono === 'movil_pe' && (tel.length !== 9 || !tel.startsWith('9'))) errores.telefono = 'El celular debe tener 9 dígitos y empezar con 9.';
    if (tel && tipoTelefono === 'fijo_pe') {
        const digitos = tel.replace(/\D/g, '').length;
        if (digitos < 7 || digitos > 9) errores.telefono = 'El teléfono fijo debe tener entre 7 y 9 dígitos.';
    }
    return errores;
}
