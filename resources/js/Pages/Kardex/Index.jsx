import React, { useState, useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader, Dialog, Stat } from '@/Components/ui';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import {
    Search,
    Boxes,
    PackageCheck,
    AlertCircle,
    AlertTriangle,
    Eye,
    Settings,
    X,
    Calendar,
    DollarSign,
    Layers,
    Clock,
    CheckCircle2,
    Save,
    Loader2,
    ShieldAlert,
    TrendingUp
} from 'lucide-react';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

export default function Index({ auth, productos = [], kardex = [] }) {
    // Compatibilidad en caso de que el controlador envíe "productos" o "kardex"
    const listaArticulos = useMemo(() => {
        if (Array.isArray(productos) && productos.length > 0) return productos;
        if (Array.isArray(kardex) && kardex.length > 0) return kardex;
        if (productos?.data) return productos.data;
        if (kardex?.data) return kardex.data;
        return [];
    }, [productos, kardex]);

    const [busqueda, setBusqueda] = useState('');
    const [filtroStock, setFiltroStock] = useState('todos'); // 'todos', 'optimo', 'bajo'

    // Estados para Modales Propios
    const [articuloDetalle, setArticuloDetalle] = useState(null);
    const [articuloTarifas, setArticuloTarifas] = useState(null);
    const [preciosEditados, setPreciosEditados] = useState([]);
    const [guardandoTarifas, setGuardandoTarifas] = useState(false);
    const [modalExito, setModalExito] = useState(null);
    const [modalAlerta, setModalAlerta] = useState(null);

    // Métricas del Inventario Físico
    const metricas = useMemo(() => {
        let totalLotes = 0;
        let totalValorizado = 0;
        let articulosConSaldo = 0;

        listaArticulos.forEach((p) => {
            const stock = Number(p.stock_actual ?? p.kardex?.stock_actual ?? p.stock ?? 0);
            if (stock > 0) articulosConSaldo += 1;
            totalValorizado += stock * Number(p.precio_costo || 0);
            const lotesArr = p.lotes || [];
            totalLotes += lotesArr.length;
        });

        return {
            totalArticulos: listaArticulos.length,
            articulosConSaldo,
            totalValorizado,
            totalLotesActivos: totalLotes
        };
    }, [listaArticulos]);

    // Filtrado de Productos
    const articulosFiltrados = useMemo(() => {
        return listaArticulos.filter((p) => {
            const nombre = (p.nombre || '').toLowerCase();
            const codigo = (p.codigo_barras || p.codigo || '').toLowerCase();
            const q = busqueda.toLowerCase().trim();
            const matchTexto = !q || nombre.includes(q) || codigo.includes(q);

            const stock = Number(p.stock_actual ?? p.kardex?.stock_actual ?? p.stock ?? 0);
            let matchStock = true;

            if (filtroStock === 'optimo') matchStock = stock > 5;
            if (filtroStock === 'bajo') matchStock = stock <= 5;

            return matchTexto && matchStock;
        });
    }, [listaArticulos, busqueda, filtroStock]);

    // Formateador de Lote Seguro (corrige el error de "un.")
    const formatLoteBadge = (lote) => {
        if (!lote) return 'General';
        const codigo = lote.codigo_lote || lote.lote || 'LOTE';
        const stock = Number(lote.stock ?? lote.stock_actual ?? 0);
        return `${codigo} (${stock})`;
    };

    const nombreUnidad = (producto) => producto?.unidad_base?.nombre || 'unidades';
    const cantidad = (valor) => {
        const numero = Number(valor || 0);
        return Number.isInteger(numero) ? String(numero) : numero.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
    };

    // Apertura del modal de edición de tarifas
    const abrirEdicionTarifas = (producto) => {
        setArticuloTarifas(producto);
        const presentaciones = (producto.presentaciones || []).map((p) => ({
            id_precio: p.id_precio,
            id_presentacion: p.id_presentacion,
            nombre: p.presentacion?.nombre || p.nombre || 'Unidad',
            factor_conversion: p.factor_conversion || 1,
            precio_venta: Number(p.precio_venta || producto.precio_venta || 0)
        }));
        setPreciosEditados(presentaciones);
    };

    const handleCambioPrecio = (idPrecioOIndex, nuevoValor) => {
        setPreciosEditados((prev) =>
            prev.map((item, idx) => {
                if (item.id_precio === idPrecioOIndex || idx === idPrecioOIndex) {
                    return { ...item, precio_venta: parseFloat(nuevoValor) || 0 };
                }
                return item;
            })
        );
    };

    const guardarNuevasTarifas = async (e) => {
        e.preventDefault();
        setGuardandoTarifas(true);

        try {
            // Cada precio mantiene su misma presentación y factor; el controlador
            // usa updateOrCreate, por eso este flujo no altera el inventario.
            await Promise.all(preciosEditados.map((precio) => axios.post(
                `/productos/${articuloTarifas.id_producto}/presentaciones`,
                {
                    id_presentacion: precio.id_presentacion,
                    factor_conversion: precio.factor_conversion,
                    precio_venta: precio.precio_venta,
                },
                { headers: { Accept: 'application/json' } },
            )));

            setGuardandoTarifas(false);
            setArticuloTarifas(null);
            setModalExito({
                titulo: 'Tarifario Actualizado',
                mensaje: `Se reconfiguraron los precios de venta para "${articuloTarifas.nombre}".`
            });

            router.reload({ preserveScroll: true });
        } catch (error) {
            setGuardandoTarifas(false);
            setModalAlerta({
                titulo: 'No se pudo actualizar el tarifario',
                mensaje: error.response?.data?.message || 'Verifica los precios e inténtalo nuevamente.'
            });
        }
    };

    return (
        <AuthenticatedLayout header="Kardex valorizado">
            <Head title="Kardex valorizado" />

            <div className="space-y-4">
                <PageHeader
                    breadcrumbs={[{ label: 'Inventario' }, { label: 'Movimientos de existencias' }]}
                    title="Kardex valorizado"
                    description="Supervisa el stock físico central, consulta lotes FIFO y edita el precio de cada presentación."
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat destacado label="Productos en kardex" value={metricas.totalArticulos} hint="Catálogo con control de existencias" icon={PackageCheck} />
                    <Stat label="Inventario valorizado" value={currency.format(metricas.totalValorizado)} hint="Stock actual × costo de compra" icon={DollarSign} iconTone="success" />
                    <Stat label="Con saldo" value={metricas.articulosConSaldo} hint={`${metricas.totalArticulos - metricas.articulosConSaldo} sin existencias`} icon={Boxes} />
                    <Stat label="Lotes activos" value={metricas.totalLotesActivos} hint="Se listan hasta 30 por producto" icon={Layers} iconTone="warning" />
                </div>

                {/* 3. BARRA DE BÚSQUEDA Y FILTROS */}
                <div className="bg-white rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="relative w-full sm:w-80">
                        <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-3 pointer-events-none" />
                        <input
                            type="text"
                            placeholder="Buscar producto por nombre o código de barras..."
                            className="w-full pl-10 pr-3.5 py-2 text-sm bg-white border border-slate-200 rounded-lg transition"
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                        />
                    </div>

                    <div className="flex items-center gap-1.5 w-full sm:w-auto bg-slate-100/80 p-1 rounded-lg">
                        {[
                            { id: 'todos', label: `Todos (${listaArticulos.length})` },
                            { id: 'optimo', label: 'Stock Óptimo' },
                            { id: 'bajo', label: 'Stock Bajo (≤ 5)' }
                        ].map((filtro) => {
                            const activo = filtroStock === filtro.id;
                            return (
                                <button
                                    key={filtro.id}
                                    type="button"
                                    onClick={() => setFiltroStock(filtro.id)}
                                    style={
                                        activo
                                            ? { backgroundColor: 'var(--brand-primary, #0e7490)', color: '#ffffff' }
                                            : undefined
                                    }
                                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer ${
                                        activo ? 'shadow-xs' : 'text-slate-500 hover:text-slate-800'
                                    }`}
                                >
                                    {filtro.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* 4. TABLA PRINCIPAL DE KARDEX Y TARIFAS */}
                <div className="bg-white rounded-2xl overflow-hidden shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left whitespace-nowrap text-xs">
                            <thead className="bg-slate-50/70 border-b border-slate-100 text-xs font-semibold text-slate-400">
                                <tr>
                                    <th className="px-4 py-2.5 font-medium">Artículo / Producto</th>
                                    <th className="px-4 py-2.5 text-center font-medium">Stock Físico Real</th>
                                    <th className="px-4 py-2.5 font-medium">Lotes Activos (FIFO)</th>
                                    <th className="px-4 py-2.5 font-medium">Precios por Presentación (PVP)</th>
                                    <th className="px-4 py-2.5 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-semibold text-slate-700">
                                {articulosFiltrados.length > 0 ? (
                                    articulosFiltrados.map((prod) => {
                                        const stock = Number(prod.stock_actual ?? prod.kardex?.stock_actual ?? prod.stock ?? 0);
                                        const lotes = prod.lotes || [];
                                        const presentaciones = prod.presentaciones || [];

                                        return (
                                            <tr key={prod.id_producto} className="hover:bg-brand-soft/40 transition-colors">
                                                {/* Producto y Código */}
                                                <td className="px-4 py-2.5">
                                                    <p className="font-bold text-xs text-slate-900">{prod.nombre}</p>
                                                    <span className="text-xs text-slate-400 font-mono block mt-0.5">
                                                        CÓD: {prod.codigo_barras || prod.codigo || '—'}
                                                    </span>
                                                </td>

                                                {/* Stock Físico */}
                                                <td className="px-4 py-2.5 text-center">
                                                    <span
                                                        className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border ${
                                                            stock > 5
                                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                                : 'bg-rose-50 text-rose-700 border-rose-200'
                                                        }`}
                                                    >
                                                        <span
                                                            className={`w-1.5 h-1.5 rounded-full ${
                                                                stock > 5 ? 'bg-emerald-500' : 'bg-rose-500'
                                                            }`}
                                                        />
                                                        {cantidad(stock)} {nombreUnidad(prod)}
                                                    </span>
                                                </td>

                                                {/* Lotes FIFO Formateados Limpiamente */}
                                                <td className="px-4 py-2.5">
                                                    {lotes.length > 0 ? (
                                                        <div className="flex flex-wrap gap-1 max-w-xs">
                                                            {lotes.slice(0, 2).map((l, idx) => (
                                                                <span
                                                                    key={idx}
                                                                    className="bg-brand-soft text-brand-ink border border-blue-200/70 text-xs font-mono font-bold px-2 py-0.5 rounded-md"
                                                                >
                                                                    {formatLoteBadge(l)} {nombreUnidad(prod)}
                                                                </span>
                                                            ))}
                                                            {lotes.length > 2 && (
                                                                <span className="text-xs text-slate-400 font-bold self-center">
                                                                    +{lotes.length - 2} más
                                                                </span>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-slate-400 italic">
                                                            Lote General
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Presentaciones y Tarifas */}
                                                <td className="px-4 py-2.5">
                                                    <div className="flex flex-wrap gap-1.5 items-center">
                                                        {presentaciones.length > 0 ? (
                                                            presentaciones.map((p, idx) => {
                                                                const nombrePres = p.presentacion?.nombre || p.nombre || 'Unidad';
                                                                const factor = Math.round(Number(p.factor_conversion) || 1);
                                                                const etiqueta = factor > 1 ? `${nombrePres} (×${factor} ${nombreUnidad(prod)})` : `${nombrePres} (${nombreUnidad(prod)})`;
                                                                const precio = Number(p.precio_venta || prod.precio_venta || 0);

                                                                return (
                                                                    <span
                                                                        key={idx}
                                                                        className="bg-slate-50 border border-slate-200 px-2 py-0.5 rounded-md text-xs font-bold text-slate-600"
                                                                    >
                                                                        {etiqueta}:{' '}
                                                                        <strong className="text-emerald-700 font-mono">
                                                                            {currency.format(precio)}
                                                                        </strong>
                                                                    </span>
                                                                );
                                                            })
                                                        ) : (
                                                            <span className="font-mono font-bold text-emerald-700 text-xs">
                                                                {currency.format(Number(prod.precio_venta || 0))}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>

                                                {/* Acciones */}
                                                <td className="px-4 py-2.5 text-right">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <button
                                                            type="button"
                                                            onClick={() => setArticuloDetalle(prod)}
                                                            className="w-8 h-8 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 flex items-center justify-center transition cursor-pointer"
                                                            title="Ver detalle de kardex y lotes"
                                                        >
                                                            <Eye className="w-3.5 h-3.5" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => abrirEdicionTarifas(prod)}
                                                            className="w-8 h-8 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 flex items-center justify-center transition cursor-pointer"
                                                            title="Editar Precios de Presentaciones"
                                                        >
                                                            <Settings className="w-3.5 h-3.5" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan="5" className="py-16 text-center text-slate-400">
                                            <p className="font-bold text-xs text-slate-500">
                                                No se encontraron artículos en el inventario
                                            </p>
                                            <p className="text-xs text-slate-400 mt-0.5">
                                                Verifique el criterio de búsqueda o ingrese mercadería desde el módulo de Compras.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* MODAL 1: AUDITORÍA DE KARDEX Y LOTES FIFO (OJITO) */}
            <Dialog open={Boolean(articuloDetalle)} onClose={() => setArticuloDetalle(null)} size="xl">
                {(articuloDetalle) && (
                <div className="p-6 sm:p-7 flex flex-col">
                        <div className="flex items-start justify-between pb-4 border-b border-slate-100 mb-4 shrink-0">
                            <div>
                                <span className="text-xs font-semibold text-slate-400 block mb-0.5">
                                    Auditoría de Existencias Físicas
                                </span>
                                <h3 className="text-lg font-semibold text-slate-900 tracking-tight">
                                    {articuloDetalle.nombre}
                                </h3>
                                <p className="text-xs text-slate-500 font-mono mt-0.5">
                                    CÓDIGO: {articuloDetalle.codigo_barras || articuloDetalle.codigo || '—'}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setArticuloDetalle(null)}
                                className="w-8 h-8 rounded-xl text-slate-400 hover:bg-slate-100 flex items-center justify-center cursor-pointer"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        {/* Balance Físico Valorizado */}
                        <div className="grid grid-cols-3 gap-2.5 mb-4 shrink-0">
                            <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-center">
                                <span className="block text-xs font-semibold text-slate-400">
                                    Stock Central
                                </span>
                                <span className="text-base font-semibold text-slate-900 font-mono">
                                    {cantidad(articuloDetalle.stock_actual)} {nombreUnidad(articuloDetalle)}
                                </span>
                            </div>
                            <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-center">
                                <span className="block text-xs font-semibold text-slate-400">
                                    Costo Promedio
                                </span>
                                <span className="text-base font-semibold text-slate-900 font-mono">
                                    {currency.format(Number(articuloDetalle.precio_costo ?? 0))}
                                </span>
                            </div>
                            <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg text-center">
                                <span className="block text-xs font-semibold text-slate-400">
                                    Valorización
                                </span>
                                <span className="text-base font-semibold text-emerald-700 font-mono">
                                    {currency.format(
                                        (Number(articuloDetalle.stock_actual ?? articuloDetalle.stock ?? 0)) *
                                        (Number(articuloDetalle.precio_costo ?? 0))
                                    )}
                                </span>
                            </div>
                        </div>

                        {/* Detalle de Lotes FIFO */}
                        <div className="flex-1 overflow-y-auto mb-4 border border-slate-100 rounded-lg [scrollbar-width:thin]">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 text-slate-400 uppercase text-xs font-semibold border-b border-slate-100">
                                    <tr>
                                        <th className="py-2.5 px-3 font-medium">Código de Lote</th>
                                        <th className="py-2.5 px-2 text-center font-medium">Vencimiento</th>
                                        <th className="py-2.5 px-3 text-right font-medium">Stock Disponible</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 font-semibold text-slate-700">
                                    {(articuloDetalle.lotes || []).length > 0 ? (
                                        articuloDetalle.lotes.map((l, idx) => (
                                            <tr key={idx}>
                                                <td className="py-2.5 px-3 font-mono font-bold text-brand-ink">
                                                    {l.codigo_lote}
                                                </td>
                                                <td className="py-2.5 px-2 text-center text-slate-500 font-mono">
                                                    {l.fecha_vencimiento
                                                        ? new Date(l.fecha_vencimiento).toLocaleDateString('es-PE')
                                                        : 'No perecible'}
                                                </td>
                                                <td className="py-2.5 px-3 text-right font-mono font-semibold text-slate-900">
                                                    {cantidad(l.stock ?? l.stock_actual)} {nombreUnidad(articuloDetalle)}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="3" className="py-8 text-center text-slate-400 text-xs">
                                                No hay lotes específicos registrados para este artículo.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="shrink-0">
                            <div className="mb-2 flex items-center justify-between">
                                <h4 className="text-xs font-semibold text-slate-400">Últimos movimientos registrados</h4>
                                <span className="text-xs font-semibold text-slate-400">{(articuloDetalle.movimientos || []).length} movimientos</span>
                            </div>
                            <div className="max-h-52 overflow-y-auto rounded-lg border border-slate-100 [scrollbar-width:thin]">
                                <table className="w-full text-left text-xs">
                                    <thead className="sticky top-0 bg-slate-50 text-xs font-semibold text-slate-400">
                                        <tr><th className="px-3 py-2 font-medium">Fecha</th><th className="px-2 py-2 font-medium">Movimiento</th><th className="px-2 py-2 text-right font-medium">Cantidad</th><th className="px-3 py-2 text-right font-medium">Saldo</th></tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {(articuloDetalle.movimientos || []).length > 0 ? articuloDetalle.movimientos.map((movimiento) => {
                                            const esEntrada = Number(movimiento.cantidad_entrada) > 0;
                                            const cantidadMovimiento = esEntrada ? movimiento.cantidad_entrada : movimiento.cantidad_salida;
                                            return <tr key={movimiento.id_kardex} className="font-semibold text-slate-600">
                                                <td className="px-3 py-2 font-mono text-xs">{movimiento.ultima_actualizacion ? new Date(movimiento.ultima_actualizacion).toLocaleString('es-PE') : '—'}</td>
                                                <td className={`px-2 py-2 font-bold ${esEntrada ? 'text-emerald-700' : 'text-rose-700'}`}>{esEntrada ? 'Entrada' : 'Salida'}</td>
                                                <td className={`px-2 py-2 text-right font-mono ${esEntrada ? 'text-emerald-700' : 'text-rose-700'}`}>{esEntrada ? '+' : '-'}{cantidad(cantidadMovimiento)} {nombreUnidad(articuloDetalle)}</td>
                                                <td className="px-3 py-2 text-right font-mono font-semibold text-slate-900">{cantidad(movimiento.stock_actual)} {nombreUnidad(articuloDetalle)}</td>
                                            </tr>;
                                        }) : <tr><td colSpan="4" className="px-3 py-6 text-center text-slate-400">Aún no hay movimientos para este producto.</td></tr>}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div className="flex justify-end shrink-0">
                            <button
                                type="button"
                                onClick={() => setArticuloDetalle(null)}
                                className="volumen-marca h-10 px-5 rounded-xl text-on-brand font-bold text-xs transition cursor-pointer hover:brightness-105"
                            >
                                Cerrar Ventana
                            </button>
                        </div>
                </div>
                )}
            </Dialog>

            {/* MODAL 2: AJUSTE DE TARIFAS POR PRESENTACIÓN (ENGRANAJE) */}
            <Dialog open={Boolean(articuloTarifas)} onClose={() => setArticuloTarifas(null)} size="md">
                {(articuloTarifas) && (
                <div className="p-6">
                        <div className="flex items-start justify-between pb-3 border-b border-slate-100 mb-4">
                            <div>
                                <span className="text-xs font-semibold text-slate-400 block mb-0.5">
                                    Actualización de Tarifario
                                </span>
                                <h3 className="text-base font-semibold text-slate-900 tracking-tight">
                                    {articuloTarifas.nombre}
                                </h3>
                            </div>
                            <button
                                type="button"
                                onClick={() => setArticuloTarifas(null)}
                                className="w-8 h-8 rounded-xl text-slate-400 hover:bg-slate-100 flex items-center justify-center cursor-pointer"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <form onSubmit={guardarNuevasTarifas} className="space-y-3.5">
                            <div className="max-h-60 overflow-y-auto space-y-2.5 pr-1 [scrollbar-width:thin]">
                                {preciosEditados.map((item, idx) => (
                                    <div
                                        key={idx}
                                        className="p-3 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <span className="block text-xs font-bold text-slate-800">
                                                {item.nombre}
                                            </span>
                                            <span className="text-xs text-slate-400 font-semibold">
                                                Factor: {item.factor_conversion} un. base
                                            </span>
                                        </div>

                                        <div className="w-32 relative flex items-center">
                                            <span className="absolute left-3 text-xs font-bold text-slate-400">
                                                S/
                                            </span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                value={item.precio_venta}
                                                onWheel={(e) => e.target.blur()}
                                                onChange={(e) => handleCambioPrecio(item.id_precio || idx, e.target.value)}
                                                className="w-full h-9 pl-7 pr-2.5 font-mono font-semibold text-xs text-right text-slate-900 bg-white border border-slate-200 rounded-xl [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                required
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>

                            <div className="flex gap-2.5 pt-2 border-t border-slate-100">
                                <button
                                    type="button"
                                    onClick={() => setArticuloTarifas(null)}
                                    className="flex-1 h-10 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition cursor-pointer"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={guardandoTarifas}
                                    className="volumen-marca text-on-brand flex-1 h-10 rounded-xl font-bold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer disabled:bg-slate-300 hover:brightness-105"
                                >
                                    {guardandoTarifas ? (
                                        <Loader2 className="w-4 h-4 animate-spin" />
                                    ) : (
                                        <>
                                            <Save className="w-4 h-4" />
                                            <span>Guardar Tarifas</span>
                                        </>
                                    )}
                                </button>
                            </div>
                        </form>
                </div>
                )}
            </Dialog>

            {/* MODAL 3: ÉXITO */}
            <Dialog open={Boolean(modalExito)} onClose={() => setModalExito(null)} size="sm">
                {(modalExito) && (
                <div className="p-6 text-center">
                        <div
                            className="bg-brand text-on-brand hover:bg-brand-hover w-12 h-12 rounded-lg flex items-center justify-center mx-auto mb-3"
                        >
                            <CheckCircle2 className="w-6 h-6" />
                        </div>
                        <h4 className="text-base font-semibold text-slate-900">{modalExito.titulo}</h4>
                        <p className="text-xs text-slate-500 font-medium mt-1 leading-relaxed">
                            {modalExito.mensaje}
                        </p>
                        <button
                            type="button"
                            onClick={() => setModalExito(null)}
                            className="volumen-marca text-on-brand w-full mt-4 h-10 rounded-xl font-bold text-xs transition cursor-pointer hover:brightness-105"
                        >
                            Aceptar
                        </button>
                </div>
                )}
            </Dialog>

            {/* MODAL 4: ALERTA */}
            <Dialog open={Boolean(modalAlerta)} onClose={() => setModalAlerta(null)} size="sm">
                {(modalAlerta) && (
                <div className="p-6">
                        <div className="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center mb-3">
                            <AlertCircle className="w-5 h-5" />
                        </div>
                        <h4 className="text-sm font-semibold text-slate-900">{modalAlerta.titulo}</h4>
                        <p className="text-xs text-slate-600 font-medium mt-1.5 leading-relaxed">
                            {modalAlerta.mensaje}
                        </p>
                        <button
                            type="button"
                            onClick={() => setModalAlerta(null)}
                            className="volumen-marca w-full mt-4 h-10 rounded-xl text-on-brand font-bold text-xs transition cursor-pointer hover:brightness-105"
                        >
                            Entendido
                        </button>
                </div>
                )}
            </Dialog>
        </AuthenticatedLayout>
    );
}
