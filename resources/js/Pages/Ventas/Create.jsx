import { useEffect, useMemo, useRef, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2, Loader2, Search, ShoppingCart, User, X } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DialogoAperturaCaja from '@/Components/Caja/DialogoAperturaCaja';
import avisarErrorConsultaDocumento from '@/Utils/consultaDocumento';
import { MENSAJE_RUC, pareceNombre, prefijoRucPosible } from '@/Utils/documentos';
import PanelPagos from '@/Components/Ventas/PanelPagos';
import VentaRegistradaModal from '@/Components/Ventas/VentaRegistradaModal';
import CatalogoPos from '@/Components/Ventas/pos/CatalogoPos';
import DialogoConfirmarCobro from '@/Components/Ventas/pos/DialogoConfirmarCobro';
import LineaCarrito from '@/Components/Ventas/pos/LineaCarrito';
import { SelectorTipoDocumento } from '@/Components/Ventas/pos/SelectoresPos';
import SugerenciasCliente from '@/Components/Ventas/pos/SugerenciasCliente';
import { formatoMoneda } from '@/Components/Ventas/pos/formato';
import { Badge, Button, Dialog, Kbd, NoticeDialog, cx } from '@/Components/ui';
import useAtajoTeclado from '@/hooks/useAtajoTeclado';

