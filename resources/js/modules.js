import {
    Boxes, BookOpen, Building2, ChartColumn, ChefHat, CreditCard, FileText, House, IdCard, Layers, LayoutGrid, MapPin,
    MonitorSmartphone, NotebookText, Package, PackageMinus, PackagePlus, Printer, ReceiptText, Ruler, ShieldCheck, SlidersHorizontal,
    Tag, Trash2, Truck, UserCog, Users, Wallet,
} from 'lucide-react';

/** Rutas visibles; la autorización real continúa en middleware y policies del servidor. */
export const navigationModules = [
    { label: 'Principal', items: [
        { label: 'Inicio', href: '/', icon: House },
    ] },
    { label: 'Restaurante', items: [
        // Un solo punto de venta: mostrador, mesas y delivery.
        { label: 'Punto de venta', etiquetaCorta: 'Vender', href: '/pos', icon: MonitorSmartphone, permiso: 'pedidos.registrar' },
        { label: 'Salón', href: '/salon', icon: LayoutGrid },
        { label: 'Carta', href: '/carta', icon: BookOpen },
        { label: 'Cocina', href: '/cocina', icon: ChefHat, exacta: true, permiso: 'cocina.ver' },
        { label: 'Estaciones de cocina', href: '/cocina/estaciones', icon: SlidersHorizontal, permiso: 'cocina.configurar' },
        { label: 'Reportes del restaurante', href: '/reportes/restaurante', icon: ChartColumn, permiso: 'reportes.restaurante' },
    ] },
    { label: 'Operaciones', items: [
        { label: 'Caja', href: '/caja', icon: Wallet },
        { label: 'Comprobantes', href: '/ventas', icon: ReceiptText, exacta: true },
    ] },
    // Inventario con lenguaje de restaurante, sobre el kardex existente.
    { label: 'Inventario', items: [
        { label: 'Recetas e insumos', href: '/recetas', icon: NotebookText, permiso: 'recetas.gestionar' },
        { label: 'Entradas de insumos', href: '/ingresos', icon: PackagePlus },
        { label: 'Salidas y ajustes', href: '/salidas', icon: PackageMinus },
        { label: 'Mermas', href: '/mermas', icon: Trash2, permiso: 'mermas.registrar' },
        { label: 'Existencias', href: '/kardex', icon: Boxes },
        { label: 'Proveedores', href: '/proveedores', icon: Truck },
    ] },
    { label: 'Gestión', items: [
        { label: 'Productos', href: '/productos', icon: Package },
        { label: 'Clientes', href: '/clientes', icon: Users },
        { label: 'Categorías', href: '/categorias', icon: Tag },
        { label: 'Presentaciones', href: '/presentaciones', icon: Layers },
    ] },
    { label: 'Configuración', items: [
        { label: 'Datos generales', href: '/datos-generales', icon: Building2 },
        { label: 'Sucursales', href: '/sucursales', icon: MapPin },
        { label: 'Usuarios y roles', href: '/usuarios', icon: UserCog },
        { label: 'Roles y permisos', href: '/roles', icon: ShieldCheck },
        { label: 'Comprobantes', href: '/tipos-comprobante', icon: FileText },
        { label: 'Documentos de identidad', href: '/tipos-documento', icon: IdCard },
        { label: 'Métodos de pago', href: '/metodos-pago', icon: CreditCard },
        { label: 'Impresoras y terminales', href: '/impresoras', icon: Printer },
        { label: 'Unidades de medida', href: '/unidades', icon: Ruler },
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
