import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight, Calendar, ChefHat, Clock, Minus, Receipt, Store, TrendingUp, Users, Wallet } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, CardHeader, Field, Input, PageHeader, Stat, Table, TBody, Td, Th, THead, Tr, cx } from '@/Components/ui';
import { Barras, Columnas } from '@/Components/Restaurante/GraficosReporte';
import { cantidad, soles } from '@/Components/Restaurante/formato';
import { conParticipacion, diasDelPeriodo, horasDelServicio, periodoDeRango, rangoDePeriodo, variacion } from '@/Components/Restaurante/reportes';

const dinero = (valor) => (valor == null ? '—' : soles(valor));
const numero = (valor) => (valor == null ? '—' : cantidad(valor));
const solesCortos = (valor) => (valor >= 1000 ? `S/ ${Math.round(valor / 100) / 10}k` : `S/ ${Math.round(valor)}`);
const formatearInsumo = new Intl.NumberFormat('es-PE', { maximumFractionDigits: 4 }).format;
const fechaCorta = (iso, corta = false) => new Date(`${iso}T12:00:00-05:00`).toLocaleDateString('es-PE', { timeZone: 'America/Lima', day: 'numeric', month: corta ? 'numeric' : 'short' });
const TIPOS = { MESA: 'Mesa', PARA_LLEVAR: 'Para llevar', DELIVERY: 'Delivery' };
const PERIODOS = [['hoy', 'Hoy'], ['ayer', 'Ayer'], ['7dias', '7 días'], ['30dias', '30 días'], ['mes', 'Este mes']];

/** Variación contra el período anterior, con flecha y texto (nunca solo color). */
function Variacion({ actual, anterior }) {
    const cambio = variacion(actual, anterior);
    if (cambio == null) return null;
    const Icono = cambio === 0 ? Minus : cambio > 0 ? ArrowUpRight : ArrowDownRight;
    const tono = cambio === 0 ? 'bg-slate-100 text-slate-600' : cambio > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700';
    return (
        <span className={cx('inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-xs font-medium tabular-nums', tono)}>
            <Icono className="h-3.5 w-3.5" aria-hidden="true" />
            {cambio > 0 ? '+' : ''}{cambio.toLocaleString('es-PE')} %
            <span className="sr-only"> frente al período anterior</span>
        </span>
    );
}

function Tabla({ titulo, filas, columnas }) {
    return (
        <Card className="min-w-0">
            <CardHeader title={titulo} />
            {!filas.length ? <p className="px-5 py-6 text-sm text-slate-500">Sin operaciones en el período.</p> : (
                <Table caption={titulo}>
                    <THead>{columnas.map(([clave, nombre, , alinear]) => <Th key={clave} align={alinear}>{nombre}</Th>)}</THead>
                    <TBody>
                        {filas.map((fila, i) => (
                            <Tr key={i}>{columnas.map(([clave, , formato, alinear]) => <Td key={clave} align={alinear} className={alinear === 'right' ? 'tabular-nums' : undefined}>{formato ? formato(fila[clave]) : fila[clave] ?? '—'}</Td>)}</Tr>
                        ))}
                    </TBody>
                </Table>
            )}
        </Card>
    );
}

