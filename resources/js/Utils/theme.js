/**
 * Identidad visual configurable.
 *
 * La fuente de verdad es `color_primario` de Datos Generales, que el servidor
 * comparte en cada respuesta Inertia como `organization`. Este módulo solo
 * traduce ese color a variables CSS; los tonos derivados (hover, fondo suave,
 * borde) se calculan en `resources/css/app.css` con `color-mix`.
 */

export const COLOR_MARCA_POR_DEFECTO = '#0e7490';

/** Paletas sugeridas en Datos Generales. Solo se usa `color`; el resto se deriva. */
export const PALETAS_SISTEMA = [
    { id: 'azul_institucional', nombre: 'Azul institucional', rubro: 'Banca y finanzas', color: '#1e40af' },
    { id: 'petroleo', nombre: 'Azul petróleo', rubro: 'Tecnología y logística', color: '#0e7490' },
    { id: 'esmeralda', nombre: 'Verde esmeralda', rubro: 'Farmacia y salud', color: '#047857' },
    { id: 'bosque', nombre: 'Verde bosque', rubro: 'Agro y naturaleza', color: '#166534' },
    { id: 'grafito', nombre: 'Grafito', rubro: 'Corporativo', color: '#18181b' },
    { id: 'zafiro_noche', nombre: 'Zafiro oscuro', rubro: 'Consultoría y legal', color: '#0f172a' },
    { id: 'borgona', nombre: 'Borgoña', rubro: 'Gourmet y licorería', color: '#881337' },
    { id: 'rubi', nombre: 'Rojo carmesí', rubro: 'Restaurantes y alimentos', color: '#991b1b' },
    { id: 'ambar_cobre', nombre: 'Ámbar cobre', rubro: 'Ferretería y construcción', color: '#b45309' },
    { id: 'indigo_real', nombre: 'Índigo ejecutivo', rubro: 'Comercio mayorista', color: '#4338ca' },
];

export function normalizarHex(valor) {
    if (typeof valor !== 'string') return null;
    let hex = valor.trim().replace(/^#/, '');
    if (/^[0-9a-f]{3}$/i.test(hex)) hex = hex.split('').map((c) => c + c).join('');
    return /^[0-9a-f]{6}$/i.test(hex) ? `#${hex.toLowerCase()}` : null;
}

function aRgb(hex) {
    const num = parseInt(hex.slice(1), 16);
    return [(num >> 16) & 255, (num >> 8) & 255, num & 255];
}

function aHex(rgb) {
    return `#${rgb.map((v) => Math.round(v).toString(16).padStart(2, '0')).join('')}`;
}

function luminancia(rgb) {
    const [r, g, b] = rgb.map((v) => {
        const c = v / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contraste(hexA, hexB) {
    const [l1, l2] = [luminancia(aRgb(hexA)), luminancia(aRgb(hexB))].sort((a, b) => b - a);
    return (l1 + 0.05) / (l2 + 0.05);
}

/** Oscurece el color hasta alcanzar el contraste pedido sobre blanco (texto e íconos de marca). */
function tintaLegible(hex, minimo = 4.5) {
    let rgb = aRgb(hex);
    for (let i = 0; i < 20 && contraste(aHex(rgb), '#ffffff') < minimo; i += 1) {
        rgb = rgb.map((v) => v * 0.9);
    }
    return aHex(rgb);
}

/** Variables que dependen del color de marca y no pueden resolverse solo con CSS. */
export function construirTokensMarca(valor) {
    const hex = normalizarHex(valor) ?? COLOR_MARCA_POR_DEFECTO;
    const textoSobreMarca = contraste(hex, '#ffffff') >= contraste(hex, '#0f172a') ? '#ffffff' : '#0f172a';

    return {
        '--brand-primary': hex,
        '--brand-on': textoSobreMarca,
        '--brand-ink': tintaLegible(hex),
    };
}

export function aplicarColorMarca(valor) {
    if (typeof document === 'undefined') return;
    const tokens = construirTokensMarca(valor);
    const root = document.documentElement;
    Object.entries(tokens).forEach(([nombre, valorToken]) => root.style.setProperty(nombre, valorToken));
}

/** Lee el color configurado desde las props compartidas por HandleInertiaRequests. */
export function colorMarcaDesdeProps(props = {}) {
    const empresa = props.organization ?? props.empresa ?? null;
    return normalizarHex(empresa?.color_primario) ?? COLOR_MARCA_POR_DEFECTO;
}

export function paletaPorColor(valor) {
    const hex = normalizarHex(valor);
    return PALETAS_SISTEMA.find((paleta) => paleta.color === hex) ?? null;
}
