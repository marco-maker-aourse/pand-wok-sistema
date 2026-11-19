import React, { useState, useEffect, useMemo } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, Dialog, EmptyState, IconButton, buttonClasses } from '@/Components/ui';
import { Head, useForm, router, Link } from '@inertiajs/react';
import {
    Search,
    Trash2,
    CheckCircle2,
    AlertCircle,
    ArrowLeft,
    Boxes,
    Calendar,
    FileText,
    Loader2,
    X,
    ClipboardList,
    AlertTriangle,
    Tag
} from 'lucide-react';

export default function Create({ auth, productos = [], motivos = [] }) {
    const [busquedaProd, setBusquedaProd] = useState('');
    const [carrito, setCarrito] = useState([]);

    // Modales Propios del Sistema
    const [modalCargando, setModalCargando] = useState(false);
    const [modalConfirmacion, setModalConfirmacion] = useState(false);
    const [modalExito, setModalExito] = useState(null);
    const [modalAlerta, setModalAlerta] = useState(null);

    const listaMotivos = motivos.length > 0 ? motivos : [
        'MERMA',
        'VENCIMIENTO',
        'ROTURA / DETERIORO',
        'AJUSTE DE INVENTARIO',
        'CONSUMO INTERNO'
    ];

    const { data, setData, post, processing } = useForm({
        motivo: listaMotivos[0],
        observacion: '',
        detalles: []
    });

    useEffect(() => {
        setData('detalles', carrito);
    }, [carrito]);

    const formatPresentacionLabel = (nombre, factor) => {
        const f = Math.round(Number(factor) || 1);
        if (f <= 1) return nombre || 'Unidad';
        return `${nombre} (x${f})`;
    };

    // Agregar producto para dar de baja
    const agregarAlCarrito = (producto) => {
        const uid = `${producto.id_producto}_${Date.now()}`;
        const listaPres = producto.presentaciones || [];
        const presDefecto = listaPres[0] || null;

        setCarrito([
            {
                uid: uid,
                id_producto: producto.id_producto,
                id_prod_pres: presDefecto ? presDefecto.id_prod_pres : null,
                nombre: producto.nombre,
                presentaciones: listaPres,
                factor_conversion: presDefecto ? Number(presDefecto.factor_conversion) : 1,
                cantidad: 1,
                lote: producto.lote_recomendado?.codigo_lote || producto.lotes?.[0]?.codigo_lote || '',
                lotes: producto.lotes || [],
                stock_maximo: producto.stock_actual ?? 999
            },
            ...carrito
        ]);
    };

    const cambiarPresentacion = (uid, idProdPres) => {
        setCarrito(
            carrito.map((item) => {
                if (item.uid === uid) {
                    const pres = item.presentaciones.find((p) => String(p.id_prod_pres) === String(idProdPres));
                    return {
                        ...item,
                        id_prod_pres: pres ? pres.id_prod_pres : item.id_prod_pres,
                        factor_conversion: pres ? Number(pres.factor_conversion) : 1
                    };
                }
                return item;
            })
        );
    };

    // Escritura directa de cantidad (soporta 1000, 24, etc.) sin interferencia del scroll
    const actualizarCantidadDirecta = (uid, val) => {
        const cantLimpia = val.replace(/\D/g, '');
        const nuevaCant = cantLimpia === '' ? '' : parseInt(cantLimpia, 10);

        setCarrito(
            carrito.map((item) =>
                item.uid === uid ? { ...item, cantidad: nuevaCant } : item
            )
        );
    };

    const validarCantidadOnBlur = (uid) => {
        setCarrito(
            carrito.map((item) => {
                if (item.uid === uid) {
                    const cant = Number(item.cantidad) || 1;
                    return { ...item, cantidad: Math.max(1, cant) };
                }
                return item;
            })
        );
    };

    const actualizarLote = (uid, valor) => {
        setCarrito(
            carrito.map((item) =>
                item.uid === uid ? { ...item, lote: valor.toUpperCase() } : item
            )
        );
    };

    const removerDelCarrito = (uid) => setCarrito(carrito.filter((item) => item.uid !== uid));
    const totalUnidades = carrito.reduce(
        (sum, item) => sum + (Number(item.cantidad) || 0) * item.factor_conversion,
        0
    );

    const iniciarSalida = (e) => {
        e.preventDefault();

        if (carrito.length === 0) {
            setModalAlerta({
                titulo: 'Lista de baja vacía',
                mensaje: 'Seleccione al menos un producto del inventario para registrar la salida.'
            });
            return;
        }

        if (!data.observacion.trim()) {
            setModalAlerta({
                titulo: 'Justificación requerida',
                mensaje: 'Ingrese el motivo u observación detallada de la baja para la auditoría de Kardex.',
                sugerencia: 'Indique número de acta, rotura en descarga o fecha de vencimiento detectada.'
            });
            return;
        }

        setModalConfirmacion(true);
    };

    const confirmarSalida = () => {
        setModalConfirmacion(false);
        setModalCargando(true);

        post('/salidas', {
            preserveScroll: true,
            onSuccess: () => {
                setModalCargando(false);
                setModalExito({
                    motivo: data.motivo,
                    totalUnidades: totalUnidades,
                    observacion: data.observacion,
                    articulos: carrito.length
                });
            },
            onError: (errs) => {
                setModalCargando(false);
                const msg = Object.values(errs)[0] || 'Ocurrió un error al procesar la salida en el servidor.';
                setModalAlerta({
                    titulo: 'No se pudo procesar la salida',
                    mensaje: msg
                });
            }
        });
    };

    const productosFiltrados = useMemo(() => {
        if (!busquedaProd.trim()) return productos;
        const q = busquedaProd.toLowerCase().trim();
        return productos.filter(
            (p) =>
                (p.nombre || '').toLowerCase().includes(q) ||
                (p.codigo_barras && p.codigo_barras.includes(q))
        );
    }, [productos, busquedaProd]);

    return (
        <AuthenticatedLayout header="Nuevo ajuste de inventario">
            <Head title="Nuevo ajuste de inventario" />

            <div className="h-[calc(100vh-6rem)] lg:h-[calc(100vh-7.5rem)] flex flex-col lg:flex-row gap-5">
                {/* PANEL IZQUIERDO: PRODUCTOS DISPONIBLES EN STOCK */}
                <div className="flex-1 flex flex-col bg-white rounded-2xl p-4 sm:p-5 overflow-hidden shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="flex items-center justify-between mb-4">
                        <div className="relative flex-1 mr-3">
                            <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5 pointer-events-none" />
                            <input
                                type="text"
                                placeholder="Buscar producto en stock por nombre o código..."
                                className="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm placeholder:text-slate-400 transition"
                                value={busquedaProd}
                                onChange={(e) => setBusquedaProd(e.target.value)}
                                autoFocus
                            />
                        </div>
                        <Link href="/salidas" className={buttonClasses({ variant: 'secondary', className: 'shrink-0' })}>
                            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                            <span>Historial</span>
                        </Link>
                    </div>

                    <div className="flex-1 overflow-y-auto pr-1 pb-2 [scrollbar-width:thin]">
                        {productosFiltrados.length > 0 ? (
                            <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                                {productosFiltrados.map((prod) => {
                                    const agotado = (prod.stock_actual ?? 0) <= 0;

                                    return (
                                        <button
                                            type="button"
                                            key={prod.id_producto}
                                            disabled={agotado}
                                            onClick={() => agregarAlCarrito(prod)}
                                            className={`group flex flex-col justify-between rounded-xl p-3.5 text-left ring-1 select-none ${
                                                agotado
                                                    ? 'cursor-not-allowed bg-slate-50 opacity-50 ring-slate-200/60'
                                                    : 'elevable cursor-pointer bg-white ring-slate-200 hover:ring-rose-200'
                                            }`}
                                        >
                                            <div>
                                                <span className="volumen-suave mb-2 grid h-8 w-8 place-items-center rounded-lg text-xs font-semibold text-brand-ink" aria-hidden="true">
                                                    {prod.nombre?.charAt(0).toUpperCase()}
                                                </span>
                                                <h3 className="font-semibold text-xs leading-snug line-clamp-2 text-slate-800 group-hover:text-slate-900 transition-colors">
                                                    {prod.nombre}
                                                </h3>
                                                <div className="mt-2 flex items-center gap-1.5">
                                                    <span className="bg-slate-100 text-slate-700 text-xs font-bold px-2 py-0.5 rounded-md uppercase">
                                                        Stock: {prod.stock_actual ?? 0}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="flex justify-between items-end mt-3 pt-2 border-t border-slate-100">
                                                <span className="text-xs font-bold text-slate-400">
                                                    {prod.presentaciones?.length || 1} empaque{prod.presentaciones?.length === 1 ? '' : 's'}
                                                </span>
                                                {!agotado && (
                                                    <span className="w-6 h-6 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-xs font-bold group-hover:bg-rose-600 group-hover:text-white transition">
                                                        -
                                                    </span>
                                                )}
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        ) : (
                            <EmptyState icon={Search} title="No se encontraron artículos" description="Prueba con otra palabra clave en el buscador." />
                        )}
                    </div>
                </div>

                {/* PANEL DERECHO: MOTIVO, OBSERVACIÓN Y LISTA DE BAJA */}
                <div className="w-full lg:w-[460px] flex flex-col bg-white rounded-2xl overflow-hidden shrink-0 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    {/* Configuración de la Salida */}
                    <div className="p-4 border-b border-slate-100 bg-slate-50/60 space-y-3">
                        <div>
                            <label className="block text-xs font-semibold text-slate-500 mb-1 flex items-center gap-1.5">
                                <AlertTriangle className="w-3.5 h-3.5 text-amber-500" />
                                <span>Motivo de la Salida / Baja *</span>
                            </label>
                            <div className="grid grid-cols-2 gap-1.5">
                                {listaMotivos.map((m) => {
                                    const seleccionado = data.motivo === m;
                                    return (
                                        <button
                                            key={m}
                                            type="button"
                                            onClick={() => setData('motivo', m)}
                                            className={`py-2 px-2.5 rounded-xl text-xs font-semibold tracking-tight text-center border transition cursor-pointer ${
                                                seleccionado
                                                    ? 'volumen-marca text-on-brand border-transparent'
                                                    : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300'
                                            }`}
                                        >
                                            {m}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-500 mb-1 flex items-center gap-1.5">
                                <FileText className="w-3.5 h-3.5 text-slate-400" />
                                <span>Justificación para Kardex *</span>
                            </label>
                            <textarea
                                rows={2}
                                placeholder="Ej: Merma en transporte, empaque deteriorado en anaquel, ajuste físico..."
                                className="w-full text-xs font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl p-2.5 resize-none transition"
                                value={data.observacion}
                                onChange={(e) => setData('observacion', e.target.value)}
                                required
                            />
                        </div>
                    </div>

                    {/* Lista de Productos Seleccionados para Salida */}
                    <div className="flex-1 overflow-y-auto p-3.5 space-y-2.5 [scrollbar-width:thin]">
                        {carrito.length === 0 ? (
                            <EmptyState icon={ClipboardList} title="Lista de baja vacía" description="Elige productos del catálogo para agregarlos a la salida." />
                        ) : (
                            carrito.map((item) => (
                                <div
                                    key={item.uid}
                                    className="bg-white p-3 rounded-2xl space-y-2 relative shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]"
                                >
                                    <div className="flex justify-between items-start">
                                        <div className="min-w-0 pr-2">
                                            <p className="font-bold text-xs text-slate-800 truncate">{item.nombre}</p>
                                            <span className="text-xs font-semibold text-rose-600 uppercase">
                                                Descuento: -{(Number(item.cantidad) || 0) * item.factor_conversion} un. base
                                            </span>
                                        </div>
                                        <IconButton icon={Trash2} label={`Quitar ${item.nombre}`} onClick={() => removerDelCarrito(item.uid)} />
                                    </div>

                                    <div className="grid grid-cols-12 gap-2 items-center">
                                        {/* Presentación */}
                                        <div className="col-span-5">
                                            <select
                                                className="w-full text-xs font-bold text-slate-700 border border-slate-200 rounded-lg py-1 px-1.5 bg-white"
                                                value={item.id_prod_pres || ''}
                                                onChange={(e) => cambiarPresentacion(item.uid, e.target.value)}
                                            >
                                                {item.presentaciones.map((p) => (
                                                    <option key={p.id_prod_pres} value={p.id_prod_pres}>
                                                        {formatPresentacionLabel(p.nombre, p.factor_conversion)}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        {/* ESCRITURA DIRECTA DE CANTIDAD (Sin rueda de ratón) */}
                                        <div className="col-span-3">
                                            <input
                                                type="text"
                                                value={item.cantidad}
                                                onWheel={(e) => e.target.blur()}
                                                onChange={(e) => actualizarCantidadDirecta(item.uid, e.target.value)}
                                                onBlur={() => validarCantidadOnBlur(item.uid)}
                                                className="w-full text-xs font-mono font-semibold text-center text-slate-900 bg-white border border-slate-200 rounded-lg py-1 px-1"
                                                placeholder="1"
                                                title="Cantidad a descontar"
                                            />
                                        </div>

                                        {/* Lote */}
                                        <div className="col-span-4">
                                            <input
                                                type="text"
                                                placeholder="Lote (opcional)"
                                                value={item.lote}
                                                onChange={(e) => actualizarLote(item.uid, e.target.value)}
                                                className="w-full text-xs font-mono font-bold uppercase bg-white border border-slate-200 rounded-lg py-1 px-2"
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {/* TOTAL Y ACCIÓN */}
                    <div className="p-4 bg-slate-50 border-t border-slate-200/80">
                        <form onSubmit={iniciarSalida} className="space-y-3">
                            <div className="flex justify-between items-baseline">
                                <span className="text-xs font-semibold text-slate-500">
                                    Total Unidades Físicas:
                                </span>
                                <span className="text-2xl font-semibold text-rose-600 font-mono">
                                    -{totalUnidades} un.
                                </span>
                            </div>

                            <Button type="submit" variant="danger" size="lg" block icon={CheckCircle2} disabled={processing || carrito.length === 0}>
                                Dar de baja y descontar en kardex
                            </Button>
                        </form>
                    </div>
                </div>
            </div>

            {/* MODAL 1: CARGANDO */}
            <Dialog open={Boolean(modalCargando)} onClose={() => {}} size="sm" dismissible={false}>
                {(modalCargando) && (
                <div className="p-6 flex flex-col items-center text-center">
                        <Loader2 className="w-10 h-10 animate-spin text-rose-600 mb-3" />
                        <h4 className="text-sm font-semibold text-slate-900">Procesando Salida</h4>
                        <p className="text-xs text-slate-500 mt-1 font-medium">
                            Descontando existencias y registrando el movimiento de salida en Kardex...
                        </p>
                </div>
                )}
            </Dialog>

            {/* MODAL 2: CONFIRMACIÓN */}
            <Dialog open={Boolean(modalConfirmacion)} onClose={() => setModalConfirmacion(false)} size="md">
                {(modalConfirmacion) && (
                <div className="p-6">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                            <h3 className="text-sm font-semibold text-slate-900">
                                Confirmar Baja de Mercadería
                            </h3>
                            <IconButton icon={X} label="Cerrar" onClick={() => setModalConfirmacion(false)} />
                        </div>

                        <div className="space-y-3 mb-5">
                            <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-1.5 text-xs">
                                <p className="flex justify-between text-slate-600">
                                    <span>Motivo Declarado:</span>
                                    <span className="font-semibold text-rose-600 uppercase">{data.motivo}</span>
                                </p>
                                <p className="flex justify-between text-slate-600">
                                    <span>Líneas Afectadas:</span>
                                    <span className="font-bold text-slate-900">{carrito.length} productos</span>
                                </p>
                                <p className="flex justify-between text-slate-600">
                                    <span>Total Unidades:</span>
                                    <span className="font-mono font-semibold text-slate-900">-{totalUnidades} un.</span>
                                </p>
                            </div>
                            <div className="p-3 bg-amber-50 border border-amber-200/80 rounded-lg text-xs text-amber-800">
                                <AlertTriangle className="mr-1 inline h-3.5 w-3.5 align-[-2px]" aria-hidden="true" /><span className="font-bold">Aviso de auditoría:</span> Esta operación modificará el stock actual del inventario de forma inmediata.
                            </div>
                        </div>

                        <div className="flex gap-2.5">
                            <Button variant="secondary" className="flex-1" onClick={() => setModalConfirmacion(false)}>Revisar</Button>
                            <Button variant="danger" className="flex-1" onClick={confirmarSalida}>Confirmar baja</Button>
                        </div>
                </div>
                )}
            </Dialog>

            {/* MODAL 3: ÉXITO Y REDIRECCIÓN AL HISTORIAL */}
            <Dialog open={Boolean(modalExito)} onClose={() => {}} size="sm" dismissible={false}>
                {(modalExito) && (
                <div className="p-6 text-center">
                        <div className="w-12 h-12 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-3">
                            <CheckCircle2 className="w-6 h-6" />
                        </div>

                        <span className="text-xs font-semibold text-rose-700 bg-rose-50 px-2.5 py-0.5 rounded-full inline-block mb-1">
                            Baja Procesada
                        </span>

                        <h3 className="text-lg font-semibold text-slate-900 tracking-tight">
                            Motivo: {modalExito.motivo}
                        </h3>
                        <p className="text-xs text-slate-500 font-medium mt-0.5 italic">
                            "{modalExito.observacion}"
                        </p>

                        <div className="my-4 p-3 bg-slate-50 border border-slate-200 rounded-lg font-mono text-xs space-y-1.5 text-left">
                            <div className="flex justify-between text-slate-500">
                                <span>Artículos descontados:</span>
                                <span className="font-bold text-slate-800">{modalExito.articulos} líneas</span>
                            </div>
                            <div className="flex justify-between text-slate-500 border-t border-slate-200 pt-1.5">
                                <span className="font-bold text-slate-800">Unidades de baja:</span>
                                <span className="font-semibold text-rose-600 text-sm">
                                    -{modalExito.totalUnidades} un.
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            onClick={() => {
                                setModalExito(null);
                                router.visit('/salidas');
                            }}
                            className="volumen-marca text-on-brand w-full h-10 rounded-xl font-bold text-xs transition cursor-pointer hover:brightness-105"
                        >
                            Ir al Historial de Salidas
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
                        {modalAlerta.sugerencia && (
                            <div className="mt-3 p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-500">
                                <span className="font-bold">Sugerencia:</span> {modalAlerta.sugerencia}
                            </div>
                        )}
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