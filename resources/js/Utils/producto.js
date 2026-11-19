/**
 * Identidad del producto SaaS (independiente de cada empresa cliente).
 * El nombre se configura con VITE_APP_NAME en .env; "Laravel" se ignora por ser el valor de fábrica.
 */
const nombreConfigurado = (import.meta.env.VITE_APP_NAME || '').trim();

export const NOMBRE_PRODUCTO = nombreConfigurado && nombreConfigurado.toLowerCase() !== 'laravel' ? nombreConfigurado : 'SVAMM';

export const DESCRIPCION_PRODUCTO = 'Ventas, inventario y caja para comercio mayorista y minorista';
