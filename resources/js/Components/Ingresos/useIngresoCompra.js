import { useEffect, useMemo, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { toast } from '@/Components/ui';
import avisarErrorConsultaDocumento from '@/Utils/consultaDocumento';
import { MENSAJE_RUC, esRucValido, pareceNombre } from '@/Utils/documentos';

/** Los insumos se compran para la cocina: no tienen precio de venta al público. */
export const esInsumo = (item) => item?.tipo_producto === 'INSUMO';

const serieSugerida = (nombre = '') => (nombre.includes('FACTURA') ? 'F001' : nombre.includes('BOLETA') ? 'B001' : 'T001');

const docProveedor = (p) => String(p?.num_documento || p?.num_document || p?.ruc || '');
const nombreProveedor = (p) => String(p?.razon_social || p?.nombre_razon_social || '');

/**
 * Estado y reglas de la entrada de mercadería: proveedor (con consulta de RUC), comprobante
 * y líneas con presentación, cantidad y costo. El servidor valida y
 * registra el ingreso (StoreIngresoRequest / RegistrarIngresoService).
 */
export default function useIngresoCompra({ productos, proveedores, tiposComprobante, productoInicialId, onRegistrado }) {
    const [carrito, setCarrito] = useState([]);
    const [listaProveedores, setListaProveedores] = useState(proveedores);
    const [searchProv, setSearchProv] = useState('');
    const [proveedorActivo, setProveedorActivo] = useState(null);
    const [buscandoSunat, setBuscandoSunat] = useState(false);
    const [listaAbierta, setListaAbierta] = useState(false);
    const productoInicialProcesado = useRef(false);

    useEffect(() => { setListaProveedores(proveedores); }, [proveedores]);

    const hoy = new Date().toISOString().split('T')[0];
    const comprobanteInicial = tiposComprobante[0];

    const form = useForm({
        id_proveedor: '',
        id_tipo_comprobante: comprobanteInicial?.id_tipo_comprobante || '',
        tipo_comprobante: comprobanteInicial?.descripcion?.toUpperCase() || 'FACTURA',
        serie_comprobante: serieSugerida(comprobanteInicial?.descripcion?.toUpperCase()),
        num_comprobante: '',
        fecha_emision: hoy,
        detalles: [],
    });
    const { data, setData } = form;

    useEffect(() => { setData('detalles', carrito); }, [carrito]); // eslint-disable-line react-hooks/exhaustive-deps

    const proveedoresFiltrados = useMemo(() => {
        const q = searchProv.toLowerCase().trim();
        if (!q) return listaProveedores.slice(0, 10);
        return listaProveedores.filter((p) => docProveedor(p).trim().includes(q) || nombreProveedor(p).toLowerCase().includes(q));
    }, [listaProveedores, searchProv]);

    const seleccionarProveedor = (prov) => {
        setData((prev) => ({ ...prev, id_proveedor: prov.id_proveedor }));
        setProveedorActivo(prov);
        setSearchProv(nombreProveedor(prov) || docProveedor(prov));
        setListaAbierta(false);
    };

    const cambiarBusquedaProveedor = (val) => {
        setSearchProv(val);
        setListaAbierta(true);
        if (proveedorActivo && docProveedor(proveedorActivo) !== val && nombreProveedor(proveedorActivo) !== val) {
            setProveedorActivo(null);
            setData('id_proveedor', '');
        }
        // Un RUC de 11 dígitos sugiere factura.
        if (/^\d{11}$/.test(val.trim())) {
            const idFactura = tiposComprobante.find((t) => (t.nombre || t.descripcion || '').toUpperCase().includes('FACTURA'))?.id_tipo_comprobante || 1;
            setData((prev) => ({ ...prev, tipo_comprobante: 'FACTURA', id_tipo_comprobante: idFactura, serie_comprobante: 'F001' }));
        }
    };

    const limpiarProveedor = () => {
        setSearchProv('');
        setProveedorActivo(null);
        setData('id_proveedor', '');
    };

    const cambiarTipoComprobante = (id) => {
        const comp = tiposComprobante.find((t) => t.id_tipo_comprobante === id);
        const nombre = comp?.descripcion?.toUpperCase() || 'FACTURA';
        setData((prev) => ({ ...prev, id_tipo_comprobante: id, tipo_comprobante: nombre, serie_comprobante: serieSugerida(nombre) }));
    };

    /** Lupa: busca entre los registrados por nombre; por RUC, en el padrón y lo registra. */
    const buscarProveedor = async () => {
        const termino = searchProv.trim();
        if (!termino) {
            toast.warning('Escribe el RUC o la razón social del proveedor.');
            return;
        }

        if (pareceNombre(termino)) {
            if (proveedoresFiltrados.length === 1) {
                seleccionarProveedor(proveedoresFiltrados[0]);
            } else {
                setListaAbierta(true);
                if (proveedoresFiltrados.length > 1) toast.warning('Varios proveedores coinciden', 'Elige el correcto en la lista.');
                else toast.warning('Proveedor no registrado', 'Para consultar el padrón de SUNAT ingresa el RUC de 11 dígitos.');
            }
            return;
        }

        const soloDigitos = termino.replace(/\s/g, '');
        // Misma regla que el servidor (App\Rules\Ruc).
        if (!esRucValido(soloDigitos)) {
            toast.warning('RUC inválido', `${MENSAJE_RUC} También puedes escribir la razón social de un proveedor registrado.`);
            return;
        }

        const existente = listaProveedores.find((p) => docProveedor(p).replace(/\D/g, '').trim() === soloDigitos);
        if (existente) {
            seleccionarProveedor(existente);
            return;
        }

        setBuscandoSunat(true);
        try {
            const url = typeof route === 'function' ? route('documentos.lookup') : '/api/documentos/consulta';
            const res = await axios.post(url, { context: 'proveedor', tipo: 'ruc', documento: soloDigitos });
            const doc = res.data?.data || res.data;
            const razonSocial = doc?.nombre || doc?.razon_social || doc?.nombre_razon_social;
            const direccion = doc?.direccion || doc?.direccion_fiscal || '';
            if (!razonSocial) throw new Error('El RUC ingresado no devolvió datos en el padrón.');

            // Contrato de StoreProveedorRequest: ruc, razon_social y direccion.
            const guardado = await axios.post('/proveedores', {
                ruc: soloDigitos,
                razon_social: String(razonSocial).toUpperCase().slice(0, 255),
                direccion: String(direccion).toUpperCase().slice(0, 255) || null,
                estado: true,
            }, { headers: { Accept: 'application/json' } });

            // Solo se selecciona el proveedor que el servidor confirmó haber guardado.
            const nuevo = guardado.data?.data;
            if (!nuevo?.id_proveedor) throw new Error('El proveedor no se pudo registrar. Inténtalo nuevamente.');
            setListaProveedores((prev) => [nuevo, ...prev]);
            seleccionarProveedor(nuevo);
            toast.success('Proveedor registrado', nombreProveedor(nuevo));
        } catch (error) {
            avisarErrorConsultaDocumento(error);
        } finally {
            setBuscandoSunat(false);
        }
    };

    const agregar = (producto) => {
        const presentaciones = producto.presentaciones || [];
        const porDefecto = presentaciones.find((p) => Number(p.factor_conversion) === 1) ?? presentaciones[0] ?? null;
        const insumo = esInsumo(producto);
        const pvp = porDefecto?.precio_venta ? Number(porDefecto.precio_venta) : Number(producto.precio_venta || 0);

        setCarrito((actual) => [{
            uid: `${producto.id_producto}_${Date.now()}`,
            id_producto: producto.id_producto,
            id_prod_pres: porDefecto ? porDefecto.id_prod_pres : null,
            nombre: producto.nombre,
            tipo_producto: producto.tipo_producto,
            presentaciones,
            unidad_base: producto.unidad_base,
            factor_conversion: porDefecto ? Number(porDefecto.factor_conversion) : 1,
            cantidad: 1,
            precio_costo: '',
            // Sin precio de venta el servidor no toca el PVP (RegistrarIngresoService).
            porcentaje_utilidad: insumo ? '' : 18,
            precio_venta: insumo ? '' : Number(pvp.toFixed(2)),
            subtotal: 0,
        }, ...actual]);
    };

    useEffect(() => {
        if (productoInicialProcesado.current || !productoInicialId || carrito.length > 0) return;
        const producto = productos.find((item) => Number(item.id_producto) === Number(productoInicialId));
        if (producto) {
            productoInicialProcesado.current = true;
            agregar(producto);
        }
    }, [productoInicialId, productos, carrito.length]); // eslint-disable-line react-hooks/exhaustive-deps

    const cambiarPresentacion = (uid, idProdPres) => setCarrito((actual) => actual.map((item) => {
        if (item.uid !== uid) return item;
        const pres = item.presentaciones.find((p) => String(p.id_prod_pres) === String(idProdPres));
        const factor = pres ? Number(pres.factor_conversion) : 1;
        if (esInsumo(item)) return { ...item, id_prod_pres: pres ? pres.id_prod_pres : item.id_prod_pres, factor_conversion: factor };
        const nuevoPVP = pres?.precio_venta ? Number(pres.precio_venta) : item.precio_venta;
        const costo = Number(item.precio_costo) || 0;
        const porc = costo > 0 ? Number((((nuevoPVP - costo) / costo) * 100).toFixed(1)) : item.porcentaje_utilidad;
        return { ...item, id_prod_pres: pres ? pres.id_prod_pres : item.id_prod_pres, factor_conversion: factor, precio_venta: nuevoPVP, porcentaje_utilidad: porc };
    }));

    /** Costo, margen y precio de venta se mantienen sincronizados entre sí. */
    const actualizar = (uid, campo, valor) => setCarrito((actual) => actual.map((item) => {
        if (item.uid !== uid) return item;
        const nuevo = { ...item };
        if (campo === 'cantidad') {
            const normalizada = String(valor).replace(',', '.').replace(/[^\d.]/g, '');
            const [entero = '', ...decimales] = normalizada.split('.');
            nuevo.cantidad = decimales.length ? `${entero}.${decimales.join('').slice(0, 4)}` : entero;
            nuevo.subtotal = (nuevo.cantidad === '' ? 0 : Number(nuevo.cantidad)) * (Number(item.precio_costo) || 0);
        } else if (campo === 'precio_costo') {
            nuevo.precio_costo = valor;
            const costo = parseFloat(valor) || 0;
            nuevo.subtotal = (Number(item.cantidad) || 0) * costo;
            const porc = parseFloat(item.porcentaje_utilidad);
            if (!Number.isNaN(porc) && porc > 0 && costo > 0) {
                nuevo.precio_venta = Number((costo * (1 + porc / 100)).toFixed(2));
            } else if (costo > 0 && Number(item.precio_venta) > 0) {
                nuevo.porcentaje_utilidad = Number((((Number(item.precio_venta) - costo) / costo) * 100).toFixed(1));
            }
        } else if (campo === 'porcentaje_utilidad') {
            nuevo.porcentaje_utilidad = valor;
            const porc = parseFloat(valor);
            const costo = Number(item.precio_costo) || 0;
            if (!Number.isNaN(porc) && costo > 0) nuevo.precio_venta = Number((costo * (1 + porc / 100)).toFixed(2));
        } else if (campo === 'precio_venta') {
            nuevo.precio_venta = valor;
            const venta = parseFloat(valor) || 0;
            const costo = Number(item.precio_costo) || 0;
            if (costo > 0 && venta > 0) nuevo.porcentaje_utilidad = Number((((venta - costo) / costo) * 100).toFixed(1));
        } else {
            nuevo[campo] = valor;
        }
        return nuevo;
    }));

    const normalizarCantidad = (uid) => setCarrito((actual) => actual.map((item) => {
        if (item.uid !== uid) return item;
        const cantidad = Number(item.cantidad) || 1;
        return { ...item, cantidad, subtotal: cantidad * (Number(item.precio_costo) || 0) };
    }));

    const quitar = (uid) => setCarrito((actual) => actual.filter((item) => item.uid !== uid));
    const total = carrito.reduce((suma, item) => suma + item.subtotal, 0);

    /** Qué falta para registrar; null si se puede. Se muestra junto al botón. */
    const faltante = carrito.length === 0 ? 'Agrega al menos un producto.'
        : !data.id_proveedor ? 'Identifica al proveedor por RUC o razón social.'
        : !data.serie_comprobante || !data.num_comprobante ? 'Completa la serie y el número del comprobante del proveedor.'
        : null;

    const registrar = () => {
        if (faltante) {
            toast.warning('Falta completar la compra', faltante);
            return;
        }
        form.post('/ingresos', {
            preserveScroll: true,
            onSuccess: () => onRegistrado({
                tipo: data.tipo_comprobante,
                serie: data.serie_comprobante,
                numero: data.num_comprobante,
                proveedor: nombreProveedor(proveedorActivo) || 'Proveedor',
                total,
                articulos: carrito.length,
            }),
            onError: (errores) => toast.error('No se pudo registrar la compra', Object.values(errores)[0] || 'Revisa los datos e inténtalo de nuevo.'),
        });
    };

    return {
        form, data, setData, carrito, total, faltante,
        proveedor: {
            busqueda: searchProv, activo: proveedorActivo, filtrados: proveedoresFiltrados, buscando: buscandoSunat,
            listaAbierta, setListaAbierta, cambiarBusqueda: cambiarBusquedaProveedor, seleccionar: seleccionarProveedor, limpiar: limpiarProveedor, buscar: buscarProveedor,
        },
        cambiarTipoComprobante, agregar, cambiarPresentacion, actualizar, normalizarCantidad, quitar, registrar,
    };
}

export { docProveedor, nombreProveedor };