export default function Create({ auth, productosIniciales, clientes = [], tiposDocumento = [], metodosPago = [], turnoAbierto = null, datosEmisor = null }) {
    const [busquedaProd, setBusquedaProd] = useState('');
    const [productos, setProductos] = useState([]);
    const [productosCache, setProductosCache] = useState({});
    const [buscandoProductos, setBuscandoProductos] = useState(false);
    const [carrito, setCarrito] = useState([]);
    const [mostrarAperturaCaja, setMostrarAperturaCaja] = useState(false);
    const [pestañaMovil, setPestañaMovil] = useState('catalogo');

    const [listaClientes, setListaClientes] = useState(clientes);
    const [searchCli, setSearchCli] = useState('');
    const [clienteActivo, setClienteActivo] = useState(null);
    const [idTipoDocumento, setIdTipoDocumento] = useState(() => {
        const publico = clientes.find((cliente) => cliente.num_documento === '00000000');
        return publico?.id_tipo_documento
            ?? tiposDocumento.find((tipo) => String(tipo.codigo_sunat) === '1')?.id_tipo_documento
            ?? tiposDocumento[0]?.id_tipo_documento
            ?? '';
    });
    const [buscandoSunat, setBuscandoSunat] = useState(false);
    const dropdownRef = useRef(null);
    const [isCliDropdownOpen, setIsCliDropdownOpen] = useState(false);

    const [modalCargando, setModalCargando] = useState(false);
    const [modalConfirmacion, setModalConfirmacion] = useState(false);
    const [modalExito, setModalExito] = useState(null);
    const [modalAlerta, setModalAlerta] = useState(null);
    // Impide registrar la misma venta dos veces por doble clic o doble atajo.
    const enviandoVentaRef = useRef(false);

    const tipoDocumentoSeleccionado = tiposDocumento.find(
        (tipo) => String(tipo.id_tipo_documento) === String(idTipoDocumento)
    );

    // Banderas de validación de documento en tiempo real
    const esRuc = String(tipoDocumentoSeleccionado?.codigo_sunat) === '6' || tipoDocumentoSeleccionado?.nombre?.toUpperCase().includes('RUC');
    const esDni = String(tipoDocumentoSeleccionado?.codigo_sunat) === '1' || tipoDocumentoSeleccionado?.nombre?.toUpperCase().includes('DNI');
    const limiteCaracteres = esRuc ? 11 : esDni ? 8 : (Number(tipoDocumentoSeleccionado?.longitud_exacta) || 15);
    // Con letras, el campo busca clientes registrados por nombre; con dígitos, valida el documento.
    const buscaPorNombre = pareceNombre(searchCli);
    const [buscandoClientes, setBuscandoClientes] = useState(false);

    const { data, setData, post, processing } = useForm({
        id_cliente: '',
        pagos: [{
            id_metodo: metodosPago.length > 0 ? metodosPago[0].id_metodo : '',
            monto: '',
            nro_referencia: '',
            monto_recibido: '',
        }],
        tipo_comprobante: 'BOLETA',
        detalles: []
    });

    // Cada venta nueva parte de un único medio de pago sin importes, referencias ni vuelto heredados.
    const pagoInicial = () => ({
        id_metodo: metodosPago.length > 0 ? metodosPago[0].id_metodo : '',
        monto: '',
        nro_referencia: '',
        monto_recibido: '',
    });

    useEffect(() => {
        const publico = listaClientes.find(
            (c) => c.num_documento === '00000000' || c.nombre_razon_social?.toUpperCase().includes('PUBLICO')
        );
        if (publico) {
            seleccionarCliente(publico, false);
        }
    }, []);

    useEffect(() => {
        if (Array.isArray(productosIniciales)) {
            setProductos(productosIniciales);
            setProductosCache((actual) => Object.fromEntries([
                ...Object.entries(actual),
                ...productosIniciales.map((producto) => [producto.id_producto, producto]),
            ]));
        }
    }, [productosIniciales]);

    useEffect(() => {
        const termino = busquedaProd.trim();

        if (termino.length < 2) {
            if (!termino && Array.isArray(productosIniciales)) setProductos(productosIniciales);
            return undefined;
        }

        const timer = window.setTimeout(async () => {
            setBuscandoProductos(true);
            try {
                const { data: res } = await axios.get('/ventas/productos/buscar', { params: { q: termino } });
                const resultados = res.data ?? [];
                setProductos(resultados);
                setProductosCache((actual) => Object.fromEntries([
                    ...Object.entries(actual),
                    ...resultados.map((producto) => [producto.id_producto, producto]),
                ]));
            } catch {
                setProductos([]);
            } finally {
                setBuscandoProductos(false);
            }
        }, 220);

        return () => window.clearTimeout(timer);
    }, [busquedaProd, productosIniciales]);

    useEffect(() => {
        function handleClickOutside(e) {
            if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
                setIsCliDropdownOpen(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        setData('detalles', carrito);
    }, [carrito]);

    const clientesFiltrados = useMemo(() => {
        if (!searchCli.trim()) return listaClientes.slice(0, 5);
        const query = searchCli.toLowerCase();
        return listaClientes.filter(
            (c) =>
                c.num_documento?.toLowerCase().includes(query) ||
                c.nombre_razon_social?.toLowerCase().includes(query)
        );
    }, [listaClientes, searchCli]);

    // Solo se precargan los clientes recientes: por nombre se consulta la base completa.
    useEffect(() => {
        const termino = searchCli.trim();
        if (!buscaPorNombre || termino.length < 2 || clienteActivo) return undefined;

        const controlador = new AbortController();
        const timer = window.setTimeout(async () => {
            setBuscandoClientes(true);
            try {
                const { data: respuesta } = await axios.get('/ventas/clientes/buscar', { params: { q: termino }, signal: controlador.signal });
                const encontrados = respuesta?.data ?? [];
                setListaClientes((prev) => {
                    const ids = new Set(prev.map((c) => c.id_cliente));
                    return [...prev, ...encontrados.filter((c) => !ids.has(c.id_cliente))];
                });
            } catch {
                // Si la consulta falla, siguen disponibles las coincidencias ya cargadas.
            } finally {
                if (!controlador.signal.aborted) setBuscandoClientes(false);
            }
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controlador.abort();
        };
    }, [searchCli, buscaPorNombre, clienteActivo]);

    const nombreUnidad = (item) => item?.unidad_base?.nombre || 'unidad';

    const calcularDiasVencimiento = (fechaStr) => {
        if (!fechaStr) return null;
        const fecha = new Date(fechaStr + 'T00:00:00');
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        const diffTime = fecha.getTime() - hoy.getTime();
        return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    };

    const seleccionarCliente = (cli, updateSearch = true) => {
        const doc = String(cli.num_documento || '').trim();
        const tipoDocumento = tiposDocumento.find(
            (tipo) => String(tipo.id_tipo_documento) === String(cli.id_tipo_documento)
        );
        const clienteEsRuc = String(tipoDocumento?.codigo_sunat) === '6' || doc.length === 11;

        setData((prev) => ({
            ...prev,
            id_cliente: cli.id_cliente,
            tipo_comprobante: clienteEsRuc ? 'FACTURA' : 'BOLETA'
        }));
        if (tipoDocumento) setIdTipoDocumento(tipoDocumento.id_tipo_documento);

        setClienteActivo(cli);
        if (updateSearch) setSearchCli(cli.num_documento);
        setIsCliDropdownOpen(false);
    };

    // =========================================================================
    // VALIDACIÓN ESTRICTA Y SEGURA DE ENTRADA (RUC 11 DÍGITOS / PREFIJOS 10 Y 20)
    // =========================================================================
    const handleSearchChange = (e) => {
        let val = e.target.value;

        if (pareceNombre(val)) {
            // Búsqueda por nombre o razón social de un cliente ya registrado.
            val = val.replace(/\s+/g, ' ').slice(0, 100).toUpperCase();
        } else if (esRuc) {
            // Solo números y un prefijo válido de SUNAT (misma regla que el servidor).
            val = val.replace(/\D/g, '');
            if (!prefijoRucPosible(val)) {
                return;
            }

            // Límite estricto e inquebrantable de 11 dígitos
            val = val.slice(0, 11);

            // Si inicia con 20 (Empresa), conmuta obligatoriamente a FACTURA
            if (val.startsWith('20')) {
                setData('tipo_comprobante', 'FACTURA');
            }
        } else if (esDni) {
            // DNI: Solo números y máximo 8 dígitos
            val = val.replace(/\D/g, '').slice(0, 8);
            setData('tipo_comprobante', 'BOLETA');
        } else {
            val = val.slice(0, limiteCaracteres).toUpperCase();
        }

        setSearchCli(val);
        setIsCliDropdownOpen(true);

        if (val === '') {
            const publico = listaClientes.find((c) => c.num_documento === '00000000');
            if (publico) seleccionarCliente(publico, false);
        } else {
            setClienteActivo(null);
            setData('id_cliente', '');
        }
    };

    const limpiarCliente = () => {
        setSearchCli('');
        const publico = listaClientes.find((c) => c.num_documento === '00000000');
        if (publico) seleccionarCliente(publico, false);
        document.getElementById('input_cliente')?.focus();
    };

    /** Se invoca solo cuando la venta ya fue aceptada por Laravel. No reutiliza datos de cobro. */
    const prepararSiguienteVenta = () => {
        const publico = listaClientes.find((cliente) => cliente.num_documento === '00000000');
        const tipoPublico = tiposDocumento.find((tipo) => String(tipo.id_tipo_documento) === String(publico?.id_tipo_documento));

        setCarrito([]);
        setBusquedaProd('');
        setProductos(Array.isArray(productosIniciales) ? productosIniciales : []);
        setSearchCli('');
        setClienteActivo(publico ?? null);
        setIsCliDropdownOpen(false);
        setPestañaMovil('catalogo');
        if (tipoPublico) setIdTipoDocumento(tipoPublico.id_tipo_documento);

        setData({
            id_cliente: publico?.id_cliente ?? '',
            pagos: [pagoInicial()],
            tipo_comprobante: 'BOLETA',
            detalles: [],
        });
    };

    const cambiarTipoDocumento = (tipo) => {
        setIdTipoDocumento(tipo.id_tipo_documento);
        setIsCliDropdownOpen(false);

        const codSunat = String(tipo.codigo_sunat || '');
        const nuevoEsRuc = codSunat === '6' || tipo.nombre?.toUpperCase().includes('RUC');

        setSearchCli('');
        setClienteActivo(null);
        setData((prev) => ({
            ...prev,
            id_cliente: '',
            tipo_comprobante: nuevoEsRuc ? 'FACTURA' : 'BOLETA'
        }));
    };

    const accionLupa = async () => {
        const doc = searchCli.trim();

        if (!doc) {
            setModalAlerta({
                titulo: 'Campo vacío',
                mensaje: `Ingresa el número de ${tipoDocumentoSeleccionado?.nombre || 'documento'} o el nombre del cliente.`
            });
            return;
        }

        if (buscaPorNombre) {
            // Por nombre solo se eligen clientes registrados; SUNAT/RENIEC se consulta por documento.
            if (clientesFiltrados.length === 1) {
                seleccionarCliente(clientesFiltrados[0]);
            } else {
                setIsCliDropdownOpen(true);
                setModalAlerta(clientesFiltrados.length > 1
                    ? { titulo: 'Varios clientes coinciden', mensaje: 'Elige el cliente correcto en la lista de coincidencias.' }
                    : { titulo: 'Cliente no registrado', mensaje: 'No hay clientes con ese nombre. Para consultar SUNAT o RENIEC ingresa el número de documento.' });
            }
            return;
        }

        if (esRuc && doc.length !== 11) {
            setModalAlerta({
                titulo: 'RUC Incompleto',
                mensaje: MENSAJE_RUC,
            });
            return;
        }

        if (esDni && doc.length !== 8) {
            setModalAlerta({
                titulo: 'DNI Incompleto',
                mensaje: 'El DNI debe tener exactamente 8 dígitos numéricos.',
            });
            return;
        }

        const existeLocal = listaClientes.find((c) => c.num_documento?.toLowerCase() === doc.toLowerCase());
        if (existeLocal) {
            seleccionarCliente(existeLocal);
            return;
        }

        if (['1', '6'].includes(String(tipoDocumentoSeleccionado?.codigo_sunat))) {
            setBuscandoSunat(true);
            try {
                const res = await axios.post('/ventas/buscar-cliente', {
                    id_tipo_documento: tipoDocumentoSeleccionado.id_tipo_documento,
                    documento: doc,
                });
                if (res.data?.success && res.data?.cliente) {
                    const nuevo = res.data.cliente;
                    setListaClientes((prev) => [nuevo, ...prev]);
                    seleccionarCliente(nuevo);
                } else {
                    throw new Error(res.data?.message || 'Documento no registrado en el padrón.');
                }
            } catch (error) {
                avisarErrorConsultaDocumento(error);
            } finally {
                setBuscandoSunat(false);
            }
        }
    };

    const getStockRestante = (id_producto) => {
        const prod = productosCache[id_producto];
        if (!prod) return 0;
        const consumido = carrito
            .filter((item) => item.id_producto === id_producto)
            .reduce((sum, item) => sum + item.cantidad * item.factor_conversion, 0);
        return prod.stock_actual - consumido;
    };

    // AGREGAR AL CARRITO APLICANDO FIFO ESTRICTO
    const agregarAlCarrito = (producto) => {
        if (!producto.es_servicio && producto.stock_actual <= 0) return;

        const presentaciones = producto.presentaciones || [];
        const presDefecto = presentaciones.find((p) => Number(p.factor_conversion) === 1) ?? presentaciones[0] ?? null;

        if (!presDefecto) {
            setModalAlerta({
                titulo: 'Sin precio configurado',
                mensaje: 'Este producto no cuenta con presentaciones activas para venta.',
            });
            return;
        }

        const stockRestante = getStockRestante(producto.id_producto);
        if (!producto.es_servicio && stockRestante < 0.0001) {
            setModalAlerta({
                titulo: 'Stock Agotado',
                mensaje: `No hay más existencias físicas para "${producto.nombre}".`
            });
            return;
        }

        const uid = `${producto.id_producto}_base`;
        const existe = carrito.find((item) => item.uid === uid);

        if (existe) {
            setCarrito((actual) => actual.map((item) => {
                if (item.uid !== uid) return item;
                const siguienteCantidad = Number(item.cantidad) + 1;
                return { ...item, cantidad: siguienteCantidad, subtotal: siguienteCantidad * Number(item.precio) };
            }));
        } else {
            const loteFIFO = producto.lote_recomendado || producto.lotes?.[0] || null;

            setCarrito([
                {
                    uid: uid,
                    id_producto: producto.id_producto,
                    id_prod_pres: presDefecto.id_prod_pres,
                    nombre: producto.nombre,
                    es_servicio: Boolean(producto.es_servicio),
                    afecta_igv: Boolean(producto.afecta_igv),
                    presentaciones: presentaciones,
                    unidad_base: producto.unidad_base,
                    lotes: producto.lotes || [],
                    lote: loteFIFO?.codigo_lote || '',
                    lote_info: loteFIFO,
                    precio_costo_kardex: Number(producto.precio_costo || 0),
                    factor_conversion: presDefecto?.factor_conversion ? Number(presDefecto.factor_conversion) : 1,
                    cantidad: 1,
                    precio: Number(presDefecto.precio_venta),
                    subtotal: Number(presDefecto.precio_venta),
                    stock_original: producto.stock_actual
                },
                ...carrito
            ]);
        }
    };

    const cambiarPresentacion = (uid, idProdPres) => {
        setCarrito(
            carrito.map((item) => {
                if (item.uid === uid) {
                    const pres = item.presentaciones.find((p) => String(p.id_prod_pres) === String(idProdPres));
                    const nuevoPrecio = pres ? Number(pres.precio_venta) : Number(item.precio);
                    const nuevoFactor = pres ? Number(pres.factor_conversion) : 1;
                    return {
                        ...item,
                        id_prod_pres: pres ? pres.id_prod_pres : item.id_prod_pres,
                        precio: nuevoPrecio,
                        factor_conversion: nuevoFactor,
                        subtotal: Number(item.cantidad) * nuevoPrecio
                    };
                }
                return item;
            })
        );
    };

    const modificarCantidadPaso = (uid, delta) => {
        setCarrito((actual) => actual.map((item) => {
            if (item.uid !== uid) return item;
            const nueva = Math.max(1, Number(item.cantidad) + delta);
            return { ...item, cantidad: nueva, subtotal: nueva * item.precio };
        }));
    };

    const actualizarCantidadDirecta = (uid, val) => {
        const normalizada = String(val).replace(',', '.').replace(/[^\d.]/g, '');
        const [entero = '', ...decimales] = normalizada.split('.');
        const fraccion = decimales.join('').slice(0, 4);
        const nuevaCant = decimales.length ? `${entero}.${fraccion}` : entero;

        setCarrito(
            carrito.map((item) => {
                if (item.uid === uid) {
                    const cantNum = nuevaCant === '' ? 0 : Number(nuevaCant);
                    return {
                        ...item,
                        cantidad: nuevaCant,
                        subtotal: cantNum * item.precio
                    };
                }
                return item;
            })
        );
    };

    const validarCantidadOnBlur = (uid) => {
        setCarrito(
            carrito.map((item) => {
                if (item.uid === uid) {
                    const cant = Math.max(0.0001, Number(item.cantidad) || 1);
                    return {
                        ...item,
                        cantidad: cant,
                        subtotal: cant * item.precio
                    };
                }
                return item;
            })
        );
    };

    const actualizarLote = (uid, codigoLote) => {
        setCarrito(carrito.map((item) => {
            if (item.uid === uid) {
                const infoLote = item.lotes?.find(l => l.codigo_lote === codigoLote);
                return { ...item, lote: codigoLote, lote_info: infoLote };
            }
            return item;
        }));
    };

    const removerDelCarrito = (uid) => setCarrito(carrito.filter((item) => item.uid !== uid));
    const totalVenta = carrito.reduce((sum, item) => sum + item.subtotal, 0);
    const pagos = data.pagos || [];
    const totalPagado = pagos.reduce((sum, pago) => sum + (Number(pago.monto) || 0), 0);
    const saldoPendiente = Number((totalVenta - totalPagado).toFixed(2));

    const actualizarPago = (indice, campo, valor) => {
        setData('pagos', pagos.map((pago, actual) => (
            actual === indice ? { ...pago, [campo]: valor } : pago
        )));
    };

    // Cada fila representa un medio de pago. Evitamos selectores para que el cajero
    // pueda armar cobros mixtos con pocos clics y sin perder el foco del cobro.
    const seleccionarMetodoPago = (indice, metodo) => {
        const estaEnOtraFila = pagos.some((pago, actual) => (
            actual !== indice && Number(pago.id_metodo) === Number(metodo.id_metodo)
        ));

        if (estaEnOtraFila) return;

        actualizarPago(indice, 'id_metodo', metodo.id_metodo);
    };

    const agregarPago = () => {
        const siguienteMetodo = metodosPago.find((metodo) => !pagos.some(
            (pago) => Number(pago.id_metodo) === Number(metodo.id_metodo)
        ));

        if (!siguienteMetodo) {
            setModalAlerta({
                titulo: 'No hay más medios disponibles',
                mensaje: 'Cada medio de pago solo puede usarse una vez en el mismo cobro.',
            });
            return;
        }

        setData('pagos', [...pagos, {
            id_metodo: siguienteMetodo.id_metodo,
            monto: Math.max(saldoPendiente, 0).toFixed(2),
            nro_referencia: '',
            monto_recibido: '',
        }]);
    };

    const quitarPago = (indice) => {
        if (pagos.length === 1) return;
        setData('pagos', pagos.filter((_, actual) => actual !== indice));
    };

    const metodoDePago = (pago) => metodosPago.find(
        (metodo) => Number(metodo.id_metodo) === Number(pago.id_metodo)
    );

    const iniciarCobro = () => {
        if (!turnoAbierto) {
            setMostrarAperturaCaja(true);
            return;
        }

        if (carrito.length === 0) {
            setModalAlerta({
                titulo: 'Carrito Vacío',
                mensaje: 'Selecciona al menos un artículo para despachar.'
            });
            return;
        }

        if (!data.id_cliente) {
            setModalAlerta({
                titulo: 'Cliente No Asignado',
                mensaje: 'Asigna un cliente o mantén "Público en General".'
            });
            return;
        }

        if (data.tipo_comprobante === 'FACTURA' && String(clienteActivo?.num_documento).length !== 11) {
            setModalAlerta({
                titulo: 'RUC Requerido para Factura',
                mensaje: 'Para emitir Factura electrónica se requiere un cliente con RUC válido de 11 dígitos.'
            });
            return;
        }

        const pagosPreparados = pagos.map((pago) => ({ ...pago }));
        if (pagosPreparados.length === 1 && !(Number(pagosPreparados[0].monto) > 0)) {
            pagosPreparados[0].monto = totalVenta.toFixed(2);
        }
        pagosPreparados.forEach((pago) => {
            if (metodoDePago(pago)?.tipo === 'EFECTIVO' && pago.monto_recibido === '') {
                pago.monto_recibido = Number(pago.monto || 0).toFixed(2);
            }
        });
        const totalPreparado = pagosPreparados.reduce((sum, pago) => sum + (Number(pago.monto) || 0), 0);
        if (Math.abs(totalPreparado - totalVenta) > 0.01) {
            setModalAlerta({
                titulo: 'Distribuye el cobro',
                mensaje: `Los pagos deben sumar ${formatoMoneda.format(totalVenta)}. Falta o sobra ${formatoMoneda.format(Math.abs(totalVenta - totalPreparado))}.`,
            });
            return;
        }
        setData('pagos', pagosPreparados);
        setModalConfirmacion(true);
    };

    const confirmarVenta = async (pagosARegistrar = pagos) => {
        const totalPagosARegistrar = pagosARegistrar.reduce((sum, pago) => sum + (Number(pago.monto) || 0), 0);
        if (Math.abs(totalPagosARegistrar - totalVenta) > 0.01) {
            setModalAlerta({
                titulo: 'Cobro incompleto',
                mensaje: 'La suma de los pagos debe coincidir exactamente con el total de la venta.',
            });
            return;
        }

        const pagoInvalido = pagosARegistrar.find((pago) => {
            const metodo = metodoDePago(pago);
            if (metodo?.requiere_referencia && !String(pago.nro_referencia || '').trim()) return true;
            return metodo?.tipo === 'EFECTIVO' && (Number(pago.monto_recibido) || 0) < Number(pago.monto);
        });
        if (pagoInvalido) {
            setModalAlerta({
                titulo: 'Revisa los datos del cobro',
                mensaje: 'Indica la referencia para los medios que la requieren y el efectivo recibido para cada pago en efectivo.',
            });
            return;
        }

        if (enviandoVentaRef.current) return;
        enviandoVentaRef.current = true;
        setModalConfirmacion(false);
        setModalCargando(true);

        try {
            const res = await axios.post('/ventas', {
                ...data,
                pagos: pagosARegistrar.map((pago) => ({
                    ...pago,
                    monto: Number(pago.monto),
                    monto_recibido: pago.monto_recibido === '' ? null : Number(pago.monto_recibido),
                })),
            });

            if (res.data?.success) {
                const ventaRegistrada = {
                    tipo: data.tipo_comprobante,
                    id_venta: res.data.id_venta,
                    estado_sunat: 'PENDIENTE',
                    serie: res.data.serie || (data.tipo_comprobante === 'FACTURA' ? 'F001' : 'B001'),
                    correlativo: res.data.correlativo || 1,
                    cliente: res.data.nombre_razon_social || clienteActivo?.nombre_razon_social || 'PÚBLICO EN GENERAL',
                    documento: res.data.num_documento || clienteActivo?.num_documento || '00000000',
                    tipoDocumentoCliente: res.data.codigo_tipo_documento_cliente || '0',
                    direccionCliente: res.data.direccion_cliente || clienteActivo?.direccion_fiscal || '',
                    documentCode: res.data.codigo_comprobante,
                    total: Number(res.data.total_venta ?? totalVenta),
                    op_gravadas: Number(res.data.op_gravadas || 0),
                    op_exoneradas: Number(res.data.op_exoneradas || 0),
                    igv: Number(res.data.igv || 0),
                    descuento: Number(res.data.descuento_global || 0),
                    fecha: res.data.fecha_emision || new Date().toISOString(),
                    detalles: (res.data.detalles || []).map((detail) => ({
                        ...detail,
                        descripcion: detail.producto,
                        unidad: detail.presentacion,
                        precioUnitario: detail.precio_unitario,
                    })),
                    paymentMethod: res.data.pagos?.map((p) => p.metodo).join(' + ') || metodoPagoNombre,
                    isCash: res.data.pagos?.some((p) => p.tipo === 'EFECTIVO') ?? pagosARegistrar.some((pago) => metodoDePago(pago)?.tipo === 'EFECTIVO'),
                    cashReceived: res.data.monto_recibido === null ? null : Number(res.data.monto_recibido),
                    change: res.data.vuelto === null ? null : Number(res.data.vuelto),
                };

                prepararSiguienteVenta();

                try {
                    const { data: resultadoSunat } = await axios.post('/emitir', { id_venta: ventaRegistrada.id_venta });
                    ventaRegistrada.estado_sunat = resultadoSunat.estado || 'PENDIENTE';
                    ventaRegistrada.mensaje_sunat = resultadoSunat.mensaje || '';
                    ventaRegistrada.hash_cpe = resultadoSunat.hash || '';
                    ventaRegistrada.impresion = resultadoSunat.impresion;
                } catch {
                    ventaRegistrada.estado_sunat = 'PENDIENTE';
                }

                setModalCargando(false);
                setModalExito(ventaRegistrada);
            } else {
                setModalCargando(false);
                setModalAlerta({ titulo: 'Venta no registrada', mensaje: res.data?.message || 'El servidor no confirmó el registro. Revisa el historial antes de reintentar.' });
            }
        } catch (error) {
            setModalCargando(false);
            const msg = error.response?.data?.message || 'No fue posible registrar la venta.';
            setModalAlerta({
                titulo: 'Error en venta',
                mensaje: msg,
                sugerencia: 'Verifica que el turno de caja esté abierto y los lotes tengan stock suficiente.'
            });
        } finally {
            enviandoVentaRef.current = false;
        }
    };

    const metodoPagoNombre = pagos.map((pago) => metodoDePago(pago)?.nombre).filter(Boolean).join(' + ') || 'No especificado';

    const enfocarBuscador = () => window.requestAnimationFrame(() => document.getElementById('buscador_producto')?.focus());

    const hayDialogoAbierto = modalConfirmacion || modalCargando || Boolean(modalExito) || Boolean(modalAlerta) || mostrarAperturaCaja;
    useAtajoTeclado('F2', () => { setPestañaMovil('catalogo'); enfocarBuscador(); }, { activo: !hayDialogoAbierto });
    useAtajoTeclado('F9', () => iniciarCobro(), { activo: !hayDialogoAbierto && carrito.length > 0 && !processing });

    // Enter en el buscador: agrega el producto si el código coincide exactamente (lector de barras)
    // o si la búsqueda dejó un único resultado.
    const agregarDesdeBusqueda = () => {
        const termino = busquedaProd.trim();
        if (!termino) return;
        const exacto = productos.find((p) => [p.codigo_barras, p.codigo, p.codigo_sunat].some((c) => c && String(c) === termino));
        const elegido = exacto ?? (productos.length === 1 ? productos[0] : null);
        if (!elegido) return;
        agregarAlCarrito(elegido);
        setBusquedaProd('');
    };

    return (
        <AuthenticatedLayout header="Punto de venta" fullWidth>
            <Head title="Punto de venta" />

            <div className="flex h-[calc(100dvh-7rem)] flex-col gap-3 lg:h-[calc(100dvh-6.5rem)]">
                <div className="flex rounded-lg border border-slate-200 bg-white p-1 text-sm lg:hidden" role="tablist" aria-label="Vista del punto de venta">
                    {[
                        ['catalogo', 'Catálogo'],
                        ['carrito', `Ticket (${carrito.length}) · ${formatoMoneda.format(totalVenta)}`],
                    ].map(([valor, etiqueta]) => (
                        <button
                            key={valor}
                            type="button"
                            role="tab"
                            aria-selected={pestañaMovil === valor}
                            onClick={() => setPestañaMovil(valor)}
                            className={cx('flex-1 rounded-md py-1.5 font-medium', pestañaMovil === valor ? 'bg-brand-soft text-brand-ink' : 'text-slate-600')}
                        >
                            {etiqueta}
                        </button>
                    ))}
                </div>

                <div className="flex flex-1 flex-col gap-3 overflow-hidden lg:flex-row">
                    <CatalogoPos
                        className={pestañaMovil === 'carrito' ? 'hidden lg:flex' : 'flex'}
                        productos={productos}
                        busqueda={busquedaProd}
                        onBusqueda={setBusquedaProd}
                        buscando={buscandoProductos}
                        turnoAbierto={turnoAbierto}
                        onAbrirCaja={() => setMostrarAperturaCaja(true)}
                        onAgregar={agregarAlCarrito}
                        onEnter={agregarDesdeBusqueda}
                    />

                    {/* En móvil se desplaza todo el ticket (antes se recortaba y ocultaba el cobro); en escritorio solo la lista. */}
                    <section
                        aria-label="Ticket de venta"
                        className={cx('flex min-h-0 w-full flex-1 flex-col overflow-y-auto rounded-2xl bg-white shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05] lg:w-[440px] lg:flex-none lg:shrink-0 lg:overflow-hidden xl:w-[480px]', pestañaMovil === 'catalogo' ? 'hidden lg:flex' : 'flex')}
                    >
                        <div className="shrink-0 border-b border-slate-200 p-3" ref={dropdownRef}>
                            <div className="mb-1.5 flex items-center justify-between">
                                <label htmlFor="input_cliente" className="flex items-center gap-1.5 text-xs font-medium text-slate-700">
                                    <User className="h-3.5 w-3.5" aria-hidden="true" />
                                    Cliente
                                </label>
                                {clienteActivo && clienteActivo.num_documento !== '00000000' && <Badge tone="success" dot>Identificado</Badge>}
                            </div>

                            <div className="flex gap-2">
                                <SelectorTipoDocumento tipos={tiposDocumento} seleccionadoId={idTipoDocumento} onSeleccionar={cambiarTipoDocumento} />
                                <div className="relative flex flex-1 overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:border-brand focus-within:ring-3 focus-within:ring-brand-ring">
                                    <input
                                        id="input_cliente"
                                        type="text"
                                        inputMode="text"
                                        autoComplete="off"
                                        maxLength={buscaPorNombre ? 100 : limiteCaracteres}
                                        placeholder={esRuc ? 'RUC o nombre del cliente' : esDni ? 'DNI o nombre del cliente' : 'Documento o nombre del cliente'}
                                        aria-describedby="ayuda_cliente"
                                        className="!min-h-0 h-9 w-full !rounded-none !border-0 px-3 font-mono text-sm uppercase !shadow-none"
                                        value={searchCli}
                                        onChange={handleSearchChange}
                                        onFocus={() => setIsCliDropdownOpen(true)}
                                    />
                                    {searchCli && (
                                        <button type="button" onClick={limpiarCliente} aria-label="Limpiar cliente" className="px-2 text-slate-400 hover:text-slate-700">
                                            <X className="h-3.5 w-3.5" aria-hidden="true" />
                                        </button>
                                    )}
                                    <button
                                        type="button"
                                        onClick={accionLupa}
                                        disabled={buscandoSunat}
                                        aria-label="Buscar o consultar documento"
                                        className="volumen-marca grid w-10 place-items-center text-on-brand disabled:opacity-60 hover:brightness-105"
                                    >
                                        {buscandoSunat ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : <Search className="h-4 w-4" aria-hidden="true" />}
                                    </button>
                                </div>
                            </div>

                            <p id="ayuda_cliente" className="mt-1 flex justify-between px-0.5 text-xs text-slate-500">
                                {buscaPorNombre ? (
                                    <span>Buscando clientes registrados por nombre</span>
                                ) : (
                                    <>
                                        <span>{esRuc ? 'RUC de 11 dígitos (10, 15, 16, 17 o 20)' : esDni ? 'DNI de 8 dígitos' : tipoDocumentoSeleccionado?.nombre || 'Documento'} · o escribe el nombre</span>
                                        <span className="font-mono tabular-nums">{searchCli.length}/{limiteCaracteres}</span>
                                    </>
                                )}
                            </p>

                            {isCliDropdownOpen && !clienteActivo && searchCli.trim().length >= 2 && (
                                <SugerenciasCliente clientes={clientesFiltrados.slice(0, 8)} buscando={buscandoClientes} onElegir={seleccionarCliente} />
                            )}

                            {clienteActivo && (
                                <div className="mt-2 flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium text-slate-900">{clienteActivo.nombre_razon_social}</p>
                                        <p className="font-mono text-xs text-slate-500">{clienteActivo.num_documento}</p>
                                    </div>
                                    <Badge tone="brand">{data.tipo_comprobante}</Badge>
                                </div>
                            )}
                        </div>

                        <div className="shrink-0 p-3 [scrollbar-width:thin] lg:min-h-0 lg:flex-1 lg:shrink lg:overflow-y-auto">
                            {carrito.length === 0 ? (
                                <div className="flex h-full flex-col items-center justify-center py-12 text-center">
                                    <ShoppingCart className="mb-2 h-6 w-6 text-slate-400" aria-hidden="true" />
                                    <p className="text-sm font-medium text-slate-700">Ticket vacío</p>
                                    <p className="mt-0.5 text-xs text-slate-500">Selecciona productos del catálogo para agregarlos.</p>
                                </div>
                            ) : (
                                <ul className="space-y-2">
                                    {carrito.map((item) => (
                                        <LineaCarrito
                                            key={item.uid}
                                            item={item}
                                            unidad={nombreUnidad(item)}
                                            diasVencimiento={calcularDiasVencimiento(item.lote_info?.fecha_vencimiento)}
                                            onQuitar={() => removerDelCarrito(item.uid)}
                                            onPresentacion={(id) => cambiarPresentacion(item.uid, id)}
                                            onPaso={(delta) => modificarCantidadPaso(item.uid, delta)}
                                            onCantidad={(valor) => actualizarCantidadDirecta(item.uid, valor)}
                                            onCantidadBlur={() => validarCantidadOnBlur(item.uid)}
                                            onLote={(codigo) => actualizarLote(item.uid, codigo)}
                                        />
                                    ))}
                                </ul>
                            )}
                        </div>

                        <div className="mt-auto shrink-0 border-t border-slate-100 bg-white p-3 pb-0 lg:pb-3">
                            <PanelPagos pagos={pagos} metodosPago={metodosPago} totalVenta={totalVenta} saldoPendiente={saldoPendiente} metodoDePago={metodoDePago} onAgregar={agregarPago} onSeleccionarMetodo={seleccionarMetodoPago} onActualizar={actualizarPago} onQuitar={quitarPago} />
                        </div>
                        {/* Solo total y cobro quedan fijos al pie: siempre visibles aunque el cobro mixto tenga muchas filas. */}
                        <div className="sticky bottom-0 z-10 shrink-0 space-y-3 bg-white p-3 shadow-[0_-10px_16px_-14px_rgb(15_23_42/0.35)] lg:static lg:pt-0 lg:shadow-none">
                            <div className="flex items-baseline justify-between rounded-xl bg-brand-soft px-4 py-3 ring-1 ring-brand-border">
                                <span className="text-sm font-medium text-brand-ink">Total a cobrar</span>
                                <span className="text-[1.75rem] leading-none font-semibold tracking-[-0.02em] text-slate-900 tabular-nums" aria-live="polite">{formatoMoneda.format(totalVenta)}</span>
                            </div>
                            <Button size="lg" block icon={CheckCircle2} onClick={iniciarCobro} disabled={processing || carrito.length === 0 || !data.id_cliente} aria-keyshortcuts="F9">
                                Cobrar y emitir comprobante
                                <Kbd className="ml-1 border-white/40 bg-white/15 text-current shadow-none">F9</Kbd>
                            </Button>
                        </div>
                    </section>
                </div>
            </div>

            <Dialog open={modalCargando} onClose={() => {}} dismissible={false} size="sm">
                <div className="flex flex-col items-center gap-3 px-6 py-8 text-center" role="status">
                    <Loader2 className="h-8 w-8 animate-spin text-brand-ink" aria-hidden="true" />
                    <p className="text-sm font-medium text-slate-900">Emitiendo comprobante…</p>
                </div>
            </Dialog>

            <DialogoConfirmarCobro
                open={modalConfirmacion}
                onClose={() => setModalConfirmacion(false)}
                onConfirmar={() => confirmarVenta()}
                tipoComprobante={data.tipo_comprobante}
                cliente={clienteActivo}
                mediosPago={metodoPagoNombre}
                total={totalVenta}
                pagos={pagos}
                metodoDePago={metodoDePago}
                onActualizarPago={actualizarPago}
            />

            <VentaRegistradaModal
                venta={modalExito}
                onClose={() => {
                    setModalExito(null);
                    enfocarBuscador();
                }}
            />

            <NoticeDialog
                notice={modalAlerta && { tone: 'warning', title: modalAlerta.titulo, message: modalAlerta.mensaje }}
                onClose={() => setModalAlerta(null)}
            />

            <DialogoAperturaCaja isOpen={mostrarAperturaCaja} onClose={() => setMostrarAperturaCaja(false)} />
        </AuthenticatedLayout>
    );
}
