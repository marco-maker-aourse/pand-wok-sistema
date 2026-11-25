import { NOMBRE_PRODUCTO } from '@/Utils/producto';

/**
 * Pestaña del navegador con la identidad de Datos Generales: logo como ícono, nombre
 * comercial en el título y color de marca en la barra del navegador móvil. El servidor ya
 * la pinta en la primera carga (app.blade.php); aquí se mantiene al día al navegar, por
 * ejemplo justo después de cambiar el logo.
 */
let nombrePestana = NOMBRE_PRODUCTO;

export const tituloPestana = (titulo) => (titulo ? `${titulo} — ${nombrePestana}` : nombrePestana);

export function aplicarMarcaPestana(props = {}) {
    const info = props?.empresa?.id_empresa ? props.empresa : props?.organization;
    nombrePestana = info?.nombre_comercial || info?.razon_social || NOMBRE_PRODUCTO;
    if (typeof document === 'undefined') return;

    const icono = info?.logo_url || '/favicon.ico';
    document.querySelectorAll('link[data-marca]').forEach((enlace) => {
        if (enlace.getAttribute('href') !== icono) enlace.setAttribute('href', icono);
    });
    if (info?.color_primario) document.querySelector('meta[name="theme-color"]')?.setAttribute('content', info.color_primario);
}
