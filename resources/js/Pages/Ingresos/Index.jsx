import React, { useState, useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader, Dialog, PaginacionServidor, Stat } from '@/Components/ui';
import { Head, Link } from '@inertiajs/react';
import {
    PackagePlus,
    Search,
    Eye,
    CheckCircle2,
    Building2,
    Calendar,
    Boxes,
    FileText,
    DollarSign,
    X,
    Layers,
    ShieldCheck
} from 'lucide-react';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

export default function Index({ auth, ingresos = [] }) {
    const [busqueda, setBusqueda] = useState('');
    const [filtroTipo, setFiltroTipo] = useState('todos');
    const [selectedIngreso, setSelectedIngreso] = useState(null);

    const listaIngresos = useMemo(() => {
        if (Array.isArray(ingresos)) return ingresos;
        if (ingresos?.data) return ingresos.data;
        return [];
    }, [ingresos]);

    // Métricas ejecutivas
    const metricas = useMemo(() => {
        let totalInvertido = 0;
        const proveedoresUnicos = new Set();

        listaIngresos.forEach((ing) => {
            totalInvertido += Number(ing.total_ingreso || ing.total || 0);
            const provNombre = ing.proveedor?.nombre_razon_social || ing.proveedor?.razon_social || ing.proveedor?.nombre;
            if (provNombre) proveedoresUnicos.add(provNombre);
        });

        return {
            totalCompras: listaIngresos.length,
            totalInvertido,
            totalProveedores: proveedoresUnicos.size
        };
    }, [listaIngresos]);

    const ingresosFiltrados = useMemo(() => {
        return listaIngresos.filter((ing) => {
            const proveedor = (
                ing.proveedor?.nombre_razon_social ||
                ing.proveedor?.razon_social ||
                ing.proveedor?.nombre ||
                ''
            ).toLowerCase();
            const ruc = (ing.proveedor?.num_documento || ing.proveedor?.ruc || '').toLowerCase();
            const serieNumero = `${ing.serie_comprobante || ''}-${ing.num_comprobante || ''}`.toLowerCase();
            const q = busqueda.toLowerCase().trim();

            const matchTexto = !q || proveedor.includes(q) || ruc.includes(q) || serieNumero.includes(q);
            const tipoComp = (ing.tipo_comprobante || ing.tipoComprobante?.nombre || '').toLowerCase();
            let matchTipo = true;

            if (filtroTipo === 'factura') matchTipo = tipoComp.includes('factura');
            else if (filtroTipo === 'boleta') matchTipo = tipoComp.includes('boleta');
            else if (filtroTipo === 'guia') matchTipo = tipoComp.includes('guia') || tipoComp.includes('remision');

            return matchTexto && matchTipo;
        });
    }, [listaIngresos, busqueda, filtroTipo]);

    const openDetails = (ingreso) => setSelectedIngreso(ingreso);
    const closeDetails = () => setSelectedIngreso(null);

    return (
        <AuthenticatedLayout header="Entradas de mercadería">
            <Head title="Entradas de mercadería" />

            <div className="space-y-4">
                <PageHeader
                    breadcrumbs={[{ label: 'Inventario' }, { label: 'Entradas de mercadería' }]}
                    title="Entradas de mercadería"
                    description="Registro de facturas de proveedores e incremento automático de stock en Kardex."
                    actions={
                        <>
                        <Link
                            href="/ingresos/create"
                            className="bg-brand text-on-brand hover:bg-brand-hover h-11 px-5 rounded-lg font-semibold text-xs transition flex items-center gap-2 cursor-pointer"
                        >
                            <PackagePlus className="w-4 h-4" />
                            <span>Hacer Nueva Compra</span>
                        </Link>
                        </>
                    }
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Stat destacado label="Compras registradas" value={Number(ingresos?.total ?? listaIngresos.length).toLocaleString('es-PE')} hint="Total en el sistema" icon={FileText} />
                    <Stat label="Monto en esta página" value={currency.format(metricas.totalInvertido)} hint={`Suma de ${metricas.totalCompras} comprobantes listados`} icon={DollarSign} iconTone="success" />
                    <Stat label="Proveedores en esta página" value={metricas.totalProveedores} hint="Empresas distintas en la lista actual" icon={Building2} />
                </div>

                {/* 3. BARRA DE HERRAMIENTAS Y PÍLDORAS FILTRADORAS */}
                <div className="bg-white rounded-2xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="relative w-full md:w-80">
                        <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-3 pointer-events-none" />
                        <input
                            type="text"
                            placeholder="Buscar por proveedor, RUC o serie..."
                            className="w-full pl-10 pr-3.5 py-2 text-sm bg-white border border-slate-200 rounded-lg transition"
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                        />
                    </div>

                    <div className="flex items-center gap-1.5 w-full md:w-auto bg-slate-100/80 p-1 rounded-lg">
                        {[
                            { id: 'todos', label: `Todos (${listaIngresos.length})` },
                            { id: 'factura', label: 'Facturas' },
                            { id: 'boleta', label: 'Boletas' },
                            { id: 'guia', label: 'Guías' }
                        ].map((filtro) => {
                            const activo = filtroTipo === filtro.id;
                            return (
                                <button
                                    key={filtro.id}
                                    type="button"
                                    onClick={() => setFiltroTipo(filtro.id)}
                                    style={
                                        activo
                                            ? { backgroundColor: 'var(--brand-primary, #0e7490)', color: '#ffffff' }
                                            : undefined
                                    }
                                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer ${
                                        activo
                                            ? 'shadow-xs'
                                            : 'text-slate-500 hover:text-slate-800'
                                    }`}
                                >
                                    {filtro.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* 4. TABLA PRINCIPAL DE COMPRAS */}
                <div className="bg-white rounded-2xl overflow-hidden shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left whitespace-nowrap text-xs">
                            <thead className="bg-slate-50/70 border-b border-slate-100 text-xs font-semibold text-slate-400">
                                <tr>
                                    <th className="px-4 py-2.5 font-medium">Fecha de Emisión</th>
                                    <th className="px-4 py-2.5 font-medium">Comprobante</th>
                                    <th className="px-4 py-2.5 font-medium">Proveedor Abastecedor</th>
                                    <th className="px-4 py-2.5 text-right font-medium">Total Invertido</th>
                                    <th className="px-4 py-2.5 text-center font-medium">Estado Almacén</th>
                                    <th className="px-4 py-2.5 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-semibold text-slate-700">
                                {ingresosFiltrados.length > 0 ? (
                                    ingresosFiltrados.map((ing) => {
                                        const total = Number(ing.total_ingreso || ing.total || 0);
                                        const fecha = ing.fecha_emision ? new Date(ing.fecha_emision) : new Date();

                                        return (
                                            <tr key={ing.id_ingreso} className="hover:bg-brand-soft/40 transition-colors">
                                                <td className="px-4 py-2.5">
                                                    <span className="font-bold text-slate-800">
                                                        {fecha.toLocaleDateString('es-PE')}
                                                    </span>
                                                    <span className="text-xs text-slate-400 block font-mono">
                                                        {fecha.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-2.5">
                                                    <div className="flex items-center gap-2">
                                                        <span className="px-2 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                                            {ing.tipo_comprobante || ing.tipoComprobante?.nombre || 'Factura'}
                                                        </span>
                                                        <span className="font-mono font-semibold text-slate-900 text-xs">
                                                            {ing.serie_comprobante}-{ing.num_comprobante}
                                                        </span>
                                                    </div>
                                                </td>

                                                <td className="px-4 py-2.5">
                                                    <p className="font-bold text-slate-800 text-xs truncate max-w-xs">
                                                        {ing.proveedor?.nombre_razon_social ||
                                                            ing.proveedor?.razon_social ||
                                                            ing.proveedor?.nombre ||
                                                            'Proveedor Comercial'}
                                                    </p>
                                                    <span className="text-xs text-slate-400 font-mono">
                                                        RUC: {ing.proveedor?.num_documento || ing.proveedor?.ruc || '—'}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-2.5 text-right font-semibold text-slate-900 text-sm font-mono">
                                                    {currency.format(total)}
                                                </td>

                                                <td className="px-4 py-2.5 text-center">
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        {ing.estado || 'Ingresado'}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-2.5 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => openDetails(ing)}
                                                        className="h-8 px-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 font-bold text-xs inline-flex items-center gap-1.5 transition cursor-pointer"
                                                        title="Ver productos ingresados"
                                                    >
                                                        <Eye className="w-3.5 h-3.5" />
                                                        <span>Detalles</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan="6" className="py-16 text-center text-slate-400">
                                            <p className="font-bold text-xs text-slate-500">
                                                No se encontraron compras registradas
                                            </p>
                                            <p className="text-xs text-slate-400 mt-0.5">
                                                Haz clic en "Hacer Nueva Compra" para ingresar mercadería al inventario.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <PaginacionServidor paginador={ingresos} entidad="compras" />
                </div>
            </div>

            {/* MODAL PROPIO: DETALLES DE COMPRA (SIN DEGRADADOS OSCUROS) */}
            <Dialog open={Boolean(selectedIngreso)} onClose={closeDetails} size="2xl">
                {(selectedIngreso) && (
                <div className="p-6 sm:p-7 flex flex-col">
                        <div className="flex items-start justify-between pb-4 border-b border-slate-100 mb-4 shrink-0">
                            <div>
                                <div className="flex items-center gap-2 mb-1.5">
                                    <span className="px-2.5 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-800">
                                        {selectedIngreso.tipo_comprobante || 'Factura'}
                                    </span>
                                    <span className="text-xs font-mono text-slate-400">
                                        Emisión: {new Date(selectedIngreso.fecha_emision).toLocaleDateString('es-PE')}
                                    </span>
                                </div>
                                <h3 className="text-xl font-semibold font-mono text-slate-900 tracking-tight">
                                    {selectedIngreso.serie_comprobante}-{selectedIngreso.num_comprobante}
                                </h3>
                                <p className="text-xs text-slate-500 font-medium mt-0.5">
                                    Proveedor: <strong className="text-slate-800">
                                        {selectedIngreso.proveedor?.nombre_razon_social || selectedIngreso.proveedor?.razon_social}
                                    </strong> (RUC: {selectedIngreso.proveedor?.num_documento || selectedIngreso.proveedor?.ruc})
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={closeDetails}
                                className="w-8 h-8 rounded-xl text-slate-400 hover:bg-slate-100 flex items-center justify-center cursor-pointer"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        {/* Tabla de Artículos Ingresados */}
                        <div className="flex-1 overflow-y-auto mb-4 border border-slate-100 rounded-lg [scrollbar-width:thin]">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 text-slate-400 uppercase text-xs font-semibold border-b border-slate-100">
                                    <tr>
                                        <th className="py-2.5 px-3 font-medium">Artículo / Producto</th>
                                        <th className="py-2.5 px-2 text-center font-medium">Empaque</th>
                                        <th className="py-2.5 px-2 text-center font-medium">Cant.</th>
                                        <th className="py-2.5 px-3 text-right font-medium">Costo Unit.</th>
                                        <th className="py-2.5 px-3 text-right font-medium">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 font-semibold text-slate-700">
                                    {selectedIngreso.detalles && selectedIngreso.detalles.length > 0 ? (
                                        selectedIngreso.detalles.map((d, index) => {
                                            const cant = Number(d.cantidad || 0);
                                            const costo = Number(d.precio_costo || d.costo_unitario || 0);
                                            const subtotal = Number(d.subtotal || cant * costo);

                                            return (
                                                <tr key={index}>
                                                    <td className="py-2.5 px-3 font-bold text-slate-800">
                                                        {d.producto?.nombre || 'Producto Registrado'}
                                                    </td>
                                                    <td className="py-2.5 px-2 text-center">
                                                        <span className="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-xs font-bold">
                                                            {d.presentacion?.presentacion?.nombre || d.presentacion?.nombre || 'Unidad Base'}
                                                        </span>
                                                    </td>
                                                    <td className="py-2.5 px-2 text-center font-mono font-bold text-slate-800">
                                                        +{cant}
                                                    </td>
                                                    <td className="py-2.5 px-3 text-right font-mono text-slate-600">
                                                        {currency.format(costo)}
                                                    </td>
                                                    <td className="py-2.5 px-3 text-right font-mono font-semibold text-slate-900">
                                                        {currency.format(subtotal)}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    ) : (
                                        <tr>
                                            <td colSpan="5" className="py-8 text-center text-slate-400 text-xs">
                                                No hay detalles específicos cargados para esta compra.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Liquidación de Inversión */}
                        <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg flex justify-between items-center mb-4 text-xs shrink-0">
                            <span className="text-slate-400 uppercase font-semibold text-xs">
                                Total Facturado de Compra:
                            </span>
                            <span className="text-xl font-semibold font-mono text-slate-900">
                                {currency.format(Number(selectedIngreso.total_ingreso || selectedIngreso.total || 0))}
                            </span>
                        </div>

                        <div className="flex justify-end gap-2 shrink-0">
                            <button
                                type="button"
                                onClick={closeDetails}
                                className="volumen-marca h-10 px-5 rounded-xl text-on-brand font-bold text-xs transition cursor-pointer hover:brightness-105"
                            >
                                Cerrar Ventana
                            </button>
                        </div>
                </div>
                )}
            </Dialog>
        </AuthenticatedLayout>
    );
}