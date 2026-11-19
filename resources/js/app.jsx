import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { aplicarColorMarca, colorMarcaDesdeProps } from '@/Utils/theme';

import { NOMBRE_PRODUCTO } from '@/Utils/producto';

const appName = NOMBRE_PRODUCTO;

const routes = {
    dashboard: '/',
    'datos-generales.index': '/datos-generales',
    'datos-generales.update': '/datos-generales',
    login: '/login',
    'login.store': '/login',
    'password.confirm': '/user/confirm-password',
    'password.email': '/forgot-password',
    'password.request': '/forgot-password',
    'password.store': '/reset-password',
    'ventas.index': '/ventas',
    'ventas.store': '/ventas',
    'ingresos.index': '/ingresos',
    'ingresos.store': '/ingresos',
    'salidas.create': '/salidas',
    'salidas.index': '/salidas',
    'salidas.store': '/salidas',
    'kardex.index': '/kardex',
    'productos.index': '/productos',
    'productos.store': '/productos',
    'productos.update': '/productos',
    'productos.destroy': '/productos',
    'unidades.index': '/unidades',
    'unidades.store': '/unidades',
    'unidades.update': '/unidades',
    'unidades.destroy': '/unidades',
    'presentaciones.index': '/presentaciones',
    'presentaciones.store': '/presentaciones',
    'presentaciones.update': '/presentaciones',
    'presentaciones.destroy': '/presentaciones',
    'categorias.index': '/categorias',
    'categorias.store': '/categorias',
    'categorias.update': '/categorias',
    'categorias.destroy': '/categorias',
    'clientes.store': '/clientes',
    'marcas.index': '/marcas',
    'marcas.store': '/marcas',
    'marcas.update': '/marcas',
    'marcas.destroy': '/marcas',
    'clientes.index': '/clientes',
    'clientes.update': '/clientes',
    'clientes.destroy': '/clientes',
    'proveedores.index': '/proveedores',
    'proveedores.store': '/proveedores',
    'proveedores.update': '/proveedores',
    'proveedores.destroy': '/proveedores',
    'inventario.index': '/inventario',
    'usuarios.index': '/usuarios',
    'usuarios.store': '/usuarios',
    'usuarios.update': '/usuarios',
    'usuarios.destroy': '/usuarios',
    'roles.index': '/roles',
    'roles.store': '/roles',
    'roles.update': '/roles',
    'roles.destroy': '/roles',
    'roles.permissions.update': '/roles/permisos',
    'tipos-comprobante.index': '/tipos-comprobante',
    'tipos-comprobante.store': '/tipos-comprobante',
    'tipos-comprobante.update': '/tipos-comprobante',
    'tipos-comprobante.destroy': '/tipos-comprobante',
    'tipos-documento.index': '/tipos-documento',
    'tipos-documento.store': '/tipos-documento',
    'tipos-documento.update': '/tipos-documento',
    'tipos-documento.destroy': '/tipos-documento',
    'metodos-pago.index': '/metodos-pago',
    'metodos-pago.store': '/metodos-pago',
    'metodos-pago.update': '/metodos-pago',
    'metodos-pago.destroy': '/metodos-pago',
    'impresoras.index': '/impresoras',
    'impresoras.store': '/impresoras',
    'impresoras.update': '/impresoras',
    'terminales-pos.store': '/terminales-pos',
    'terminales-pos.update': '/terminales-pos',
    'ia.preguntar': '/ia/preguntar',
    'ia.chat': '/ia/chat',
    'documentos.lookup': '/api/documentos/consulta',
    logout: '/logout',
};

window.route = (name, parameter) => {
    if (!name) {
        return {
            current: (pattern) => {
                const current = Object.entries(routes).find(([, path]) => path === window.location.pathname)?.[0] ?? '';
                return pattern.endsWith('.*') ? current.startsWith(pattern.slice(0, -1)) : current === pattern;
            },
        };
    }

    const path = routes[name];
    if (!path) {
        throw new Error(`Ruta frontend no registrada: ${name}`);
    }
    if (parameter === undefined) return path;

    const id = typeof parameter === 'object' ? Object.values(parameter)[0] : parameter;
    if (name === 'roles.permissions.update') return `/roles/${id}/permisos`;
    if (/\.(update|destroy)$/.test(name)) return `${path}/${id}`;

    return path;
};

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        // El color de Datos Generales llega en cada respuesta; se aplica antes del
        // primer render y en cada navegación para que un cambio se vea sin recargar.
        aplicarColorMarca(colorMarcaDesdeProps(props.initialPage?.props));
        router.on('navigate', (event) => aplicarColorMarca(colorMarcaDesdeProps(event.detail.page.props)));

        const root = createRoot(el);
        root.render(<App {...props} />);
    },
    progress: {
        color: 'var(--brand-primary)',
        // Solo aparece si la visita tarda; evita parpadeos en navegaciones rápidas.
        delay: 200,
        showSpinner: false,
    },
});
