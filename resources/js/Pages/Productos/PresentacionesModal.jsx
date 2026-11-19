import React, { useEffect, useState, useMemo } from 'react';
import Modal from '@/Components/Modal';
import { Dialog } from '@/Components/ui';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import {
    Layers,
    Plus,
    Trash2,
    X,
    CheckCircle2,
    AlertCircle,
    AlertTriangle,
    Loader2,
    Boxes,
    Pencil,
    Sparkles,
    Check,
    Tag,
    ShoppingBag
} from 'lucide-react';

const formatMoneda = (val) => `S/ ${Number(val || 0).toFixed(2)}`;

/**
 * Atajos de presentación por tipo de producto del restaurante. En platos, cada
 * presentación es un tamaño con su propio precio (factor 1: no hay stock del plato).
 * En bebidas e insumos el factor convierte a la unidad base del Kardex.
 */
const PRESETS_POR_TIPO = {
    PREPARADO: [['PORCIÓN', 1, 'Plato regular'], ['PERSONAL', 1, 'Tamaño individual'], ['FAMILIAR', 1, 'Para varias personas'], ['PARA COMPARTIR', 1, 'Fuente o piqueo']],
    DIRECTO: [['UNIDAD', 1, 'Botella, lata o vaso'], ['VASO', 1, 'Servido en vaso'], ['JARRA', 1, 'Jarra para la mesa'], ['BALDE', 6, '6 unidades (p. ej. cervezas)']],
    INSUMO: [['UNIDAD', 1, 'Unidad base'], ['PAQUETE', 12, 'Compra por paquete'], ['CAJA', 24, 'Compra por caja'], ['SACO', 50, 'Compra por saco']],
};

