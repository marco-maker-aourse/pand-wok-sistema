import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, Field, Input, Stat } from '@/Components/ui';
import { cantidad, soles } from '@/Components/Restaurante/formato';

const dinero = (valor) => valor == null ? '—' : soles(valor);
const numero = (valor) => valor == null ? '—' : cantidad(valor);
const formatearInsumo = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 4 }).format;

function Tabla({ titulo, filas, columnas }) {
    return (
        <Card className="min-w-0 p-4">
            <h2 className="mb-3 font-semibold">{titulo}</h2>
            {!filas.length ? <p className="text-sm text-slate-600">Sin operaciones en el período.</p> : (
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm" aria-label={titulo}>
                        <thead><tr>{columnas.map(([clave, nombre]) => <th key={clave} scope="col" className="whitespace-nowrap border-b py-2 pr-3 text-xs font-medium text-slate-600">{nombre}</th>)}</tr></thead>
                        <tbody>{filas.map((fila, i) => <tr key={i}>{columnas.map(([clave, , formato]) => <td key={clave} className="border-b py-2 pr-3 last:pr-0">{formato ? formato(fila[clave]) : fila[clave] ?? '—'}</td>)}</tr>)}</tbody>
                    </table>
                </div>
            )}
        </Card>
    );
}

const monto = ['total', 'Importe', dinero];
const nombre = ['nombre', 'Nombre'];
const cobros = ['cobros', 'Cobros', numero];
const unidad = ['unidad', 'Unidad base'];
const sucursal = ['sucursal', 'Sucursal'];

export default function Reportes({ filtros, sucursales, reporte }) {
    const form = useForm(filtros);
    const { resumen } = reporte;
    const aplicar = (evento) => {
        evento.preventDefault();
        form.get('/reportes/restaurante', { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout title="Reportes del restaurante">
            <Head title="Reportes del restaurante" />
            <h1 className="mb-4 text-xl font-semibold">Reportes del restaurante</h1>
            <Card className="mb-4 p-4">
                <form onSubmit={aplicar} className="flex flex-wrap items-end gap-3">
                    <Field label="Desde" error={form.errors.desde}><Input type="date" required value={form.data.desde} onChange={(e) => form.setData('desde', e.target.value)} /></Field>
                    <Field label="Hasta" error={form.errors.hasta}><Input type="date" required min={form.data.desde} value={form.data.hasta} onChange={(e) => form.setData('hasta', e.target.value)} /></Field>
                    <Field label="Sucursal"><select className="min-h-10 rounded-lg border-slate-300 text-sm" value={form.data.sucursal} onChange={(e) => form.setData('sucursal', e.target.value)}>
                        {sucursales.length > 1 && <option value={0}>Todas las autorizadas</option>}
                        {sucursales.map((sede) => <option key={sede.id_sucursal} value={sede.id_sucursal}>{sede.nombre}</option>)}
                    </select></Field>
                    <Button type="submit" loading={form.processing}>Consultar</Button>
                </form>
            </Card>
            <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Stat label="Ventas cobradas" value={dinero(resumen.total)} hint={`${resumen.cobros} comprobantes registrados`} />
                <Stat label="Costo registrado" value={dinero(resumen.costo_registrado)} />
                <Stat label="Margen estimado sin IGV" value={dinero(resumen.margen)} hint={resumen.lineas_sin_costo ? `${resumen.lineas_sin_costo} líneas de platos sin costo registrado` : undefined} />
                <Stat label="Pedidos anulados" value={resumen.pedidos_anulados} />
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
                <Tabla titulo="Ventas por día" filas={reporte.dias} columnas={[[ 'dia', 'Fecha' ], cobros, monto]} />
                <Tabla titulo="Ventas por sucursal" filas={reporte.sucursales} columnas={[[ 'nombre', 'Sucursal' ], cobros, monto]} />
                <Tabla titulo="Platos más vendidos" filas={reporte.platos} columnas={[nombre, ['cantidad', 'Unidades', numero], ['total', 'Importe bruto', dinero]]} />
                <Tabla titulo="Ingresos brutos por categoría" filas={reporte.categorias} columnas={[nombre, monto]} />
                <Tabla titulo="Consumo de insumos" filas={reporte.insumos} columnas={[nombre, unidad, ['consumo', 'Consumo', formatearInsumo], ['devoluciones', 'Devuelto', formatearInsumo], ['merma_directa', 'Merma directa', formatearInsumo]]} />
                <Tabla titulo="Merma por anulación de platos" filas={reporte.merma_cocina} columnas={[nombre, unidad, ['cantidad', 'Cantidad', formatearInsumo], ['costo', 'Costo', dinero]]} />
                <Tabla titulo="Ventas por turno de caja" filas={reporte.caja} columnas={[['id_turno', 'Turno'], sucursal, ['nombres', 'Cajero'], ['estado', 'Estado'], cobros, monto, ['monto_cierre_declarado', 'Cierre declarado', dinero]]} />
                <Tabla titulo="Preparación por estación" filas={reporte.cocina} columnas={[sucursal, ['nombre', 'Estación'], ['comandas', 'Comandas', numero], ['segundos', 'Promedio (min)', (valor) => numero(Math.round(Number(valor) / 6) / 10)]]} />
                <Tabla titulo="Anulaciones recientes" filas={reporte.anulados} columnas={[['numero', 'Pedido'], sucursal, ['motivo_anulacion', 'Motivo'], ['cerrado_en', 'Fecha', (fecha) => new Date(fecha).toLocaleDateString('es-PE', { timeZone: 'America/Lima' })]]} />
            </div>
        </AppLayout>
    );
}
