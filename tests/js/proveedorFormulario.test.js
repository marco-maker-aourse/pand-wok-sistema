/**
 * Formulario de proveedores.  node --test tests/js
 * El listado entrega la dirección como `direccion_fiscal`; si el formulario la leyera de
 * otra clave, al editar se enviaría vacía y el servidor borraría la dirección guardada.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { formularioDesdeProveedor, tipoTelefonoGuardado, validarProveedor } from '../../resources/js/Components/Proveedores/formularioProveedor.js';

const proveedor = {
    id_proveedor: 7,
    num_documento: '20100055237',
    razon_social: 'Alicorp s.a.a.',
    direccion_fiscal: 'Av. Argentina 4793, Callao',
    telefono: '964112233',
    email: 'pedidos@alicorp.pe',
    estado: true,
};

test('editar conserva la dirección fiscal que llega del listado', () => {
    const datos = formularioDesdeProveedor(proveedor);
    assert.equal(datos.direccion, 'AV. ARGENTINA 4793, CALLAO');
    assert.equal(datos.ruc, '20100055237');
    assert.equal(datos.razon_social, 'ALICORP S.A.A.');
    assert.equal(datos.estado, true);
});

test('acepta también el formato antiguo con `direccion` y `ruc`', () => {
    const datos = formularioDesdeProveedor({ ruc: '20123456789', razon_social: 'X', direccion: 'Jr. Uno 1' });
    assert.equal(datos.ruc, '20123456789');
    assert.equal(datos.direccion, 'JR. UNO 1');
});

test('el tipo de teléfono se deduce del número guardado', () => {
    assert.equal(tipoTelefonoGuardado('964112233'), 'movil_pe');
    assert.equal(tipoTelefonoGuardado('01-445566'), 'fijo_pe');
    assert.equal(tipoTelefonoGuardado('+1 800 555-0199'), 'internacional');
    assert.equal(tipoTelefonoGuardado(''), 'movil_pe');
});

test('la validación devuelve errores por campo en lugar de bloquear con un aviso', () => {
    assert.deepEqual(validarProveedor({ ruc: '123', razon_social: 'AB', telefono: '' }, 'movil_pe'), {
        ruc: 'El RUC debe tener 11 dígitos.',
        razon_social: 'Escribe la razón social (mínimo 3 caracteres).',
    });
    assert.deepEqual(validarProveedor({ ruc: '30100055237', razon_social: 'ALICORP', telefono: '' }, 'movil_pe'), {
        ruc: 'El RUC debe empezar con 10, 15, 16, 17 o 20.',
    });
    assert.deepEqual(validarProveedor({ ruc: '20100055237', razon_social: 'ALICORP', telefono: '812345678' }, 'movil_pe'), {
        telefono: 'El celular debe tener 9 dígitos y empezar con 9.',
    });
    assert.deepEqual(validarProveedor({ ruc: '20100055237', razon_social: 'ALICORP', telefono: '' }, 'movil_pe'), {});
});
