/**
 * Menú visible por rol. Solo decide qué enlaces se muestran: la autorización
 * real sigue en los middleware y policies del servidor.
 */
const PERMISOS_POR_ROL = {
    COCINA: { grupos: [], items: [] },
    CAJERO: {
        grupos: ['Principal', 'Restaurante', 'Operaciones', 'Gestión'],
        items: ['Inicio', 'Salón', 'Caja', 'Ventas', 'Productos', 'Clientes'],
    },
    MOZO: {
        grupos: ['Principal', 'Restaurante'],
        items: ['Inicio', 'Salón'],
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

export function filtrarNavegacionPorRol(modulos = [], rolNombre = '', rolId = 0, permisos = []) {
    const rol = (rolNombre || '').trim().toUpperCase();

    if (rol === 'ADMINISTRADOR GENERAL') return modulos;
    if (ROLES_ADMINISTRADOR.includes(rol) || rolId === 1) {
        return modulos.map((grupo) => ({
            ...grupo,
            items: grupo.items.filter((item) => !item.permiso || permisos.includes(item.permiso)),
        }));
    }

    let config = PERMISOS_POR_ROL[rol];
    if (!config && rolId === 2) config = PERMISOS_POR_ROL.CAJERO;
    if (!config && rolId === 3) config = PERMISOS_POR_ROL.ALMACENERO;
    config ??= { grupos: ['Principal'], items: ['Inicio'] };

    const grupos = config.grupos.map((g) => g.toLowerCase());
    const items = config.items.map((i) => i.toLowerCase());

    return modulos
        .map((grupo) => ({
            ...grupo,
            items: (grupo.items || []).filter((item) => item.permiso
                ? permisos.includes(item.permiso)
                : grupos.includes(grupo.label.toLowerCase()) && items.includes(item.label.toLowerCase())),
        }))
        .filter((grupo) => grupo.items.length > 0);
}

export function esRutaActiva(url, href, exacta = false) {
    if (!href) return false;
    const ruta = url.split('?')[0];
    return ruta === href || (!exacta && href !== '/' && ruta.startsWith(`${href}/`));
}
