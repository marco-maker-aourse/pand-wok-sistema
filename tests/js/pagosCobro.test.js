/**
 * Pagos del cobro del restaurante (POS y dividir cuenta).  node --test tests/js
 * El servidor exige el efectivo recibido y la referencia de Yape/transferencia: la pantalla
 * debe enviarlos siempre o explicar qué falta, nunca dejar el botón muerto.
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { motivoPagos, pagosParaEnviar } from '../../resources/js/Components/Restaurante/pagos.js';

const metodos = [
    { id_metodo: 1, nombre: 'Efectivo', tipo: 'EFECTIVO', requiere_referencia: false },
    { id_metodo: 2, nombre: 'Yape', tipo: 'BILLETERA_DIRECTA', requiere_referencia: true },
];

test('efectivo sin monto recibido se envía como pago exacto', () => {
    const pagos = pagosParaEnviar([{ id_metodo: 1, monto: '', monto_recibido: '', nro_referencia: '' }], metodos, 15);
    assert.deepEqual(pagos, [{ id_metodo: 1, monto: '15.00', monto_recibido: '15.00', nro_referencia: null }]);
});

test('efectivo con billete mayor conserva lo recibido para calcular el vuelto', () => {
    const pagos = pagosParaEnviar([{ id_metodo: 1, monto: '', monto_recibido: '20', nro_referencia: '' }], metodos, 15);
    assert.equal(pagos[0].monto_recibido, '20.00');
});

test('los métodos con referencia exigen el número de operación y lo dicen', () => {
    const pagos = [{ id_metodo: 2, monto: '', monto_recibido: '', nro_referencia: '' }];
    assert.match(motivoPagos(pagos, metodos, 15), /número de operación de Yape/);
    assert.equal(motivoPagos([{ ...pagos[0], nro_referencia: '123456' }], metodos, 15), null);
});

test('efectivo recibido menor al importe se explica', () => {
    assert.match(motivoPagos([{ id_metodo: 1, monto: '', monto_recibido: '10', nro_referencia: '' }], metodos, 15), /recibido/);
});

test('cobro mixto: los montos deben cuadrar con el total', () => {
    const pagos = [
        { id_metodo: 1, monto: '10', monto_recibido: '', nro_referencia: '' },
        { id_metodo: 2, monto: '3', monto_recibido: '', nro_referencia: '987' },
    ];
    assert.match(motivoPagos(pagos, metodos, 15), /Falta asignar/);
    assert.equal(motivoPagos([pagos[0], { ...pagos[1], monto: '5' }], metodos, 15), null);
});