export default function PresentacionesModal({
    producto,
    show,
    onClose,
    catalogoPresentaciones = [],
    unidades = []
}) {
    const { data, setData, reset } = useForm({
        id_presentacion: '',
        factor_conversion: 1,
        precio_venta: '',
    });

    const [listaAsignadas, setListaAsignadas] = useState([]);
    const [catalogoLocal, setCatalogoLocal] = useState(catalogoPresentaciones);
    const [nuevoEmpaque, setNuevoEmpaque] = useState('');
    const [creandoEmpaque, setCreandoEmpaque] = useState(false);
    const [saving, setSaving] = useState(false);
    const [presentacionEnEdicion, setPresentacionEnEdicion] = useState(null);

    // Modales nativos
    const [dialogo, setDialogo] = useState(null);
    const [presAEliminar, setPresAEliminar] = useState(null);

    const unidadBaseNombre = (producto?.unidad_base?.nombre || 'UNIDAD').toUpperCase();
    const factorNum = Math.max(Number(data.factor_conversion) || 1, 0.0001);
    const precioNum = Number(data.precio_venta || 0);
    const precioUnitarioEquivalente = precioNum > 0 ? (precioNum / factorNum) : 0;
    const esMayorista = factorNum > 1;

    // Buscar si ya existe la venta por unidad básica (factor = 1)
    const precioUnidadIndividual = useMemo(() => {
        const itemUnidad = listaAsignadas.find((i) => Number(i.factor_conversion) === 1);
        return itemUnidad ? Number(itemUnidad.precio_venta || 0) : null;
    }, [listaAsignadas]);

    // Cálculo de precio sugerido y ahorro por volumen
    const precioSugeridoMultiplicado = (precioUnidadIndividual && esMayorista)
        ? (precioUnidadIndividual * factorNum)
        : null;

    const ahorroPorUnidad = (precioUnidadIndividual && esMayorista && precioUnitarioEquivalente < precioUnidadIndividual)
        ? (precioUnidadIndividual - precioUnitarioEquivalente)
        : null;

    // Inicializar al abrir
    useEffect(() => {
        if (!show || !producto) return;
        setListaAsignadas(producto.presentaciones || []);
        setCatalogoLocal(catalogoPresentaciones);
        reset();
        setData('factor_conversion', 1);
        setNuevoEmpaque('');
        setPresentacionEnEdicion(null);
    }, [show, producto?.id_producto, catalogoPresentaciones]);

    if (!producto) return null;

    const handleClose = () => {
        reset();
        setPresentacionEnEdicion(null);
        onClose();
    };

    // Auto-detectar la cantidad a partir del texto del empaque (ej. "Caja x24" -> 24)
    const detectarCantidadDesdeNombre = (nombreTexto) => {
        if (!nombreTexto) return null;
        const texto = nombreTexto.toUpperCase();

        if (texto.includes('DOCENA')) return 12;
        if (texto.includes('SIXPACK') || texto.includes('SIX PACK')) return 6;
        if (texto.includes('UNIDAD') || texto.includes('SUELTO') || texto.includes('INDIVIDUAL')) return 1;

        // Buscar patrones como "X24", "X 24", "(24)", "× 24"
        const match = texto.match(/(?:X|\×|\()\s*(\d+)/i);
        if (match && match[1]) {
            return Number(match[1]);
        }
        return null;
    };

    // Al cambiar el selector manual de empaque
    const handleSeleccionarEmpaque = (idPres) => {
        setData('id_presentacion', idPres);
        const pres = catalogoLocal.find((p) => String(p.id_presentacion) === String(idPres));
        if (pres) {
            const cantDetectada = detectarCantidadDesdeNombre(pres.nombre);
            if (cantDetectada) {
                setData('factor_conversion', cantDetectada);
                // Si tenemos precio unitario, auto-sugerir
                if (precioUnidadIndividual && cantDetectada > 1) {
                    setData('precio_venta', Number((precioUnidadIndividual * cantDetectada).toFixed(2)));
                }
            }
        }
    };

    // Botones rápidos de 1 Clic (Unidad, Docena, Caja, etc.)
    const seleccionarPresetRapido = async (nombrePreset, cantidadPreset) => {
        const nombreUpper = nombrePreset.toUpperCase();
        setData('factor_conversion', cantidadPreset);

        // Calcular precio sugerido automáticamente
        if (precioUnidadIndividual && cantidadPreset > 1) {
            setData('precio_venta', Number((precioUnidadIndividual * cantidadPreset).toFixed(2)));
        } else if (cantidadPreset === 1 && !data.precio_venta && precioUnidadIndividual) {
            setData('precio_venta', precioUnidadIndividual);
        }

        // Buscar si ya existe en el catálogo
        let encontrado = catalogoLocal.find((p) => p.nombre.toUpperCase().includes(nombreUpper) || p.nombre.toUpperCase() === nombreUpper);

        if (encontrado) {
            setData('id_presentacion', encontrado.id_presentacion);
            return;
        }

        // Si no existe, crearlo al instante en el catálogo
        setCreandoEmpaque(true);
        try {
            const { data: response } = await axios.post('/presentaciones', { nombre: nombreUpper, estado: true }, { headers: { Accept: 'application/json' } });
            const item = response?.data ?? response;
            setCatalogoLocal((prev) => [...prev, item].sort((a, b) => a.nombre.localeCompare(b.nombre)));
            setData('id_presentacion', item.id_presentacion);
        } catch {
            // Si falla la creación rápida, no bloquea el formulario
        } finally {
            setCreandoEmpaque(false);
        }
    };

    // Crear empaque personalizado desde el input
    const crearPresentacionPersonalizada = async () => {
        const nombre = nuevoEmpaque.trim().toUpperCase();
        if (!nombre) return;

        setCreandoEmpaque(true);
        try {
            const { data: response } = await axios.post('/presentaciones', { nombre, estado: true }, { headers: { Accept: 'application/json' } });
            const item = response?.data ?? response;

            setCatalogoLocal((prev) => [...prev, item].sort((a, b) => a.nombre.localeCompare(b.nombre)));
            setData('id_presentacion', item.id_presentacion);
            
            const cant = detectarCantidadDesdeNombre(nombre);
            if (cant) setData('factor_conversion', cant);

            setNuevoEmpaque('');
        } catch (error) {
            const msg = error.response?.data?.errors ? Object.values(error.response.data.errors)[0][0] : 'NO SE PUDO CREAR EL TIPO DE EMPAQUE.';
            setDialogo({ tipo: 'error', titulo: 'ERROR AL CREAR EMPAQUE', mensaje: String(msg).toUpperCase() });
        } finally {
            setCreandoEmpaque(false);
        }
    };

    // Entrar en modo edición
    const editarPresentacion = (item) => {
        setPresentacionEnEdicion(item);
        setData({
            id_presentacion: item.id_presentacion ?? item.presentacion?.id_presentacion ?? '',
            factor_conversion: Number(item.factor_conversion) || 1,
            precio_venta: Number(item.precio_venta) || '',
        });
    };

    const cancelarEdicion = () => {
        setPresentacionEnEdicion(null);
        reset();
        setData('factor_conversion', 1);
    };

    // Guardar / Actualizar Presentación
    const submit = async (e) => {
        e.preventDefault();

        if (!data.id_presentacion) {
            setDialogo({ tipo: 'alerta', titulo: 'EMPAQUE REQUERIDO', mensaje: 'SELECCIONA EL TIPO DE EMPAQUE (EJ. UNIDAD, CAJA, PAQUETE).' });
            return;
        }

        if (!data.precio_venta || Number(data.precio_venta) <= 0) {
            setDialogo({ tipo: 'alerta', titulo: 'PRECIO INVÁLIDO', mensaje: 'ASIGNA UN PRECIO DE VENTA MAYOR A S/ 0.00.' });
            return;
        }

        setSaving(true);
        try {
            const { data: response } = await axios.post(`/productos/${producto.id_producto}/presentaciones`, data, { headers: { Accept: 'application/json' } });
            const saved = response?.data ?? response;

            setListaAsignadas((prev) => {
                const idx = prev.findIndex((item) => String(item.id_prod_pres) === String(saved.id_prod_pres));
                return idx === -1 ? [...prev, saved] : prev.map((item, i) => (i === idx ? saved : item));
            });

            reset();
            setData('factor_conversion', 1);
            setPresentacionEnEdicion(null);
            setDialogo({
                tipo: 'exito',
                titulo: presentacionEnEdicion ? 'PRECIO ACTUALIZADO' : 'EMPAQUE REGISTRADO',
                mensaje: 'EL PRECIO QUEDÓ LISTO Y DISPONIBLE EN EL PUNTO DE VENTA.'
            });
        } catch (error) {
            const msg = error.response?.data?.errors ? Object.values(error.response.data.errors)[0][0] : 'NO SE PUDO GUARDAR EL EMPAQUE.';
            setDialogo({ tipo: 'error', titulo: 'ERROR AL GUARDAR', mensaje: String(msg).toUpperCase() });
        } finally {
            setSaving(false);
        }
    };

    // Confirmar y Ejecutar Eliminación
    const ejecutarEliminarPres = () => {
        if (!presAEliminar) return;
        const target = presAEliminar;

        axios.delete(`/productos/presentaciones/${target.id_prod_pres}`, { headers: { Accept: 'application/json' } })
            .then(() => {
                setListaAsignadas((prev) => prev.filter((item) => String(item.id_prod_pres) !== String(target.id_prod_pres)));
                setPresAEliminar(null);
                setDialogo({
                    tipo: 'exito',
                    titulo: 'EMPAQUE RETIRADO',
                    mensaje: `SE RETIRÓ "${(target.presentacion?.nombre || 'LA PRESENTACIÓN').toUpperCase()}" DE ESTE PRODUCTO.`
                });
            })
            .catch(() => {
                setPresAEliminar(null);
                setDialogo({
                    tipo: 'error',
                    titulo: 'OPERACIÓN RESTRINGIDA',
                    mensaje: 'ESTE EMPAQUE TIENE COMPROBANTES O VENTAS REGISTRADAS.'
                });
            });
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="4xl">
            <div className="flex flex-col max-h-[92vh] bg-white rounded-3xl overflow-hidden shadow-2xl relative">
                {/* Cabecera Principal */}
                <div className="p-5 sm:p-6 bg-white border-b border-slate-100 flex justify-between items-start shrink-0">
                    <div className="flex items-center gap-3">
                        <span className="p-2.5 bg-blue-50 text-blue-600 rounded-2xl">
                            <Layers className="w-5 h-5" />
                        </span>
                        <div>
                            <h2 className="text-base font-black text-slate-900 tracking-tight">
                                Precios y presentaciones: {producto.nombre}
                            </h2>
                            <p className="text-xs text-slate-400 font-bold mt-0.5">
                                Stock controlado en almacén por: <strong className="text-slate-800">{unidadBaseNombre}</strong>
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        onClick={handleClose}
                        className="p-1 text-slate-400 hover:text-slate-600 rounded-lg transition cursor-pointer"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                {/* Banner de Ayuda si no tiene venta suelta */}
                {!precioUnidadIndividual && listaAsignadas.length === 0 && (
                    <div className="mx-6 mt-4 p-3.5 bg-amber-50 border border-amber-200 rounded-2xl flex items-center justify-between gap-3 text-xs text-amber-900">
                        <div>
                            <p className="font-black">Falta el precio de venta individual</p>
                            <p className="text-[11px] text-amber-700 font-medium">
                                Configura primero el precio de venta por <strong>1 {unidadBaseNombre.toLowerCase()}</strong> para que los cajeros puedan venderlo suelto.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => seleccionarPresetRapido('UNIDAD', 1)}
                            className="px-3 py-1.5 bg-amber-800 hover:bg-amber-900 text-white rounded-xl text-xs font-black tracking-wider transition shrink-0 cursor-pointer shadow-xs"
                        >
                            + Venta Suelta (x1)
                        </button>
                    </div>
                )}

                {/* Contenido en 2 Columnas */}
                <div className="flex-1 overflow-y-auto p-6 grid grid-cols-1 lg:grid-cols-12 gap-6 bg-slate-50/50 [scrollbar-width:thin]">
                    {/* COLUMNA 1: LISTADO DE PRECIOS ACTIVOS */}
                    <div className="lg:col-span-7 space-y-3">
                        <div className="flex items-center justify-between">
                            <span className="text-[10px] font-black uppercase tracking-wider text-slate-400">
                                Precios Activos ({listaAsignadas.length})
                            </span>
                            <span className="text-[10px] text-slate-400 font-bold uppercase">Listos para cobrar</span>
                        </div>

                        {listaAsignadas.length > 0 ? (
                            <div className="space-y-2.5">
                                {listaAsignadas.map((item) => {
                                    const factor = Number(item.factor_conversion || 1);
                                    const precio = Number(item.precio_venta || 0);
                                    const esSuelto = factor === 1;
                                    const precioUnitario = factor > 1 ? precio / factor : precio;

                                    return (
                                        <div
                                            key={item.id_prod_pres}
                                            className={`bg-white p-3.5 rounded-2xl border shadow-2xs hover:shadow-xs transition flex items-center justify-between gap-4 ${
                                                presentacionEnEdicion?.id_prod_pres === item.id_prod_pres
                                                    ? 'border-emerald-800 ring-2 ring-emerald-800/20'
                                                    : 'border-slate-200/80'
                                            }`}
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className={`w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 border font-black text-xs ${
                                                    esSuelto
                                                        ? 'bg-blue-50 border-blue-200 text-blue-700'
                                                        : 'bg-purple-50 border-purple-200 text-purple-700'
                                                }`}>
                                                    {esSuelto ? '1x' : `${factor}x`}
                                                </div>

                                                <div>
                                                    <div className="flex items-center gap-1.5">
                                                        <h4 className="font-black text-slate-900 text-xs">
                                                            {item.presentacion?.nombre || 'EMPAQUE'}
                                                        </h4>
                                                        {esSuelto && (
                                                            <span className="bg-blue-100 text-blue-800 text-[9px] font-black px-1.5 py-0.2 rounded uppercase">
                                                                Suelto
                                                            </span>
                                                        )}
                                                    </div>

                                                    <p className="text-[11px] text-slate-500 font-semibold mt-0.5">
                                                        {esSuelto
                                                            ? `Venta directa por 1 ${unidadBaseNombre.toLowerCase()}`
                                                            : `Contiene ${factor} ${unidadBaseNombre.toLowerCase()}s (Sale a ${formatMoneda(precioUnitario)} c/u)`}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-3">
                                                <div className="text-right">
                                                    <span className="block text-[8.5px] font-black uppercase tracking-wider text-slate-400">Precio</span>
                                                    <span className="font-mono font-black text-emerald-800 text-sm">
                                                        {formatMoneda(precio)}
                                                    </span>
                                                </div>

                                                <button
                                                    type="button"
                                                    onClick={() => editarPresentacion(item)}
                                                    className="p-1.5 text-slate-400 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition cursor-pointer"
                                                    title="Modificar precio o empaque"
                                                >
                                                    <Pencil className="w-3.5 h-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setPresAEliminar(item)}
                                                    className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                                                    title="Quitar empaque"
                                                >
                                                    <Trash2 className="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="p-8 bg-white rounded-2xl border-dashed text-center text-slate-400 space-y-1.5 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                                <Boxes className="w-8 h-8 mx-auto text-slate-300 mb-1" />
                                <p className="font-black text-xs text-slate-600">Sin precios de venta</p>
                                <p className="text-[11px] text-slate-400">
                                    Usa los botones rápidos de la derecha para fijar el precio suelto o por caja.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* COLUMNA 2: FORMULARIO ULTRA-RÁPIDO CON PRESETS */}
                    <div className="lg:col-span-5 bg-white p-5 rounded-3xl border border-slate-200 shadow-2xs flex flex-col justify-between">
                        <form onSubmit={submit} className="space-y-4">
                            <div className="border-b border-slate-100 pb-2.5">
                                <span className="text-[9px] font-black uppercase tracking-wider text-emerald-800 block">
                                    {presentacionEnEdicion ? 'Modificando Registro' : 'Fijar Precio de Venta'}
                                </span>
                                <h3 className="text-sm font-black text-slate-900 mt-0.5">
                                    {presentacionEnEdicion ? 'Actualizar precio o empaque' : 'Nueva modalidad de venta'}
                                </h3>
                            </div>

                            {/* BOTONES DE 1 CLIC (PRESETS): según el tipo de producto del restaurante */}
                            <div>
                                <label className="block text-[9px] font-black tracking-wider text-slate-600 mb-1.5">
                                    Presentaciones rápidas (elige una con 1 clic)
                                </label>
                                <div className="grid grid-cols-2 gap-1.5">
                                    {(PRESETS_POR_TIPO[producto?.tipo_producto] ?? PRESETS_POR_TIPO.DIRECTO).map(([nombre, factor, ayuda]) => (
                                        <button
                                            key={nombre}
                                            type="button"
                                            onClick={() => seleccionarPresetRapido(nombre, factor)}
                                            className="p-2 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-left transition cursor-pointer flex items-center gap-2"
                                        >
                                            {factor === 1 ? <ShoppingBag className="w-3.5 h-3.5 text-blue-600 shrink-0" /> : <Boxes className="w-3.5 h-3.5 text-amber-600 shrink-0" />}
                                            <div>
                                                <span className="block text-xs font-black text-slate-800 uppercase">{nombre}{factor > 1 ? ` (x${factor})` : ''}</span>
                                                <span className="text-[9px] text-slate-400 font-bold uppercase">{ayuda}</span>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* O seleccionar del catálogo completo */}
                            <div className="space-y-1.5 pt-1 border-t border-slate-100">
                                <label className="block text-[9px] font-black tracking-wider text-slate-600">
                                    O selecciona otro tipo de empaque
                                </label>
                                <select
                                    value={data.id_presentacion}
                                    onChange={(e) => handleSeleccionarEmpaque(e.target.value)}
                                    className="w-full text-xs font-black text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-3 uppercase transition cursor-pointer"
                                    required
                                >
                                    <option value="">Selecciona empaque...</option>
                                    {catalogoLocal.map((p) => (
                                        <option key={p.id_presentacion} value={p.id_presentacion}>
                                            {p.nombre.toUpperCase()}
                                        </option>
                                    ))}
                                </select>

                                {/* Crear empaque si no existe */}
                                <div className="flex gap-1.5 pt-1">
                                    <input 
                                        type="text"
                                        placeholder="CREAR OTRO: SACO, DISPLAY..."
                                        value={nuevoEmpaque}
                                        onChange={(e) => setNuevoEmpaque(e.target.value.toUpperCase())}
                                        className="flex-1 bg-white border border-slate-200 rounded-xl px-2.5 py-1 text-xs font-bold uppercase"
                                    />
                                    <button
                                        type="button"
                                        onClick={crearPresentacionPersonalizada}
                                        disabled={creandoEmpaque || !nuevoEmpaque.trim()}
                                        className="px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-black text-xs disabled:opacity-50 transition cursor-pointer"
                                    >
                                        + Agregar
                                    </button>
                                </div>
                            </div>

                            {/* Cantidad que contiene */}
                            <div>
                                <label className="block text-[9px] font-black tracking-wider text-slate-600 mb-1">
                                    ¿Cuántas {unidadBaseNombre.toLowerCase()}s trae este empaque? *
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    value={data.factor_conversion}
                                    onChange={(e) => setData('factor_conversion', e.target.value)}
                                    onWheel={(e) => e.target.blur()}
                                    className="w-full text-center font-mono font-black text-sm text-slate-800 bg-white border border-slate-200 rounded-xl py-2 px-3 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                    required
                                />
                            </div>

                            {/* Precio de Venta al Público */}
                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="text-[9px] font-black tracking-wider text-slate-600">
                                        Precio al Público (S/) *
                                    </label>
                                    {precioSugeridoMultiplicado && (
                                        <button
                                            type="button"
                                            onClick={() => setData('precio_venta', Number(precioSugeridoMultiplicado.toFixed(2)))}
                                            className="text-[9px] font-black text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md hover:bg-emerald-100 transition flex items-center gap-1 cursor-pointer"
                                        >
                                            <Sparkles className="w-3 h-3" />
                                            <span>Sugerir: {formatMoneda(precioSugeridoMultiplicado)}</span>
                                        </button>
                                    )}
                                </div>

                                <div className="relative">
                                    <span className="absolute left-3 top-2.5 font-black text-xs text-slate-400">S/</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        value={data.precio_venta}
                                        onChange={(e) => setData('precio_venta', e.target.value)}
                                        onWheel={(e) => e.target.blur()}
                                        className="w-full pl-8 pr-3 py-2 font-mono font-black text-sm text-slate-800 bg-white border border-slate-200 rounded-xl [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                        required
                                    />
                                </div>
                            </div>

                            {/* Tarjeta de cálculo en vivo */}
                            {esMayorista && precioNum > 0 && (
                                <div className="p-3 bg-emerald-50/70 border border-emerald-200 rounded-2xl text-xs space-y-1">
                                    <p className="font-black text-emerald-900 text-[10px]">Cálculo para Mostrador</p>
                                    <p className="text-emerald-800 font-bold text-[11px]">
                                        Cada unidad sale a <strong>{formatMoneda(precioUnitarioEquivalente)}</strong>.
                                    </p>
                                    {ahorroPorUnidad > 0 && (
                                        <p className="text-emerald-700 font-bold text-[10px]">
                                            💡 El cliente ahorra <strong>{formatMoneda(ahorroPorUnidad)}</strong> por unidad respecto al precio suelto.
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="pt-2">
                                <button
                                    type="submit"
                                    disabled={saving}
                                    className="volumen-marca w-full h-11 rounded-xl text-on-brand font-black text-xs tracking-wider transition flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer hover:brightness-105"
                                >
                                    {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Check className="w-4 h-4" />}
                                    <span>{presentacionEnEdicion ? 'Guardar Cambios' : 'Guardar Precio'}</span>
                                </button>

                                {presentacionEnEdicion && (
                                    <button
                                        type="button"
                                        onClick={cancelarEdicion}
                                        className="w-full mt-2 h-8 text-xs font-bold text-slate-500 hover:bg-slate-100 rounded-xl transition cursor-pointer"
                                    >
                                        Cancelar Edición
                                    </button>
                                )}
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {/* MODAL PROPIO DE CONFIRMACIÓN PARA QUITAR EMPAQUE */}
            <Dialog open={Boolean(presAEliminar)} onClose={() => setPresAEliminar(null)} size="sm">
                {(presAEliminar) && (
                <div className="p-6 text-center">
                        <div className="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-3">
                            <AlertTriangle className="w-6 h-6" />
                        </div>
                        <h4 className="text-sm font-black text-slate-900">¿Quitar presentación?</h4>
                        <p className="text-xs text-slate-500 font-medium mt-1 leading-relaxed">
                            ¿Estás seguro de retirar el empaque <strong className="text-slate-800">{presAEliminar.presentacion?.nombre}</strong> de este producto?
                        </p>

                        <div className="flex items-center gap-2 mt-4">
                            <button
                                type="button"
                                onClick={() => setPresAEliminar(null)}
                                className="flex-1 h-10 rounded-xl border border-slate-200 text-slate-600 font-black text-xs tracking-wider hover:bg-slate-50 transition cursor-pointer"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                onClick={ejecutarEliminarPres}
                                className="flex-1 h-10 rounded-xl bg-rose-600 text-white font-black text-xs tracking-wider hover:bg-rose-700 transition flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <Trash2 className="w-4 h-4" />
                                <span>Quitar</span>
                            </button>
                        </div>
                </div>
                )}
            </Dialog>

            {/* MODAL PROPIO DE ALERTA */}
            <Dialog open={Boolean(dialogo)} onClose={() => setDialogo(null)} size="sm">
                {(dialogo) && (
                <div className="p-6 text-center">
                        <div
                            className={`w-12 h-12 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-xs ${
                                dialogo.tipo === 'exito' ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-600'
                            }`}
                        >
                            {dialogo.tipo === 'exito' ? <CheckCircle2 className="w-6 h-6" /> : <AlertCircle className="w-6 h-6" />}
                        </div>

                        <h3 className="text-sm font-black text-slate-900">{dialogo.titulo}</h3>
                        <p className="text-xs text-slate-600 font-medium mt-1.5">{dialogo.mensaje}</p>

                        <button
                            type="button"
                            onClick={() => setDialogo(null)}
                            className="w-full mt-4 h-10 rounded-xl bg-slate-900 text-white font-black text-xs tracking-wider hover:bg-black transition cursor-pointer"
                        >
                            Entendido
                        </button>
                </div>
                )}
            </Dialog>
        </Modal>
    );
}