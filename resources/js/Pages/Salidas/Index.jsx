import React, { useState, useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageHeader, Dialog, PaginacionServidor, Stat } from '@/Components/ui';
import { Head, Link } from '@inertiajs/react';
import {
    Search,
    Eye,
    X,
    Calendar,
    AlertTriangle,
    Layers,
    Plus,
    FileText,
    Boxes
} from 'lucide-react';

export default function Index({ auth, salidas = [] }) {
    const [busqueda, setBusqueda] = useState('');
    const [filtroMotivo, setFiltroMotivo] = useState('todos');
    const [selectedSalida, setSelectedSalida] = useState(null);

    const listaSalidas = useMemo(() => {
        if (Array.isArray(salidas)) return salidas;
        if (salidas?.data) return salidas.data;
        return [];
    }, [salidas]);

    const metricas = useMemo(() => {
        let mermas = 0;
        let vencimientos = 0;
        let otros = 0;

        listaSalidas.forEach((s) => {
            const mot = (s.motivo || '').toUpperCase();
            if (mot.includes('MERMA') || mot.includes('ROTURA')) mermas++;
            else if (mot.includes('VENCIMIENTO')) vencimientos++;
            else otros++;
        });

        return {
            total: listaSalidas.length,
            mermas,
            vencimientos,
            otros
        };
    }, [listaSalidas]);

    const salidasFiltradas = useMemo(() => {
        return listaSalidas.filter((s) => {
            const motivo = (s.motivo || '').toLowerCase();
            const obs = (s.observacion || '').toLowerCase();
            const num = `salida #${s.id_salida}`.toLowerCase();
            const q = busqueda.toLowerCase().trim();

            const matchTexto = !q || motivo.includes(q) || obs.includes(q) || num.includes(q);

            let matchMotivo = true;
            if (filtroMotivo === 'MERMA') matchMotivo = motivo.includes('merma');
            else if (filtroMotivo === 'VENCIMIENTO') matchMotivo = motivo.includes('vencimiento');
            else if (filtroMotivo === 'ROTURA') matchMotivo = motivo.includes('rotura') || motivo.includes('deterioro');
            else if (filtroMotivo === 'AJUSTE') matchMotivo = motivo.includes('ajuste');

            return matchTexto && matchMotivo;
        });
    }, [listaSalidas, busqueda, filtroMotivo]);

    const openDetails = (salida) => setSelectedSalida(salida);
    const closeDetails = () => setSelectedSalida(null);

    const getBadgeStyle = (motivo = '') => {
        const m = motivo.toUpperCase();
        if (m.includes('VENCIMIENTO')) return 'bg-rose-50 text-rose-700 border-rose-200';
        if (m.includes('MERMA') || m.includes('ROTURA')) return 'bg-amber-50 text-amber-700 border-amber-200';
        if (m.includes('AJUSTE')) return 'bg-brand-soft text-brand-ink border-brand-border';
        return 'bg-slate-100 text-slate-700 border-slate-200';
    };

    return (
        <AuthenticatedLayout header="Ajustes de inventario">
            <Head title="Ajustes de inventario" />

            <div className="space-y-4">
                <PageHeader
                    breadcrumbs={[{ label: 'Inventario' }, { label: 'Salidas y ajustes' }]}
                    title="Ajustes de inventario"
                    description="Registro de bajas por roturas, vencimiento y ajustes con trazabilidad en Kardex."
                    actions={
                        <>
                        <Link
                            href="/salidas/create"
                            className="bg-brand text-on-brand hover:bg-brand-hover h-11 px-5 rounded-lg font-semibold text-xs transition flex items-center gap-2 cursor-pointer"
                        >
                            <Plus className="w-4 h-4 stroke-[3]" />
                            <span>Registrar Nueva Salida</span>
                        </Link>
                        </>
                    }
                />

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Stat destacado label="Ajustes registrados" value={Number(salidas?.total ?? listaSalidas.length).toLocaleString('es-PE')} hint="Total en el sistema" icon={FileText} />
                    <Stat label="Mermas y roturas" value={metricas.mermas} hint="En esta página" icon={Boxes} iconTone="warning" />
                    <Stat label="Por vencimiento" value={metricas.vencimientos} hint="En esta página" icon={Calendar} iconTone="danger" />
                </div>

                {/* 3. BARRA DE HERRAMIENTAS Y FILTROS */}
                <div className="bg-white rounded-2xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="relative w-full md:w-80">
                        <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-3 pointer-events-none" />
                        <input
                            type="text"
                            placeholder="Buscar por motivo, observación o #Salida..."
                            className="w-full pl-10 pr-3.5 py-2 text-sm bg-white border border-slate-200 rounded-lg transition"
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                        />
                    </div>

                    <div className="flex items-center gap-1.5 w-full md:w-auto bg-slate-100/80 p-1 rounded-lg">
                        {[
                            { id: 'todos', label: `Todos (${listaSalidas.length})` },
                            { id: 'MERMA', label: 'Mermas' },
                            { id: 'VENCIMIENTO', label: 'Vencidos' },
                            { id: 'ROTURA', label: 'Roturas' },
                            { id: 'AJUSTE', label: 'Ajustes' }
                        ].map((filtro) => {
                            const activo = filtroMotivo === filtro.id;
                            return (
                                <button
                                    key={filtro.id}
                                    type="button"
                                    onClick={() => setFiltroMotivo(filtro.id)}
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

                {/* 4. TABLA PRINCIPAL DE SALIDAS */}
                <div className="bg-white rounded-2xl overflow-hidden shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left whitespace-nowrap text-xs">
                            <thead className="bg-slate-50/70 border-b border-slate-100 text-xs font-semibold text-slate-400">
                                <tr>
                                    <th className="px-4 py-2.5 font-medium">Fecha y Hora</th>
                                    <th className="px-4 py-2.5 font-medium">Código Salida</th>
                                    <th className="px-4 py-2.5 font-medium">Motivo de Baja</th>
                                    <th className="px-4 py-2.5 font-medium">Observación / Justificación</th>
                                    <th className="px-4 py-2.5 text-center font-medium">Estado</th>
                                    <th className="px-4 py-2.5 text-right font-medium">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 font-semibold text-slate-700">
                                {salidasFiltradas.length > 0 ? (
                                    salidasFiltradas.map((s) => {
                                        const fecha = s.fecha_salida ? new Date(s.fecha_salida) : new Date();

                                        return (
                                            <tr key={s.id_salida} className="hover:bg-brand-soft/40 transition-colors">
                                                <td className="px-4 py-2.5">
                                                    <span className="font-bold text-slate-800">
                                                        {fecha.toLocaleDateString('es-PE')}
                                                    </span>
                                                    <span className="text-xs text-slate-400 block font-mono">
                                                        {fecha.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' })}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-2.5 font-mono font-semibold text-slate-900 text-xs">
                                                    Salida #{s.id_salida}
                                                </td>

                                                <td className="px-4 py-2.5">
                                                    <span className={`inline-block px-2.5 py-0.5 rounded-md text-xs font-semibold border ${getBadgeStyle(s.motivo)}`}>
                                                        {s.motivo || 'MERMA'}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-2.5">
                                                    <p className="font-medium text-slate-700 text-xs truncate max-w-sm" title={s.observacion}>
                                                        {s.observacion || 'Sin observaciones registradas'}
                                                    </p>
                                                    <span className="text-xs text-slate-400 block font-mono">
                                                        Por: {s.usuario?.nombres || s.usuario?.name || 'Administrador'}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-2.5 text-center">
                                                    <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        {s.estado ? 'Procesado' : 'Aprobado'}
                                                    </span>
                                                </td>

                                                <td className="px-4 py-2.5 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => openDetails(s)}
                                                        className="h-8 px-2.5 rounded-xl border border-slate-200 hover:bg-slate-100 text-slate-600 font-bold text-xs inline-flex items-center gap-1.5 transition cursor-pointer"
                                                        title="Ver productos dados de baja"
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
                                                No se encontraron salidas registradas
                                            </p>
                                            <p className="text-xs text-slate-400 mt-0.5">
                                                Haz clic en "Registrar Nueva Salida" para dar de baja mercadería.
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                    <PaginacionServidor paginador={salidas} entidad="ajustes" />
                </div>
            </div>

            {/* MODAL PROPIO: DETALLES DE SALIDA (ESTILO ERP LIMPIO) */}
            <Dialog open={Boolean(selectedSalida)} onClose={closeDetails} size="xl">
                {(selectedSalida) && (
                <div className="p-6 sm:p-7 flex flex-col">
                        <div className="flex items-start justify-between pb-4 border-b border-slate-100 mb-4 shrink-0">
                            <div>
                                <div className="flex items-center gap-2 mb-1.5">
                                    <span className={`px-2.5 py-0.5 rounded-md text-xs font-semibold border ${getBadgeStyle(selectedSalida.motivo)}`}>
                                        {selectedSalida.motivo}
                                    </span>
                                    <span className="text-xs font-mono text-slate-400">
                                        Fecha: {new Date(selectedSalida.fecha_salida || Date.now()).toLocaleDateString('es-PE')}
                                    </span>
                                </div>
                                <h3 className="text-xl font-semibold font-mono text-slate-900 tracking-tight">
                                    Salida #{selectedSalida.id_salida}
                                </h3>
                                <p className="text-xs text-slate-500 font-medium mt-0.5 italic">
                                    "{selectedSalida.observacion || 'Sin detalles'}"
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

                        {/* Tabla de Artículos Afectados */}
                        <div className="flex-1 overflow-y-auto mb-4 border border-slate-100 rounded-lg [scrollbar-width:thin]">
                            <table className="w-full text-left text-xs">
                                <thead className="bg-slate-50 text-slate-400 uppercase text-xs font-semibold border-b border-slate-100">
                                    <tr>
                                        <th className="py-2.5 px-3 font-medium">Artículo / Producto</th>
                                        <th className="py-2.5 px-2 text-center font-medium">Empaque</th>
                                        <th className="py-2.5 px-2 text-center font-medium">Lote</th>
                                        <th className="py-2.5 px-3 text-right font-medium">Cant. Baja</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 font-semibold text-slate-700">
                                    {selectedSalida.detalles && selectedSalida.detalles.length > 0 ? (
                                        selectedSalida.detalles.map((d, index) => (
                                            <tr key={index}>
                                                <td className="py-2.5 px-3 font-bold text-slate-800">
                                                    {d.presentacion?.producto?.nombre || d.producto?.nombre || 'Producto Registrado'}
                                                </td>
                                                <td className="py-2.5 px-2 text-center">
                                                    <span className="bg-slate-100 text-slate-600 px-2 py-0.5 rounded text-xs font-bold">
                                                        {d.presentacion?.presentacion?.nombre || d.presentacion?.nombre || 'Unidad Base'}
                                                    </span>
                                                </td>
                                                <td className="py-2.5 px-2 text-center font-mono font-bold text-slate-600 text-xs">
                                                    {d.lote || '—'}
                                                </td>
                                                <td className="py-2.5 px-3 text-right font-mono font-semibold text-rose-600 text-sm">
                                                    -{Number(d.cantidad || 0)}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="4" className="py-8 text-center text-slate-400 text-xs">
                                                No se cargaron los detalles de los productos para esta salida.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex justify-between items-center pt-2 border-t border-slate-100 shrink-0 text-xs">
                            <span className="font-bold text-slate-500">
                                Líneas de producto afectadas: {selectedSalida.detalles?.length || 0}
                            </span>
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