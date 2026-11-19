import { FiArchive, FiBarChart2, FiBookOpen, FiBox, FiCreditCard, FiFileText, FiGrid, FiHome, FiLayers, FiLayout, FiMapPin, FiPackage, FiShoppingCart, FiSliders, FiTag, FiTruck, FiUsers, FiDollarSign, FiPrinter } from 'react-icons/fi';

/** Rutas visibles; la autorización real continúa en middleware y policies del servidor. */
export const navigationModules = [
    { label: 'Principal', items: [
        { label: 'Inicio', href: '/', icon: FiHome },
    ] },
    { label: 'Restaurante', items: [
        { label: 'Salón', href: '/salon', icon: FiLayout },
        { label: 'Carta', href: '/carta', icon: FiBookOpen },
    ] },
    { label: 'Operaciones', items: [
        { label: 'Caja', href: '/caja', icon: FiDollarSign },
        { label: 'Ventas', href: '/ventas', icon: FiShoppingCart },
        { label: 'Entradas', href: '/ingresos', icon: FiArchive },
        { label: 'Ajustes de inventario', href: '/salidas', icon: FiBox },
        { label: 'Kardex', href: '/kardex', icon: FiBarChart2 },
    ] },
    { label: 'Gestión', items: [
        { label: 'Productos', href: '/productos', icon: FiPackage },
        { label: 'Clientes', href: '/clientes', icon: FiUsers },
        { label: 'Proveedores', href: '/proveedores', icon: FiTruck },
        { label: 'Categorías', href: '/categorias', icon: FiTag },
        { label: 'Presentaciones', href: '/presentaciones', icon: FiLayers },
    ] },
    { label: 'Configuración', items: [
        { label: 'Datos generales', href: '/datos-generales', icon: FiSliders },
        { label: 'Sucursales', href: '/sucursales', icon: FiMapPin },
        { label: 'Usuarios y roles', href: '/usuarios', icon: FiUsers },
        { label: 'Roles y permisos', href: '/roles', icon: FiGrid },
        { label: 'Comprobantes', href: '/tipos-comprobante', icon: FiFileText },
        { label: 'Documentos de identidad', href: '/tipos-documento', icon: FiFileText },
        { label: 'Métodos de pago', href: '/metodos-pago', icon: FiCreditCard },
        { label: 'Impresoras y terminales', href: '/impresoras', icon: FiPrinter },
        { label: 'Unidades de medida', href: '/unidades', icon: FiBox },
        { label: 'Marcas', href: '/marcas', icon: FiGrid },
    ] },
];

/** Ícono del módulo cuya ruta coincide con `url` (la más específica gana). */
export function iconoDeRuta(url = '/') {
    const ruta = url.split('?')[0];
    let mejor = null;
    navigationModules.forEach((grupo) => grupo.items.forEach((item) => {
        const coincide = item.href === '/' ? ruta === '/' : ruta === item.href || ruta.startsWith(`${item.href}/`);
        if (coincide && (!mejor || item.href.length > mejor.href.length)) mejor = item;
    }));
    return mejor?.icon ?? null;
}