export default function Reportes({ filtros, sucursales, reporte }) {
    const { resumen, anterior } = reporte;
    const [fechas, setFechas] = useState({ desde: filtros.desde, hasta: filtros.hasta });
    const [verFechas, setVerFechas] = useState(false);
    const [cargando, setCargando] = useState(false);
    const [errores, setErrores] = useState({});
    const periodo = periodoDeRango(filtros.desde, filtros.hasta);
    const unDia = filtros.desde === filtros.hasta;

    const consultar = (cambios) => router.get('/reportes/restaurante', { ...filtros, ...cambios }, {
        preserveState: true, preserveScroll: true, replace: true,
        onStart: () => { setCargando(true); setErrores({}); },
        onError: setErrores,
        onFinish: () => setCargando(false),
    });
    const aplicarFechas = (evento) => {
        evento.preventDefault();
        consultar(fechas);
    };

    const sede = sucursales.find((s) => Number(s.id_sucursal) === Number(filtros.sucursal))?.nombre ?? 'Todas las sucursales';
    const rotuloPeriodo = unDia ? fechaCorta(filtros.desde) : `${fechaCorta(filtros.desde)} – ${fechaCorta(filtros.hasta)}`;
    const horas = horasDelServicio(reporte.horas ?? []);
    const dias = unDia ? [] : diasDelPeriodo(reporte.dias, filtros.desde, filtros.hasta);
    const tipos = conParticipacion(reporte.tipos ?? []);
    const categorias = conParticipacion(reporte.categorias.map((c) => ({ ...c, total: Number(c.total) })));
    const horaPico = horas.reduce((pico, fila) => (fila.total > (pico?.total ?? 0) ? fila : pico), null);

    return (
        <AppLayout title="Reportes del restaurante">
            <Head title="Reportes del restaurante" />
            <div className="space-y-5 pb-20">
                <PageHeader
                    title="Reportes del restaurante"
                    meta={<span className="inline-flex items-center gap-1.5"><Store className="h-3.5 w-3.5" aria-hidden="true" />{sede}<Calendar className="ml-2 h-3.5 w-3.5" aria-hidden="true" />{rotuloPeriodo}</span>}
                    actions={
                        <div className={cx('flex flex-wrap items-center gap-2 transition-opacity', cargando && 'opacity-60')} aria-busy={cargando || undefined}>
                            <div className="flex flex-wrap items-center gap-0.5 rounded-lg border border-slate-200 bg-white p-0.5" role="group" aria-label="Período">
                                {PERIODOS.map(([clave, texto]) => (
                                    <button key={clave} type="button" aria-pressed={periodo === clave} onClick={() => { setVerFechas(false); consultar(rangoDePeriodo(clave)); }}
                                        className={cx('rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors', periodo === clave ? 'bg-brand text-on-brand' : 'text-slate-600 hover:bg-brand-soft hover:text-brand-ink')}>
                                        {texto}
                                    </button>
                                ))}
                                <button type="button" aria-expanded={verFechas} onClick={() => {
                                        if (!verFechas) {
                                            setErrores({});
                                            setFechas({ desde: filtros.desde, hasta: filtros.hasta });
                                        }
                                        setVerFechas(!verFechas);
                                    }}
                                    className={cx('inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors', !periodo ? 'bg-brand text-on-brand' : 'text-slate-600 hover:bg-brand-soft hover:text-brand-ink')}>
                                    <Calendar className="h-3.5 w-3.5" aria-hidden="true" />Fechas
                                </button>
                            </div>
                            {sucursales.length > 1 && (
                                <label className="sr-only" htmlFor="reporte-sucursal">Sucursal</label>
                            )}
                            {sucursales.length > 1 && (
                                <select id="reporte-sucursal" className="min-h-9 rounded-lg border-slate-200 text-sm" value={filtros.sucursal} onChange={(e) => consultar({ sucursal: e.target.value })}>
                                    <option value={0}>Todas las sucursales</option>
                                    {sucursales.map((s) => <option key={s.id_sucursal} value={s.id_sucursal}>{s.nombre}</option>)}
                                </select>
                            )}
                        </div>
                    }
                />

                {verFechas && (
                    <Card as="form" onSubmit={aplicarFechas} className="flex flex-wrap items-end gap-3 p-4">
                        <Field label="Desde" error={errores.desde}><Input type="date" required value={fechas.desde} max={fechas.hasta} onChange={(e) => setFechas((f) => ({ ...f, desde: e.target.value }))} /></Field>
                        <Field label="Hasta" error={errores.hasta}><Input type="date" required value={fechas.hasta} min={fechas.desde} onChange={(e) => setFechas((f) => ({ ...f, hasta: e.target.value }))} /></Field>
                        <Button type="submit" loading={cargando}>Consultar</Button>
                        <p className="text-xs text-slate-500">Hasta 90 días por consulta.</p>
                    </Card>
                )}

                {anterior.desde && anterior.hasta && (
                    <p className="text-sm text-slate-600">Período anterior: {fechaCorta(anterior.desde)} – {fechaCorta(anterior.hasta)}</p>
                )}

                <section aria-label="Indicadores del período" className="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-5">
                    <Stat destacado className="col-span-2 lg:col-span-1" label="Ventas cobradas" value={dinero(resumen.total)} icon={Wallet}
                        extra={<Variacion actual={resumen.total} anterior={anterior.total} />}
                        hint={anterior.total ? `Antes: ${dinero(anterior.total)}` : `${resumen.cobros} comprobantes`} />
                    <Stat label="Ticket promedio" value={dinero(resumen.ticket_promedio)} icon={Receipt}
                        extra={<Variacion actual={resumen.ticket_promedio} anterior={anterior.ticket_promedio} />}
                        hint={`${numero(resumen.cobros)} cobros`} />
                    <Stat label="Pedidos con cobro" value={numero(resumen.pedidos)} icon={Users}
                        hint={resumen.comensales ? `${numero(resumen.comensales)} comensales · ${dinero(resumen.gasto_por_comensal)} c/u` : 'Sin comensales registrados'} />
                    <Stat label="Margen estimado" value={dinero(resumen.margen)} icon={TrendingUp} tone={resumen.margen != null && resumen.margen < 0 ? 'danger' : 'default'}
                        hint={resumen.lineas_sin_costo ? `${resumen.lineas_sin_costo} platos sin receta: no se puede calcular` : `Sin IGV · costo ${dinero(resumen.costo_registrado)}`} />
                    <Stat label="Tiempo de cocina" value={resumen.minutos_cocina == null ? '—' : `${resumen.minutos_cocina.toLocaleString('es-PE')} min`} icon={Clock}
                        hint={resumen.pedidos_anulados ? `${resumen.pedidos_anulados} pedidos anulados` : 'Promedio hasta que la comanda queda lista'} />
                </section>

                <div className="grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <Card className="min-w-0 xl:col-span-2">
                        <CardHeader title={unDia ? 'Ventas por hora' : 'Ventas por día'} icon={TrendingUp}
                            description={unDia ? (horaPico ? `Hora pico: ${horaPico.hora}:00 con ${dinero(horaPico.total)}` : undefined) : `${reporte.dias.length} días con ventas`} />
                        <div className="px-5 pt-6 pb-4">
                            {unDia
                                ? <Columnas datos={horas} valor={(f) => f.total} formato={dinero} formatoEje={solesCortos} descripcion="Ventas por hora"
                                    etiqueta={(f, corta) => (corta ? `${f.hora}h` : `${f.hora}:00 – ${f.hora}:59 · ${f.cobros} cobros`)} />
                                : <Columnas datos={dias} valor={(f) => Number(f.total)} formato={dinero} formatoEje={solesCortos} descripcion="Ventas por día"
                                    etiqueta={(f, corta) => (corta ? fechaCorta(f.dia, true) : `${fechaCorta(f.dia)} · ${f.cobros} cobros`)} />}
                        </div>
                    </Card>
                    <Card className="min-w-0">
                        <CardHeader title="Por tipo de pedido" icon={Store} />
                        <div className="p-5">
                            <Barras filas={tipos} nombre={(f) => TIPOS[f.tipo] ?? f.tipo} valor={(f) => f.total} formato={dinero}
                                detalle={(f) => `${f.participacion.toLocaleString('es-PE')} % · ${numero(f.pedidos)} pedidos`} />
                        </div>
                    </Card>
                </div>

                {!unDia && horas.length > 0 && (
                    <Card className="min-w-0">
                        <CardHeader title="Horas pico" icon={Clock} description={horaPico ? `Mayor importe cobrado entre las ${horaPico.hora}:00 y las ${horaPico.hora}:59, sumando todo el período.` : undefined} />
                        <div className="px-5 pt-6 pb-4">
                            <Columnas datos={horas} valor={(f) => f.total} formato={dinero} formatoEje={solesCortos} descripcion="Ventas por hora del día" alto={140}
                                etiqueta={(f, corta) => (corta ? `${f.hora}h` : `${f.hora}:00 – ${f.hora}:59 · ${f.cobros} cobros`)} />
                        </div>
                    </Card>
                )}

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <Card className="min-w-0">
                        <CardHeader title="Platos más vendidos" icon={ChefHat} description="Por unidades vendidas" />
                        <div className="p-5">
                            <Barras filas={reporte.platos.slice(0, 10).map((p) => ({ ...p, cantidad: Number(p.cantidad) }))}
                                nombre={(f) => f.nombre} valor={(f) => f.cantidad} formato={(v) => `${numero(v)} u.`} detalle={(f) => dinero(f.total)} />
                        </div>
                    </Card>
                    <Card className="min-w-0">
                        <CardHeader title="Ventas por categoría" icon={Receipt} description="Subtotal de ítems antes del descuento global" />
                        <div className="p-5">
                            <Barras filas={categorias} nombre={(f) => f.nombre} valor={(f) => f.total} formato={dinero}
                                detalle={(f) => `${f.participacion.toLocaleString('es-PE')} % del subtotal`} />
                        </div>
                    </Card>
                </div>

                <h2 className="pt-2 text-base font-semibold text-slate-900">Detalle operativo</h2>
                <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
                    <Tabla titulo="Preparación por estación" filas={reporte.cocina} columnas={[['sucursal', 'Sucursal'], ['nombre', 'Estación'], ['comandas', 'Comandas', numero, 'right'], ['segundos', 'Promedio', (v) => `${numero(Math.round(Number(v) / 6) / 10)} min`, 'right']]} />
                    {sucursales.length > 1 && <Tabla titulo="Ventas por sucursal" filas={reporte.sucursales} columnas={[['nombre', 'Sucursal'], ['cobros', 'Cobros', numero, 'right'], ['total', 'Importe', dinero, 'right']]} />}
                    <Tabla titulo="Ventas por turno de caja" filas={reporte.caja} columnas={[['id_turno', 'Turno'], ['sucursal', 'Sucursal'], ['nombres', 'Cajero'], ['estado', 'Estado'], ['cobros', 'Cobros', numero, 'right'], ['total', 'Importe', dinero, 'right'], ['monto_cierre_declarado', 'Cierre declarado', dinero, 'right']]} />
                    <Tabla titulo="Consumo de insumos" filas={reporte.insumos} columnas={[['nombre', 'Insumo'], ['unidad', 'Unidad'], ['consumo', 'Consumo', formatearInsumo, 'right'], ['devoluciones', 'Devuelto', formatearInsumo, 'right'], ['merma_directa', 'Merma', formatearInsumo, 'right']]} />
                    <Tabla titulo="Merma por anulación de platos" filas={reporte.merma_cocina} columnas={[['nombre', 'Insumo'], ['unidad', 'Unidad'], ['cantidad', 'Cantidad', formatearInsumo, 'right'], ['costo', 'Costo', dinero, 'right']]} />
                    <Tabla titulo="Anulaciones recientes" filas={reporte.anulados} columnas={[['numero', 'Pedido'], ['sucursal', 'Sucursal'], ['motivo_anulacion', 'Motivo'], ['cerrado_en', 'Fecha', (fecha) => new Date(fecha).toLocaleDateString('es-PE', { timeZone: 'America/Lima' })]]} />
                </div>
            </div>
        </AppLayout>
    );
}
