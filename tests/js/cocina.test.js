/**
 * Tablero de cocina: urgencia por tiempo de espera y aviso de comandas nuevas.  node --test tests/js
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { comandasNuevas, minutosDesde, reloj, urgencia, URGENCIA } from '../../resources/js/Components/Restaurante/cocina.js';

const comanda = (id, estado = 'PENDIENTE') => ({ id_comanda: id, estado });

test('la urgencia cambia a los 8 y a los 15 minutos', () => {
    assert.equal(urgencia(0), URGENCIA.A_TIEMPO);
    assert.equal(urgencia(7), URGENCIA.A_TIEMPO);
    assert.equal(urgencia(8), URGENCIA.EN_ESPERA);
    assert.equal(urgencia(14), URGENCIA.EN_ESPERA);
    assert.equal(urgencia(15), URGENCIA.ATRASADA);
});

test('el reloj nunca es negativo y pasa a horas después de 60 min', () => {
    const ahora = Date.parse('2026-11-22T23:00:00Z');
    assert.equal(minutosDesde('2026-11-22T23:05:00Z', ahora), 0, 'una hora futura no da minutos negativos');
    assert.equal(minutosDesde('2026-11-22T22:38:00Z', ahora), 22);
    assert.equal(reloj(22), '22 min');
    assert.equal(reloj(75), '1 h 15');
});

test('la primera carga no suena', () => {
    const { nuevas } = comandasNuevas(null, [comanda(1), comanda(2)], '1:');
    assert.deepEqual(nuevas, []);
});

test('suena solo por comandas por hacer que no estaban antes', () => {
    const { vistos } = comandasNuevas(null, [comanda(1)], '1:');
    const { nuevas } = comandasNuevas(vistos, [comanda(1), comanda(2), comanda(3, 'LISTA')], '1:');
    assert.deepEqual(nuevas, [2]);
});

test('cambiar de estación o de sucursal no cuenta como pedido nuevo', () => {
    const { vistos } = comandasNuevas(null, [comanda(1)], '1:');
    const { nuevas } = comandasNuevas(vistos, [comanda(5), comanda(6)], '1:2');
    assert.deepEqual(nuevas, []);
});
