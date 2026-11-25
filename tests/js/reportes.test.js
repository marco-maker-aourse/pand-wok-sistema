/** Reportes del restaurante: comparaciones, horas pico y atajos de período.  node --test tests/js */
import assert from 'node:assert/strict';
import test from 'node:test';
import { conParticipacion, diasDelPeriodo, horasDelServicio, periodoDeRango, rangoDePeriodo, variacion } from '../../resources/js/Components/Restaurante/reportes.js';

test('la variación se calcula contra el período anterior y no inventa una sin base', () => {
    assert.equal(variacion(120, 100), 20);
    assert.equal(variacion(75, 100), -25);
    assert.equal(variacion(100, 0), null, 'sin ventas antes no hay porcentaje');
    assert.equal(variacion(null, 100), null);
    assert.equal(variacion(1, 3), -66.7);
});

test('las horas pico llenan los huecos del servicio sin agregar horas fuera de él', () => {
    const horas = horasDelServicio([{ hora: 12, cobros: 3, total: 90 }, { hora: 14, cobros: 1, total: 20 }]);
    assert.deepEqual(horas.map((h) => h.hora), [12, 13, 14]);
    assert.equal(horas[1].total, 0);
    assert.deepEqual(horasDelServicio([]), []);
});

test('la serie diaria incluye días sin ventas, también al pasar de mes', () => {
    const venta = { dia: '2026-11-30', total: 40, cobros: 2 };
    assert.deepEqual(diasDelPeriodo([venta], '2026-11-29', '2026-12-01'), [
        { dia: '2026-11-29', total: 0, cobros: 0 },
        venta,
        { dia: '2026-12-01', total: 0, cobros: 0 },
    ]);
});

test('los atajos de período usan la fecha de Lima', () => {
    // 2026-11-26 02:00 UTC todavía es 25 de noviembre en Lima.
    const ahora = new Date('2026-11-26T02:00:00Z');
    assert.deepEqual(rangoDePeriodo('hoy', ahora), { desde: '2026-11-25', hasta: '2026-11-25' });
    assert.deepEqual(rangoDePeriodo('ayer', ahora), { desde: '2026-11-24', hasta: '2026-11-24' });
    assert.deepEqual(rangoDePeriodo('7dias', ahora), { desde: '2026-11-19', hasta: '2026-11-25' });
    assert.deepEqual(rangoDePeriodo('mes', ahora), { desde: '2026-11-01', hasta: '2026-11-25' });
    assert.equal(periodoDeRango('2026-11-19', '2026-11-25', ahora), '7dias');
    assert.equal(periodoDeRango('2026-11-02', '2026-11-25', ahora), null);
});

test('la participación suma 100 y tolera un total en cero', () => {
    const filas = conParticipacion([{ total: 30 }, { total: 10 }]);
    assert.deepEqual(filas.map((f) => f.participacion), [75, 25]);
    assert.deepEqual(conParticipacion([{ total: 0 }]).map((f) => f.participacion), [0]);
});
