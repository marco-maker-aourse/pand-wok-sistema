import React, { useState, useEffect, useMemo, useRef } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button, Dialog, EmptyState, IconButton, buttonClasses } from '@/Components/ui';
import { Head, useForm, router, Link } from '@inertiajs/react';
import axios from 'axios';
import {
    Search,
    PackagePlus,
    Trash2,
    CheckCircle2,
    AlertCircle,
    Building2,
    Calendar,
    ArrowRight,
    ArrowLeft,
    Loader2,
    X,
    FileText,
    Boxes,
    Tag,
    Hash,
    TrendingUp,
    Percent
} from 'lucide-react';
import avisarErrorConsultaDocumento from '@/Utils/consultaDocumento';
import { MENSAJE_RUC, esRucValido, pareceNombre } from '@/Utils/documentos';

const currency = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

export default function Create({ auth, productos = [], proveedores = [], tipos_comprobante = [], productoInicialId = 0 }) {
    const [busquedaProd, setBusquedaProd] = useState('');
    const [carrito, setCarrito] = useState([]);

    const [listaProveedores, setListaProveedores] = useState(proveedores);
    const [searchProv, setSearchProv] = useState('');
    const [proveedorActivo, setProveedorActivo] = useState(null);
    const [buscandoSunat, setBuscandoSunat] = useState(false);
    const dropdownRef = useRef(null);
    const [isProvDropdownOpen, setIsProvDropdownOpen] = useState(false);
    const productoInicialProcesado = useRef(false);

    useEffect(() => {
        setListaProveedores(proveedores);
    }, [proveedores]);

    const [modalCargando, setModalCargando] = useState(false);
    const [modalExito, setModalExito] = useState(null);
    const [modalAlerta, setModalAlerta] = useState(null);

    const hoy = new Date().toISOString().split('T')[0];
    const comprobanteInicial = tipos_comprobante[0];
    const serieInicial = comprobanteInicial?.descripcion?.toUpperCase().includes('FACTURA')
        ? 'F001'
        : comprobanteInicial?.descripcion?.toUpperCase().includes('BOLETA')
            ? 'B001'
            : 'T001';

    const { data, setData, post, processing } = useForm({
        id_proveedor: '',
        id_tipo_comprobante: comprobanteInicial?.id_tipo_comprobante || '',
        tipo_comprobante: comprobanteInicial?.descripcion?.toUpperCase() || 'FACTURA',
        serie_comprobante: serieInicial,
        num_comprobante: '',
        fecha_emision: hoy,
        detalles: []
    });

    useEffect(() => {
        function handleClickOutside(e) {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
                setIsProvDropdownOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        setData('detalles', carrito);
    }, [carrito]);

    // Filtrar proveedores de forma tolerante
    const proveedoresFiltrados = useMemo(() => {
        const q = searchProv.toLowerCase().trim();
        if (!q) return listaProveedores.slice(0, 10);
        return listaProveedores.filter((p) => {
            const doc = String(p.num_documento || p.num_document || p.ruc || '').trim();
            const razon = String(p.razon_social || p.nombre_razon_social || '').toLowerCase();
            return doc.includes(q) || razon.includes(q);
        });
    }, [listaProveedores, searchProv]);

    const nombreUnidad = (productoOItem) => productoOItem?.unidad_base?.nombre || productoOItem?.unidadBase?.nombre || 'unidad';
    const unidadContenido = (productoOItem) => {
        const unidad = nombreUnidad(productoOItem);
        return ['caja', 'cajas', 'paquete', 'paquetes', 'saco', 'sacos', 'docena', 'docenas', 'display', 'displays']
            .includes(unidad.toLowerCase().trim()) ? 'unidades' : unidad;
    };

    const formatCantidadBase = (cantidad, unidad) => {
        const total = Number(cantidad || 0);
        return `${Number.isInteger(total) ? total : total.toFixed(4).replace(/0+$/, '').replace(/\.$/, '')} ${unidad}${Math.abs(total) === 1 ? '' : 's'}`;
    };

    const formatPresentacionLabel = (nombre, factor, unidad = 'unidad') => {
        const f = Number(factor) || 1;
        return `${nombre || 'Presentación'} (contiene ${formatCantidadBase(f, unidad)})`;
    };

    const seleccionarProveedor = (prov, updateSearch = true) => {
        setData((prev) => ({ ...prev, id_proveedor: prov.id_proveedor }));
        setProveedorActivo(prov);
        const docOTexto = prov.razon_social || prov.nombre_razon_social || prov.num_documento || prov.num_document || prov.ruc;
        if (updateSearch) setSearchProv(docOTexto);
        setIsProvDropdownOpen(false);
    };

    const handleSearchChange = (e) => {
        const val = e.target.value;
        setSearchProv(val);
        setIsProvDropdownOpen(true);

        const rucProv = String(proveedorActivo?.num_documento || proveedorActivo?.num_document || proveedorActivo?.ruc || '');
        const nombreProv = String(proveedorActivo?.razon_social || proveedorActivo?.nombre_razon_social || '');

        if (proveedorActivo && rucProv !== val && nombreProv !== val) {
            setProveedorActivo(null);
            setData('id_proveedor', '');
        }

        if (/^\d{11}$/.test(val.trim())) {
            const idFactura = tipos_comprobante.find((t) =>
                (t.nombre || t.descripcion || '').toUpperCase().includes('FACTURA')
            )?.id_tipo_comprobante || 1;

            setData((prev) => ({
                ...prev,
                tipo_comprobante: 'FACTURA',
                id_tipo_comprobante: idFactura,
                serie_comprobante: 'F001'
            }));
        }
    };

    const limpiarProveedor = () => {
        setSearchProv('');
        setProveedorActivo(null);
        setData('id_proveedor', '');
        document.getElementById('input_proveedor')?.focus();
    };

    const accionLupa = async () => {
        const termino = searchProv.trim();

        if (!termino) {
            setModalAlerta({
                titulo: 'Campo vacío',
                mensaje: 'Escribe el RUC o la razón social del proveedor.',
            });
            return;
        }

        // Con letras se busca entre los proveedores registrados; SUNAT solo se consulta por RUC.
        if (pareceNombre(termino)) {
            if (proveedoresFiltrados.length === 1) {
                seleccionarProveedor(proveedoresFiltrados[0]);
            } else {
                setIsProvDropdownOpen(true);
                setModalAlerta(proveedoresFiltrados.length > 1
                    ? { titulo: 'Varios proveedores coinciden', mensaje: 'Elige el proveedor correcto en la lista de coincidencias.' }
                    : { titulo: 'Proveedor no registrado', mensaje: 'No hay proveedores con ese nombre.', sugerencia: 'Para consultar el padrón de SUNAT ingresa el RUC de 11 dígitos.' });
            }
            return;
        }

        const soloDigitos = termino.replace(/\s/g, '');

        // Misma regla que el servidor (App\Rules\Ruc).
        if (!esRucValido(soloDigitos)) {
            setModalAlerta({
                titulo: 'RUC inválido',
                mensaje: MENSAJE_RUC,
                sugerencia: 'Si buscas un proveedor registrado, también puedes escribir su razón social.'
            });
            return;
        }

        const existeLocal = listaProveedores.find((p) => {
            const doc = String(p.num_documento || p.num_document || p.ruc || '').replace(/\D/g, '').trim();
            return doc === soloDigitos;
        });

        if (existeLocal) {
            seleccionarProveedor(existeLocal);
            return;
        }

        setBuscandoSunat(true);
        try {
            const url = typeof route === 'function' ? route('documentos.lookup') : '/api/documentos/consulta';
            const res = await axios.post(url, {
                context: 'proveedor',
                tipo: 'ruc',
                documento: soloDigitos
            });

            const docData = res.data?.data || res.data;
            const razonSocial = docData?.nombre || docData?.razon_social || docData?.nombre_razon_social;
            const direccion = docData?.direccion || docData?.direccion_fiscal || '';

            if (razonSocial) {
                // Contrato de StoreProveedorRequest: ruc, razon_social y direccion.
                const saveRes = await axios.post('/proveedores', {
                    ruc: soloDigitos,
                    razon_social: String(razonSocial).toUpperCase().slice(0, 255),
                    direccion: String(direccion).toUpperCase().slice(0, 255) || null,
                    estado: true
                }, { headers: { Accept: 'application/json' } });

                // Solo se selecciona el proveedor que el servidor confirmó haber guardado.
                const nuevoProv = saveRes.data?.data;
                if (!nuevoProv?.id_proveedor) {
                    throw new Error('El proveedor no se pudo registrar. Inténtalo nuevamente.');
                }

                setListaProveedores((prev) => [nuevoProv, ...prev]);
                seleccionarProveedor(nuevoProv);
            } else {
                throw new Error('El RUC ingresado no devolvió datos en el padrón.');
            }
        } catch (error) {
            avisarErrorConsultaDocumento(error);
        } finally {
            setBuscandoSunat(false);
        }
    };

    const agregarAlCarrito = (producto) => {
        const uid = `${producto.id_producto}_${Date.now()}`;
        const listaPres = producto.presentaciones || [];
        const presDefecto = listaPres.find((presentacion) => Number(presentacion.factor_conversion) === 1)
            ?? listaPres[0]
            ?? null;

        const factor = presDefecto ? Number(presDefecto.factor_conversion) : 1;
        const pvp = presDefecto?.precio_venta ? Number(presDefecto.precio_venta) : Number(producto.precio_venta || 0);

        setCarrito([
            {
                uid: uid,
                id_producto: producto.id_producto,
                id_prod_pres: presDefecto ? presDefecto.id_prod_pres : null,
                nombre: producto.nombre,
                presentaciones: listaPres,
                unidad_base: producto.unidad_base,
                factor_conversion: factor,
                cantidad: 1,
                precio_costo: '',
                porcentaje_utilidad: 18, // Por defecto inicia con el 18% sugerido
                precio_venta: Number(pvp.toFixed(2)),
                subtotal: 0
            },
            ...carrito
        ]);
    };

    useEffect(() => {
        if (productoInicialProcesado.current || !productoInicialId || carrito.length > 0) {
            return;
        }

        const producto = productos.find((item) => Number(item.id_producto) === Number(productoInicialId));

        if (producto) {
            productoInicialProcesado.current = true;
            agregarAlCarrito(producto);
        }
    }, [productoInicialId, productos, carrito.length]);

    const cambiarPresentacion = (uid, idProdPres) => {
        setCarrito(
            carrito.map((item) => {
                if (item.uid === uid) {
                    const pres = item.presentaciones.find((p) => String(p.id_prod_pres) === String(idProdPres));
                    const factor = pres ? Number(pres.factor_conversion) : 1;
                    const nuevoPVP = pres?.precio_venta ? Number(pres.precio_venta) : item.precio_venta;

                    const costo = Number(item.precio_costo) || 0;
                    const porc = costo > 0 ? Number((((nuevoPVP - costo) / costo) * 100).toFixed(1)) : item.porcentaje_utilidad;

                    return {
                        ...item,
                        id_prod_pres: pres ? pres.id_prod_pres : item.id_prod_pres,
                        factor_conversion: factor,
                        precio_venta: nuevoPVP,
                        porcentaje_utilidad: porc
                    };
                }
                return item;
            })
        );
    };

    // Sincronización bidireccional entre Costo, Margen % y Precio Venta
    const actualizarCampo = (uid, campo, valor) => {
        setCarrito(
            carrito.map((item) => {
                if (item.uid === uid) {
                    const updated = { ...item };

                    if (campo === 'cantidad') {
                        const normalizada = String(valor).replace(',', '.').replace(/[^\d.]/g, '');
                        const [entero = '', ...decimales] = normalizada.split('.');
                        updated.cantidad = decimales.length ? `${entero}.${decimales.join('').slice(0, 4)}` : entero;
                        const cNum = updated.cantidad === '' ? 0 : Number(updated.cantidad);
                        const costoNum = Number(item.precio_costo) || 0;
                        updated.subtotal = cNum * costoNum;
                    } else if (campo === 'precio_costo') {
                        updated.precio_costo = valor;
                        const costo = parseFloat(valor) || 0;
                        const cNum = Number(item.cantidad) || 0;
                        updated.subtotal = cNum * costo;

                        // Si ya tiene un % de utilidad, recalcular el precio de venta automáticamente
                        const porc = parseFloat(item.porcentaje_utilidad);
                        if (!isNaN(porc) && porc > 0 && costo > 0) {
                            updated.precio_venta = Number((costo * (1 + porc / 100)).toFixed(2));
                        } else if (costo > 0 && Number(item.precio_venta) > 0) {
                            updated.porcentaje_utilidad = Number((((Number(item.precio_venta) - costo) / costo) * 100).toFixed(1));
                        }
                    } else if (campo === 'porcentaje_utilidad') {
                        updated.porcentaje_utilidad = valor;
                        const porc = parseFloat(valor);
                        const costo = Number(item.precio_costo) || 0;
                        if (!isNaN(porc) && costo > 0) {
                            updated.precio_venta = Number((costo * (1 + porc / 100)).toFixed(2));
                        }
                    } else if (campo === 'precio_venta') {
                        updated.precio_venta = valor;
                        const venta = parseFloat(valor) || 0;
                        const costo = Number(item.precio_costo) || 0;
                        if (costo > 0 && venta > 0) {
                            updated.porcentaje_utilidad = Number((((venta - costo) / costo) * 100).toFixed(1));
                        }
                    } else {
                        updated[campo] = valor;
                    }

                    return updated;
                }
                return item;
            })
        );
    };

    const validarCantidadOnBlur = (uid) => {
        setCarrito(
            carrito.map((item) => {
                if (item.uid === uid) {
                    const cNum = Number(item.cantidad) || 1;
                    const costoNum = Number(item.precio_costo) || 0;
                    return {
                        ...item,
                        cantidad: cNum,
                        subtotal: cNum * costoNum
                    };
                }
                return item;
            })
        );
    };

    const removerDelCarrito = (uid) => setCarrito(carrito.filter((item) => item.uid !== uid));
    const totalCompra = carrito.reduce((sum, item) => sum + item.subtotal, 0);

    const procesarCompra = (e) => {
        e.preventDefault();

        if (carrito.length === 0) {
            setModalAlerta({
                titulo: 'Sin artículos seleccionados',
                mensaje: 'Debe agregar al menos un producto del catálogo para generar el ingreso a almacén.'
            });
            return;
        }

        if (!data.id_proveedor) {
            setModalAlerta({
                titulo: 'Proveedor no asignado',
                mensaje: 'Por favor identifique al proveedor con su número de RUC o razón social.'
            });
            return;
        }

        if (!data.serie_comprobante || !data.num_comprobante) {
            setModalAlerta({
                titulo: 'Comprobante incompleto',
                mensaje: 'Ingrese la serie y el número correlativo de la factura o boleta del proveedor.'
            });
            return;
        }

        setModalCargando(true);
        post('/ingresos', {
            preserveScroll: true,
            onSuccess: () => {
                setModalCargando(false);
                setModalExito({
                    tipo: data.tipo_comprobante,
                    serie: data.serie_comprobante,
                    numero: data.num_comprobante,
                    proveedor: proveedorActivo?.razon_social || proveedorActivo?.nombre_razon_social || 'Proveedor Comercial',
                    total: totalCompra,
                    articulos: carrito.length
                });
            },
            onError: (errs) => {
                setModalCargando(false);
                const msg = Object.values(errs)[0] || 'Ocurrió un error al registrar el ingreso en Kardex.';
                setModalAlerta({
                    titulo: 'No se pudo registrar la compra',
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
        <AuthenticatedLayout header="Nueva entrada de mercadería">
            <Head title="Nueva entrada de mercadería" />

            <div className="h-[calc(100vh-6rem)] lg:h-[calc(100vh-7.5rem)] flex flex-col lg:flex-row gap-5">
                {/* Catálogo de Artículos */}
                <div className="flex-1 flex flex-col bg-white rounded-2xl p-4 sm:p-5 overflow-hidden shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="flex items-center justify-between mb-4">
                        <div className="relative flex-1 mr-3">
                            <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5 pointer-events-none" />
                            <input
                                type="text"
                                placeholder="Buscar producto por nombre o código de barras..."
                                className="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-lg text-sm placeholder:text-slate-400 transition"
                                value={busquedaProd}
                                onChange={(e) => setBusquedaProd(e.target.value)}
                                autoFocus
                            />
                        </div>
                        <Link href="/ingresos" className={buttonClasses({ variant: 'secondary', className: 'shrink-0' })}>
                            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                            <span>Historial</span>
                        </Link>
                    </div>

                    <div className="flex-1 overflow-y-auto pr-1 pb-2 [scrollbar-width:thin]">
                        {productosFiltrados.length > 0 ? (
                            <div className="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                                {productosFiltrados.map((prod) => {
                                    const primerEmpaque = prod.presentaciones?.[0];
                                    const pvp = primerEmpaque?.precio_venta
                                        ? Number(primerEmpaque.precio_venta)
                                        : Number(prod.precio_venta || 0);

                                    return (
                                        <button
                                            type="button"
                                            key={prod.id_producto}
                                            onClick={() => agregarAlCarrito(prod)}
                                            className="elevable group flex cursor-pointer flex-col justify-between rounded-xl bg-white p-3.5 text-left ring-1 ring-slate-200 select-none hover:ring-brand-border"
                                        >
                                            <div>
                                                <span className="volumen-suave mb-2 grid h-8 w-8 place-items-center rounded-lg text-xs font-semibold text-brand-ink" aria-hidden="true">
                                                    {prod.nombre?.charAt(0).toUpperCase()}
                                                </span>
                                                <h3 className="font-semibold text-xs leading-snug line-clamp-2 text-slate-800 group-hover:text-slate-900 transition-colors">
                                                    {prod.nombre}
                                                </h3>
                                                <div className="mt-2 flex flex-wrap items-center gap-1.5">
                                                    <span className="bg-slate-100 text-slate-600 text-xs font-bold px-2 py-0.5 rounded-md uppercase">
                                                        {prod.presentaciones?.length || 0} presentación{prod.presentaciones?.length === 1 ? '' : 'es'}
                                                    </span>
                                                    {primerEmpaque && (
                                                        <span className="text-xs font-bold text-brand-ink">
                                                            {formatPresentacionLabel(primerEmpaque.presentacion?.nombre || primerEmpaque.nombre, primerEmpaque.factor_conversion, unidadContenido(prod))}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="flex justify-between items-end mt-3 pt-2 border-t border-slate-100">
                                                <div>
                                                    <span className="block text-xs font-semibold text-slate-400">
                                                        PVP Sugerido
                                                    </span>
                                                    <span className="font-mono font-semibold text-sm text-slate-900 tracking-tight">
                                                        {currency.format(pvp)}
                                                    </span>
                                                </div>
                                                <span
                                                    className="volumen-marca text-on-brand w-6 h-6 rounded-lg flex items-center justify-center text-xs font-bold transition group-hover:brightness-105"
                                                    aria-hidden="true"
                                                >
                                                    +
                                                </span>
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

                {/* Panel Derecho: Proveedor, Comprobante y artículos */}
                <div className="w-full lg:w-[500px] xl:w-[540px] flex flex-col bg-white rounded-2xl overflow-hidden shrink-0 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                    <div className="p-4 border-b border-slate-100 bg-slate-50/60" ref={dropdownRef}>
                        <div className="flex justify-between items-center mb-2">
                            <span className="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
                                <Building2 className="w-3.5 h-3.5" />
                                Proveedor Abastecedor
                            </span>
                            {proveedorActivo && (
                                <span className="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full uppercase">
                                    Identificado
                                </span>
                            )}
                        </div>

                        <div className="relative">
                            <div className="flex rounded-2xl bg-white overflow-hidden transition shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                                <input
                                    id="input_proveedor"
                                    type="text"
                                    placeholder="Buscar por RUC (11) o Razón Social..."
                                    className="w-full py-2 px-3 text-xs font-bold text-slate-800 uppercase font-mono"
                                    value={searchProv}
                                    onChange={handleSearchChange}
                                    onFocus={() => setIsProvDropdownOpen(true)}
                                    onClick={() => setIsProvDropdownOpen(true)}
                                />
                                {searchProv && (
                                    <button
                                        type="button"
                                        onClick={limpiarProveedor}
                                        aria-label="Limpiar proveedor"
                                        className="p-1.5 text-slate-400 hover:text-slate-600 transition cursor-pointer"
                                    >
                                        <X className="w-3.5 h-3.5" />
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={accionLupa}
                                    disabled={buscandoSunat}
                                    className="volumen-marca text-on-brand px-3.5 flex items-center justify-center transition disabled:opacity-50 cursor-pointer hover:brightness-105"
                                    title="Consultar RUC en SUNAT"
                                    aria-label="Consultar RUC en SUNAT"
                                >
                                    {buscandoSunat ? (
                                        <Loader2 className="w-3.5 h-3.5 animate-spin" />
                                    ) : (
                                        <Search className="w-3.5 h-3.5" />
                                    )}
                                </button>
                            </div>

                            {proveedorActivo && (
                                <div className="mt-2 bg-white border border-slate-200 rounded-xl p-2.5 flex items-center justify-between">
                                    <div className="min-w-0">
                                        <p className="text-xs font-bold text-slate-800 truncate">
                                            {proveedorActivo.razon_social || proveedorActivo.nombre_razon_social}
                                        </p>
                                        <p className="text-xs font-mono font-semibold text-slate-400 mt-0.5">
                                            RUC: {proveedorActivo.num_documento || proveedorActivo.num_document || proveedorActivo.ruc || '—'}
                                        </p>
                                    </div>
                                    <span className="text-xs font-semibold uppercase text-slate-700 bg-slate-100 px-2 py-1 rounded-md">
                                        Proveedor
                                    </span>
                                </div>
                            )}

                            {isProvDropdownOpen && !proveedorActivo && (
                                <div className="absolute top-11 left-0 w-full z-40 bg-white border border-slate-200 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                    {proveedoresFiltrados.length > 0 ? (
                                        proveedoresFiltrados.map((p) => (
                                            <div
                                                key={p.id_proveedor}
                                                className="px-3.5 py-2 hover:bg-slate-50 cursor-pointer border-b border-slate-100 transition"
                                                onClick={() => seleccionarProveedor(p)}
                                            >
                                                <p className="font-bold text-slate-800 text-xs truncate">
                                                    {p.razon_social || p.nombre_razon_social}
                                                </p>
                                                <span className="text-xs font-mono text-slate-400 font-semibold">
                                                    RUC: {p.num_documento || p.num_document || p.ruc || 'Sin RUC'}
                                                </span>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="p-2.5 text-center text-xs text-slate-400 font-medium">
                                            {/^\d{11}$/.test(searchProv.trim())
                                                ? 'Proveedor no encontrado localmente. Presione la lupa para consultar en SUNAT.'
                                                : 'No se encontraron proveedores registrados.'}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        <div className="grid grid-cols-3 gap-2 mt-2.5 pt-2.5 border-t border-slate-200/70">
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Tipo
                                </label>
                                <select
                                    className="w-full text-xs font-bold text-slate-800 bg-white border border-slate-200 rounded-xl py-1.5 px-2 outline-none"
                                    value={data.id_tipo_comprobante}
                                    onChange={(e) => {
                                        const id = Number(e.target.value);
                                        const comp = tipos_comprobante.find((t) => t.id_tipo_comprobante === id);
                                        const nombre = comp?.descripcion?.toUpperCase() || 'FACTURA';
                                        setData((prev) => ({
                                            ...prev,
                                            id_tipo_comprobante: id,
                                            tipo_comprobante: nombre,
                                            serie_comprobante: nombre.includes('FACTURA') ? 'F001' : nombre.includes('BOLETA') ? 'B001' : 'T001'
                                        }));
                                    }}
                                >
                                    {tipos_comprobante.map((t) => (
                                        <option key={t.id_tipo_comprobante} value={t.id_tipo_comprobante}>
                                            {t.codigo_sunat} · {t.descripcion}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Serie
                                </label>
                                <input
                                    type="text"
                                    maxLength={4}
                                    placeholder="F001"
                                    className="w-full text-center text-xs font-mono font-semibold uppercase bg-white border border-slate-200 rounded-xl py-1.5 px-2"
                                    value={data.serie_comprobante}
                                    onChange={(e) => setData('serie_comprobante', e.target.value.toUpperCase())}
                                    required
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-semibold text-slate-600 mb-1">
                                    Número
                                </label>
                                <input
                                    type="text"
                                    placeholder="0001234"
                                    className="w-full text-center text-xs font-mono font-bold bg-white border border-slate-200 rounded-xl py-1.5 px-2"
                                    value={data.num_comprobante}
                                    onChange={(e) => setData('num_comprobante', e.target.value.replace(/\D/g, ''))}
                                    required
                                />
                            </div>
                        </div>
                    </div>

                    <div className="flex-1 overflow-y-auto p-3.5 space-y-2.5 [scrollbar-width:thin]">
                        {carrito.length === 0 ? (
                            <EmptyState icon={PackagePlus} title="Sin artículos seleccionados" description="Elige productos del catálogo para indicar empaque, cantidad y costo." />
                        ) : (
                            carrito.map((item) => {
                                const costo = Number(item.precio_costo || 0);
                                const venta = Number(item.precio_venta || 0);
                                const utilidadSoles = venta - costo;
                                const margenPorc = costo > 0 ? ((venta - costo) / costo) * 100 : 0;
                                const esGanancia = utilidadSoles >= 0;
                                const signo = utilidadSoles > 0 ? '+' : '';
                                const cantNum = Number(item.cantidad) || 0;
                                const gananciaTotal = utilidadSoles * cantNum;
                                const factor = Math.max(Number(item.factor_conversion) || 1, 1);
                                const costoUnitario = factor > 0 ? (costo / factor) : 0;
                                const totalUnidades = cantNum * factor;

                                return (
                                    <div
                                        key={item.uid}
                                        className="bg-white p-3.5 rounded-2xl space-y-2.5 relative shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]"
                                    >
                                        <div className="flex justify-between items-start">
                                            <div className="min-w-0 pr-2">
                                                <p className="font-semibold text-xs text-slate-900 truncate">{item.nombre}</p>
                                                <span className="text-xs font-bold text-brand-ink uppercase">
                                                    {formatPresentacionLabel(
                                                        item.presentaciones.find((p) => String(p.id_prod_pres) === String(item.id_prod_pres))?.presentacion?.nombre,
                                                        item.factor_conversion,
                                                        unidadContenido(item)
                                                    )}
                                                </span>
                                            </div>
                                            <IconButton icon={Trash2} label={`Quitar ${item.nombre}`} onClick={() => removerDelCarrito(item.uid)} />
                                        </div>

                                        <div>
                                            <label className="block text-xs font-semibold text-slate-600 mb-0.5">
                                                Empaque / Presentación Facturada
                                            </label>
                                            <select
                                                className="w-full text-xs font-bold text-slate-700 border border-slate-200 rounded-lg py-1 px-1.5 bg-white uppercase cursor-pointer"
                                                value={item.id_prod_pres || ''}
                                                onChange={(e) => cambiarPresentacion(item.uid, e.target.value)}
                                            >
                                                {item.presentaciones.map((p) => (
                                                    <option key={p.id_prod_pres} value={p.id_prod_pres}>
                                                        {formatPresentacionLabel(p.presentacion?.nombre || p.nombre, p.factor_conversion, unidadContenido(item))}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        {/* Fila de Cantidad y Costo */}
                                        <div className="grid grid-cols-2 gap-2 bg-slate-50/70 p-2 rounded-xl border border-slate-200/60">
                                            <div>
                                                <label className="block text-xs font-semibold text-slate-600 mb-0.5">
                                                    Cantidad de empaques
                                                </label>
                                                <input
                                                    type="text"
                                                    value={item.cantidad}
                                                    onWheel={(e) => e.target.blur()}
                                                    onChange={(e) => actualizarCampo(item.uid, 'cantidad', e.target.value)}
                                                    onBlur={() => validarCantidadOnBlur(item.uid)}
                                                    className="w-full text-xs font-mono font-semibold text-center text-slate-900 bg-white border border-slate-200 rounded-lg py-1.5 px-2"
                                                    placeholder="1"
                                                    inputMode="decimal"
                                                />
                                            </div>

                                            <div>
                                                <label className="block text-xs font-semibold text-slate-600 mb-0.5">
                                                    Costo por empaque (S/)
                                                </label>
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    value={item.precio_costo}
                                                    onWheel={(e) => e.target.blur()}
                                                    onChange={(e) => actualizarCampo(item.uid, 'precio_costo', e.target.value)}
                                                    placeholder="0.00"
                                                    className="w-full text-xs font-mono font-bold text-slate-800 bg-white border border-slate-200 rounded-lg text-right py-1.5 px-2 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                />
                                            </div>
                                        </div>

                                        {/* Fila de Utilidad % y Precio Venta sincronizados */}
                                        <div className="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200/90 items-end">
                                            <div>
                                                <div className="flex items-center justify-between mb-0.5">
                                                    <label className="block text-xs font-semibold text-slate-600">
                                                        Utilidad (%)
                                                    </label>
                                                    <div className="flex gap-1">
                                                        {[15, 18, 20, 25, 30].map((p) => (
                                                            <button
                                                                key={p}
                                                                type="button"
                                                                onClick={() => actualizarCampo(item.uid, 'porcentaje_utilidad', p)}
                                                                className={`rounded px-1.5 py-0.5 text-[0.6875rem] font-semibold tabular-nums transition cursor-pointer ${
                                                                    Number(item.porcentaje_utilidad) === p
                                                                        ? 'volumen-marca text-on-brand'
                                                                        : 'bg-white hover:bg-slate-200 text-slate-600 border border-slate-200'
                                                                }`}
                                                            >
                                                                {p}%
                                                            </button>
                                                        ))}
                                                    </div>
                                                </div>
                                                <div className="relative">
                                                    <input
                                                        type="number"
                                                        step="0.1"
                                                        placeholder="18.0"
                                                        value={item.porcentaje_utilidad !== undefined ? item.porcentaje_utilidad : ''}
                                                        onWheel={(e) => e.target.blur()}
                                                        onChange={(e) => actualizarCampo(item.uid, 'porcentaje_utilidad', e.target.value)}
                                                        className="w-full text-xs font-mono font-semibold text-center text-slate-800 bg-white border border-slate-200 rounded-lg py-1.5 pr-5 pl-2 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                    />
                                                    <span className="absolute right-2 top-1.5 text-xs font-bold text-slate-400 pointer-events-none">%</span>
                                                </div>
                                            </div>

                                            <div>
                                                <label className="block text-xs font-semibold text-emerald-700 mb-0.5">
                                                    Precio Venta al Público (S/)
                                                </label>
                                                <div className="relative">
                                                    <span className="absolute left-2.5 top-1.5 text-xs font-bold text-emerald-600 pointer-events-none">S/</span>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        value={item.precio_venta}
                                                        onWheel={(e) => e.target.blur()}
                                                        onChange={(e) => actualizarCampo(item.uid, 'precio_venta', e.target.value)}
                                                        placeholder="0.00"
                                                        className="w-full text-xs font-mono font-semibold text-emerald-800 bg-white border border-emerald-300 rounded-lg text-right py-1.5 pr-2 pl-7 focus:border-emerald-600 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        {/* Desglose de existencias, Ganancia en Soles y Margen de Utilidad */}
                                        <div className="grid grid-cols-3 gap-2 rounded-xl border border-slate-100 bg-white px-2.5 py-2 text-xs">
                                            <div>
                                                <span className="block font-semibold uppercase text-slate-400">Total en Stock</span>
                                                <strong className="font-mono text-slate-800">
                                                    {formatCantidadBase(totalUnidades, nombreUnidad(item))}
                                                </strong>
                                                <span className="block text-xs text-slate-400 font-semibold">
                                                    (Costo: {currency.format(costoUnitario)} c/u)
                                                </span>
                                            </div>

                                            <div>
                                                <span className="block font-semibold uppercase text-slate-400">Ganancia por Empaque</span>
                                                <strong className={`font-mono text-xs font-semibold ${esGanancia ? 'text-emerald-700' : 'text-rose-600'}`}>
                                                    {signo}{currency.format(utilidadSoles)}
                                                </strong>
                                                <span className="block text-xs text-slate-500 font-bold">
                                                    en soles netos
                                                </span>
                                            </div>

                                            <div>
                                                <span className="block font-semibold uppercase text-slate-400">Margen Comercial</span>
                                                <strong className={`inline-block font-mono text-xs font-semibold px-1.5 py-0.2 rounded ${
                                                    esGanancia
                                                        ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                                                        : 'bg-rose-50 text-rose-700 border border-rose-200'
                                                }`}>
                                                    {signo}{costo > 0 ? `${margenPorc.toFixed(1)}%` : '0.0%'}
                                                </strong>
                                                <span className="block text-xs text-slate-500 font-bold mt-0.5">
                                                    Total: {signo}{currency.format(gananciaTotal)}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>

                    <div className="p-4 bg-slate-50 border-t border-slate-200/80">
                        <form onSubmit={procesarCompra} className="space-y-3">
                            <div className="flex justify-between items-baseline">
                                <span className="text-xs font-semibold text-slate-500">
                                    Total de la Compra:
                                </span>
                                <span className="text-2xl font-semibold text-slate-900 font-mono">
                                    {currency.format(totalCompra)}
                                </span>
                            </div>

                            <Button type="submit" size="lg" block icon={CheckCircle2} disabled={processing || carrito.length === 0 || !data.id_proveedor}>
                                Registrar compra en kardex
                            </Button>
                        </form>
                    </div>
                </div>
            </div>

            <Dialog open={Boolean(modalCargando)} onClose={() => {}} size="sm" dismissible={false}>
                {(modalCargando) && (
                <div className="p-6 flex flex-col items-center text-center">
                        <Loader2 className="w-10 h-10 animate-spin text-brand mb-3" />
                        <h4 className="text-sm font-semibold text-slate-900">Ingresando a Almacén</h4>
                        <p className="text-xs text-slate-500 mt-1 font-medium">
                            Incrementando existencias en Kardex...
                        </p>
                </div>
                )}
            </Dialog>

            <Dialog open={Boolean(modalExito)} onClose={() => {}} size="sm" dismissible={false}>
                {(modalExito) && (
                <div className="p-6 text-center">
                        <div className="volumen-marca text-on-brand w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <CheckCircle2 className="w-6 h-6" />
                        </div>

                        <span className="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full inline-block mb-1">
                            Mercadería Ingresada
                        </span>

                        <h3 className="text-lg font-semibold text-slate-900 tracking-tight">
                            {modalExito.tipo} {modalExito.serie}-{modalExito.numero}
                        </h3>
                        <p className="text-xs text-slate-500 font-medium mt-0.5">
                            {modalExito.proveedor}
                        </p>

                        <div className="my-4 p-3 bg-slate-50 border border-slate-200 rounded-lg font-mono text-xs space-y-1.5 text-left">
                            <div className="flex justify-between text-slate-500">
                                <span>Artículos ingresados:</span>
                                <span className="font-bold text-slate-800">{modalExito.articulos} líneas</span>
                            </div>
                            <div className="flex justify-between text-slate-500 border-t border-slate-200 pt-1.5">
                                <span className="font-bold text-slate-800">Total Invertido:</span>
                                <span className="font-semibold text-slate-900 text-sm">
                                    {currency.format(modalExito.total)}
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            onClick={() => {
                                setModalExito(null);
                                router.visit('/ingresos');
                            }}
                            className="volumen-marca text-on-brand w-full h-10 rounded-xl font-bold text-xs transition cursor-pointer hover:brightness-105"
                        >
                            Ir al Historial de Compras
                        </button>
                </div>
                )}
            </Dialog>

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
                            <div className="mt-3 p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-500 flex items-start gap-1.5">
                                <AlertCircle className="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" />
                                <div>
                                    <span className="font-bold">Sugerencia:</span> {modalAlerta.sugerencia}
                                </div>
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