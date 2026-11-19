/**
 * Menú visible por rol. Solo decide qué enlaces se muestran: la autorización
 * real sigue en los middleware y policies del servidor.
 */
const PERMISOS_POR_ROL = {
    CAJERO: {
        grupos: ['Principal', 'Operaciones', 'Gestión'],
        items: ['Inicio', 'Caja', 'Ventas', 'Productos', 'Clientes'],
    },
    VENDEDOR: {
        grupos: ['Principal', 'Operaciones', 'Gestión'],
        items: ['Inicio', 'Caja', 'Caja y ventas', 'Ventas', 'Kardex', 'Productos', 'Clientes'],
    },
    ALMACENERO: {
        grupos: ['Principal', 'Operaciones', 'Gestión', 'Catálogos'],
        items: [
            'Inicio',
            'Entradas',
            'Ajustes de inventario',
            'Kardex',
            'Productos',
            'Proveedores',
            'Categorías',
            'Presentaciones',
            'Marcas',
            'Unidades de medida',
        ],
    },
};

const ROLES_ADMINISTRADOR = ['ADMINISTRADOR', 'ADMINISTRADOR GENERAL'];

export function filtrarNavegacionPorRol(modulos = [], rolNombre = '', rolId = 0) {
    const rol = (rolNombre || '').trim().toUpperCase();

    if (ROLES_ADMINISTRADOR.includes(rol) || rolId === 1) {
        return modulos;
    }

    let config = PERMISOS_POR_ROL[rol];
    if (!config && rolId === 2) config = PERMISOS_POR_ROL.CAJERO;
    if (!config && rolId === 3) config = PERMISOS_POR_ROL.ALMACENERO;
    config ??= { grupos: ['Principal'], items: ['Inicio'] };

    const grupos = config.grupos.map((g) => g.toLowerCase());
    const items = config.items.map((i) => i.toLowerCase());

    return modulos
        .filter((grupo) => grupos.includes((grupo?.label || '').toLowerCase()))
        .map((grupo) => ({
            ...grupo,
            items: (grupo.items || []).filter((item) => items.includes((item?.label || '').toLowerCase())),
        }))
        .filter((grupo) => grupo.items.length > 0);
}

export function esRutaActiva(url, href) {
    if (!href) return false;
    const ruta = url.split('?')[0];
    return ruta === href || (href !== '/' && ruta.startsWith(`${href}/`));
}
