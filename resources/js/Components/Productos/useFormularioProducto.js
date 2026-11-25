import { useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { toast } from '@/Components/ui';

/**
 * Estado y reglas del asistente de productos (alta, edición, alta rápida de
 * catálogos y desactivación). La lógica se trasladó sin cambios desde
 * Productos/Index; los avisos de éxito ahora son toasts.
 * `setDialogo` recibe { tipo: 'exito' | 'error' | 'alerta', titulo, mensaje }.
 */
export default function useFormularioProducto({ categorias, unidades, catalogoPresentaciones, setDialogo }) {
    // Modales y control de asistente
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState(null);

    // Asistente en 3 Pasos
    const [pasoActual, setPasoActual] = useState(1);

    // Catálogos reactivos
    const [localCatalogs, setLocalCatalogs] = useState({ categorias, unidades });
    const [catalogoLocalPresentaciones, setCatalogoLocalPresentaciones] = useState(catalogoPresentaciones);
    // Un empaque creado (o reactivado) en cualquier diálogo queda disponible en toda la pantalla.
    const registrarPresentacion = (item) => {
        if (!item?.id_presentacion) return;
        setCatalogoLocalPresentaciones((actual) => [...actual.filter((p) => p.id_presentacion !== item.id_presentacion), item]
            .sort((a, b) => a.nombre.localeCompare(b.nombre)));
    };
    const [quickCatalog, setQuickCatalog] = useState(null);
    const [quickName, setQuickName] = useState('');
    const [quickCode, setQuickCode] = useState('');
    const [quickSaving, setQuickSaving] = useState(false);

    // Modales nativos
    const [modalEliminar, setModalEliminar] = useState(null);
    const [guardandoProducto, setGuardandoProducto] = useState(false);


    // Formulario de Inertia con array dinámico de presentaciones
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        nombre: '',
        id_categoria: '',
        id_unidad_base: '',
        codigo_barras: '',
        codigo_sunat: '',
        afecta_igv: true,
        es_servicio: false,

        // Paso 3: Precios y Márgenes
        precio_costo: '',
        precio_venta_unitario: '',
        presentaciones_adicionales: [], // Lista dinámica de empaques [{ id_temp, id_presentacion, factor_conversion, precio_venta }]
    });

    const unidadBaseSeleccionada = useMemo(() => {
        return localCatalogs.unidades.find((u) =>
            String(u.id_unidad ?? u.id) === String(data.id_unidad_base)
        );
    }, [localCatalogs.unidades, data.id_unidad_base]);

    const unidadNombre = (unidadBaseSeleccionada?.nombre || 'UNIDAD').toUpperCase();

    // Cálculos de Margen Unitario
    const costoNum = Number(data.precio_costo || 0);
    const ventaUnitariaNum = Number(data.precio_venta_unitario || 0);

    const gananciaUnitaria = (costoNum > 0 && ventaUnitariaNum > costoNum)
        ? (ventaUnitariaNum - costoNum)
        : 0;

    const margenPorcentajeUnitario = costoNum > 0
        ? ((ventaUnitariaNum - costoNum) / costoNum) * 100
        : 0;

    // Asignación rápida de unidad base (Paso 2)
    const asignarUnidadRapida = (tipo) => {
        let busquedaTexto = 'unidad';
        if (tipo === 'peso') busquedaTexto = 'kilogramo';
        if (tipo === 'liquido') busquedaTexto = 'litro';

        const encontrada = localCatalogs.unidades.find(u => 
            (u.nombre || '').toLowerCase().includes(busquedaTexto)
        );

        if (encontrada) {
            setData('id_unidad_base', encontrada.id_unidad ?? encontrada.id);
        } else if (localCatalogs.unidades.length > 0) {
            setData('id_unidad_base', localCatalogs.unidades[0].id_unidad ?? localCatalogs.unidades[0].id);
        }
    };

    // ==========================================
    // AGREGAR / EDITAR PRESENTACIONES DINÁMICAS
    // ==========================================
    const agregarPresentacionFila = (nombrePreset = '', factorPreset = 12) => {
        let idPres = '';
        if (nombrePreset) {
            const encontrada = catalogoLocalPresentaciones.find(p => p.nombre.toUpperCase().includes(nombrePreset.toUpperCase()));
            if (encontrada) idPres = encontrada.id_presentacion;
        }

        const precioSugerido = (ventaUnitariaNum > 0 && factorPreset > 1)
            ? Number((ventaUnitariaNum * factorPreset).toFixed(2))
            : '';

        const nuevaFila = {
            id_temp: Date.now() + Math.random(),
            id_presentacion: idPres,
            factor_conversion: factorPreset,
            precio_venta: precioSugerido,
        };

        setData('presentaciones_adicionales', [...data.presentaciones_adicionales, nuevaFila]);
    };

    const actualizarPresentacionFila = (idTemp, campo, valor) => {
        setData('presentaciones_adicionales', data.presentaciones_adicionales.map(fila => {
            if (fila.id_temp === idTemp) {
                const actualizado = { ...fila, [campo]: valor };
                
                // Si cambia el factor y ya hay precio unitario, recalcular precio sugerido si estaba vacío
                if (campo === 'factor_conversion' && ventaUnitariaNum > 0 && !actualizado.precio_venta) {
                    actualizado.precio_venta = Number((ventaUnitariaNum * Number(valor || 1)).toFixed(2));
                }
                return actualizado;
            }
            return fila;
        }));
    };

    const eliminarPresentacionFila = (idTemp) => {
        setData('presentaciones_adicionales', data.presentaciones_adicionales.filter(fila => fila.id_temp !== idTemp));
    };

    // Abrir Modal Crear
    const openCreate = () => {
        setEditingProduct(null);
        setPasoActual(1);
        clearErrors();
        reset();

        const defaultUnidad = localCatalogs.unidades.find(u => (u.nombre || '').toLowerCase().includes('unidad')) || localCatalogs.unidades[0];
        if (defaultUnidad) {
            setData('id_unidad_base', defaultUnidad.id_unidad ?? defaultUnidad.id);
        }
        setIsModalOpen(true);
    };

    // Abrir Modal Editar
    const openEdit = (prod) => {
        setEditingProduct(prod);
        setPasoActual(1);
        clearErrors();
        setData({
            nombre: (prod.nombre || '').toUpperCase(),
            id_categoria: prod.id_categoria || '',
            id_unidad_base: prod.id_unidad_base || '',
            codigo_barras: prod.codigo_barras || '',
            codigo_sunat: prod.codigo_sunat || '',
            afecta_igv: prod.afecta_igv !== false,
            es_servicio: Boolean(prod.es_servicio),
            precio_costo: '',
            precio_venta_unitario: '',
            presentaciones_adicionales: [],
        });
        setIsModalOpen(true);
    };

    const closeModal = () => {
        if (quickCatalog) return;
        setIsModalOpen(false);
        setEditingProduct(null);
        setPasoActual(1);
        reset();
    };

    // Alta rápida de categoría o unidad
    const saveQuickCatalog = async (e) => {
        e.preventDefault();
        if (!quickName.trim()) return;

        const tipo = quickCatalog;
        const config = {
            categoria: { url: '/categorias', key: 'categorias', id: 'id_categoria', campo: 'id_categoria' },
            unidad: { url: '/unidades', key: 'unidades', id: 'id_unidad', campo: 'id_unidad_base' }
        }[tipo];

        setQuickSaving(true);
        try {
            const payload = tipo === 'unidad'
                ? { nombre: quickName.trim().toUpperCase(), codigo_sunat: quickCode.trim().toUpperCase() || null }
                : { nombre: quickName.trim().toUpperCase(), estado: true };

            const { data: res } = await axios.post(config.url, payload, { headers: { Accept: 'application/json' } });
            const nuevo = res.data ?? res;

            setLocalCatalogs((prev) => ({
                ...prev,
                [config.key]: [...prev[config.key], nuevo].sort((a, b) => a.nombre.localeCompare(b.nombre))
            }));

            setData(config.campo, nuevo[config.id]);
            setQuickCatalog(null);
            setQuickName('');
            setQuickCode('');
        } catch (err) {
            const msg = err.response?.data?.message || 'No se pudo crear el registro.';
            setDialogo({ tipo: 'error', titulo: 'No se pudo registrar', mensaje: String(msg) });
        } finally {
            setQuickSaving(false);
        }
    };

    // Validación paso a paso (NUNCA ENVÍA AL SERVIDOR)
    const avanzarPaso = () => {
        if (pasoActual === 1) {
            if (!data.nombre.trim()) {
                setDialogo({ tipo: 'alerta', titulo: 'Nombre requerido', mensaje: 'Asigna un nombre al producto.' });
                return;
            }
            if (!data.id_categoria) {
                setDialogo({ tipo: 'alerta', titulo: 'Categoría requerida', mensaje: 'Selecciona una categoría.' });
                return;
            }
            setPasoActual(2);
        } else if (pasoActual === 2) {
            if (!data.id_unidad_base && !data.es_servicio) {
                setDialogo({ tipo: 'alerta', titulo: 'Unidad requerida', mensaje: 'Selecciona cómo se contará en almacén.' });
                return;
            }
            setPasoActual(3);
        }
    };

    // Envío del formulario (SÓLO SE DISPARA EN EL PASO 3)
    const submit = (e) => {
        e.preventDefault();

        if (pasoActual < 3) {
            avanzarPaso();
            return;
        }

        if (!editingProduct && !data.precio_venta_unitario) {
            setDialogo({
                tipo: 'alerta',
                titulo: 'Precio de venta suelto requerido',
                mensaje: 'Asigna el precio de venta por unidad para que la caja pueda cobrar.'
            });
            return;
        }

        setGuardandoProducto(true);
        const url = editingProduct ? `/productos/${editingProduct.id_producto}` : '/productos';
        const method = editingProduct ? put : post;

        method(url, {
            preserveScroll: true,
            onSuccess: () => {
                setGuardandoProducto(false);
                closeModal();
                toast.success(editingProduct ? 'Producto actualizado' : 'Producto y precios registrados', 'El producto y sus presentaciones quedaron habilitados.');
            },
            onError: (errs) => {
                setGuardandoProducto(false);
                const msg = Object.values(errs)[0] || 'Revisa los datos ingresados.';
                setDialogo({ tipo: 'error', titulo: 'No se pudo guardar', mensaje: String(msg) });
            }
        });
    };

    // Desactivar Producto
    const ejecutarEliminacion = () => {
        if (!modalEliminar) return;
        const prod = modalEliminar;

        router.delete(`/productos/${prod.id_producto}`, {
            preserveScroll: true,
            onSuccess: () => {
                setModalEliminar(null);
                toast.success('Producto desactivado', `${prod.nombre} se retiró del catálogo.`);
            },
            onError: () => {
                setModalEliminar(null);
                setDialogo({
                    tipo: 'error',
                    titulo: 'Operación no permitida',
                    mensaje: 'Este producto tiene ventas o movimientos en kardex.'
                });
            }
        });
    };

    return {
        data, setData, errors, processing,
        isModalOpen, editingProduct, pasoActual, setPasoActual, guardandoProducto,
        localCatalogs, catalogoLocalPresentaciones, registrarPresentacion,
        quickCatalog, setQuickCatalog, quickName, setQuickName, quickCode, setQuickCode, quickSaving, saveQuickCatalog,
        unidadNombre, costoNum, ventaUnitariaNum, gananciaUnitaria, margenPorcentajeUnitario,
        asignarUnidadRapida, agregarPresentacionFila, actualizarPresentacionFila, eliminarPresentacionFila,
        openCreate, openEdit, closeModal, avanzarPaso, submit,
        modalEliminar, setModalEliminar, ejecutarEliminacion,
    };
}
