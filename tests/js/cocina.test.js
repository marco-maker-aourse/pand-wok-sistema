/**
 * Tablero de cocina: urgencia por tiempo de espera y aviso de comandas nuevas.  node --test tests/js
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { comandasNuevas, minutosDesde, reloj, sonarCampana, urgencia, URGENCIA } from '../../resources/js/Components/Restaurante/cocina.js';

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

/** AudioContext mínimo: registra los tonos programados. */
function audioFalso(estado, { reanuda = true } = {}) {
    const tonos = [];
    const nodo = () => ({ connect: (otro) => otro ?? nodo(), gain: { setValueAtTime() {}, exponentialRampToValueAtTime() {} } });
    return {
        state: estado, currentTime: 0, destination: {}, tonos,
        async resume() { if (reanuda) this.state = 'running'; },
        createGain: nodo,
        createOscillator: () => ({ ...nodo(), type: '', frequency: { value: 0 }, start() { tonos.push(this.frequency.value); }, stop() {} }),
    };
}

test('el aviso suena aunque el navegador haya suspendido el audio (pantalla apagada, pestaña atrás)', async () => {
    const audio = audioFalso('suspended');
    assert.equal(await sonarCampana(audio, 1), true);
    assert.ok(audio.tonos.length >= 2, 'reanuda el audio y toca la campana');
});

test('si el navegador no deja reanudar el audio, avisa que hace falta un toque', async () => {
    const audio = audioFalso('suspended', { reanuda: false });
    assert.equal(await sonarCampana(audio, 1), false);
    assert.equal(audio.tonos.length, 0);
    assert.equal(await sonarCampana(null, 1), false, 'sin audio creado todavía');
});

test('varias comandas nuevas suenan con más insistencia, con un tope', async () => {
    const una = audioFalso('running');
    const muchas = audioFalso('running');
    await sonarCampana(una, 1);
    await sonarCampana(muchas, 9);
    assert.ok(muchas.tonos.length > una.tonos.length);
    assert.ok(muchas.tonos.length <= 3 * una.tonos.length);
});
