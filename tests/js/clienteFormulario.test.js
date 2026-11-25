/**
 * Formulario de clientes.  node --test tests/js
 * El listado no trae `tipo_persona`: se deduce del documento. Si se asumiera NATURAL, editar
 * un cliente con RUC 20 fallaría (StoreClienteRequest exige JURIDICA).
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { formularioDesdeCliente, tipoPersonaDe, validarCliente } from '../../resources/js/Components/Clientes/formularioCliente.js';

const RUC = { codigo_sunat: '6', nombre: 'RUC', longitud_exacta: 11 };
const DNI = { codigo_sunat: '1', nombre: 'DNI', longitud_exacta: 8 };

test('una empresa con RUC 20 es persona jurídica', () => {
    assert.equal(tipoPersonaDe({ num_documento: '20100055237', tipo_documento: RUC }), 'JURIDICA');
    assert.equal(tipoPersonaDe({ num_documento: '10456789012', tipo_documento: RUC }), 'NATURAL');
    assert.equal(tipoPersonaDe({ num_documento: '45678901', tipo_documento: DNI }), 'NATURAL');
    assert.equal(tipoPersonaDe({ num_documento: '20100055237', tipo_documento: RUC, tipo_persona: 'NATURAL' }), 'JURIDICA');
});

test('editar una empresa envía JURIDICA y conserva sus datos', () => {
    const datos = formularioDesdeCliente({
        num_documento: '20100055237', nombre_razon_social: 'Alicorp s.a.a.', direccion_fiscal: 'Av. Argentina 4793',
        telefono: '964112233', email: 'a@b.pe', estado: true, tipo_documento: RUC,
    });
    assert.equal(datos.tipo_persona, 'JURIDICA');
    assert.equal(datos.tipo_documento, '6');
    assert.equal(datos.nombre_razon_social, 'ALICORP S.A.A.');
    assert.equal(datos.direccion_fiscal, 'AV. ARGENTINA 4793');
});

test('valida por campo según el tipo de documento', () => {
    assert.deepEqual(validarCliente({ num_documento: '1234', nombre_razon_social: 'JUAN PEREZ', telefono: '' }, DNI, 'movil_pe'), {
        num_documento: 'El DNI debe tener 8 dígitos.',
    });
    assert.deepEqual(validarCliente({ num_documento: '30100055237', nombre_razon_social: 'X', telefono: '' }, RUC, 'movil_pe'), {
        num_documento: 'El RUC debe empezar con 10, 15, 16, 17 o 20.',
        nombre_razon_social: 'Escribe el nombre o la razón social (mínimo 3 caracteres).',
    });
    assert.deepEqual(validarCliente({ num_documento: '20100055237', nombre_razon_social: 'ALICORP', telefono: '' }, RUC, 'movil_pe'), {});
});
