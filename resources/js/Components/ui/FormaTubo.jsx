import { useId } from 'react';

/*
 * Tubo vectorial con volumen (degradado, brillo y sombra) teñido con el color de marca.
 * Es un acento 3D estático: no se anima ni se recalcula durante el uso.
 *
 * tono="claro" (por defecto): casi blanco, pensado para fondos del color de marca.
 * tono="marca": cuerpo en el color de Datos Generales, pensado para fondos claros.
 */

const TONOS = {
    claro: {
        luz: '#ffffff',
        media: 'color-mix(in srgb, var(--brand-primary) 35%, #ffffff)',
        sombra: 'color-mix(in srgb, var(--brand-primary) 75%, #000000)',
        opacidadSombra: 0.28,
    },
    marca: {
        luz: 'color-mix(in srgb, var(--brand-primary) 30%, #ffffff)',
        media: 'var(--brand-primary)',
        sombra: 'color-mix(in srgb, var(--brand-primary) 55%, #000000)',
        opacidadSombra: 0.18,
    },
};

/** Centro del viewBox ("x y ancho alto") para rotar la forma sobre sí misma. */
const centro = (viewBox = '0 0 100 100') => {
    const [x, y, w, h] = viewBox.split(/\s+/).map(Number);
    return `${x + w / 2} ${y + h / 2}`;
};

export default function FormaTubo({ d, ancho = 26, viewBox, className, desenfoque = 0, tono = 'claro', rotacion = 0, style }) {
    const id = useId().replace(/:/g, '');
    const t = TONOS[tono] ?? TONOS.claro;
    return (
        <svg viewBox={viewBox} className={className} style={style} aria-hidden="true" fill="none" overflow="visible">
            <defs>
                <linearGradient id={`cuerpo-${id}`} x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" style={{ stopColor: t.luz }} />
                    <stop offset="45%" style={{ stopColor: t.media }} />
                    <stop offset="100%" style={{ stopColor: t.sombra }} />
                </linearGradient>
                <filter id={`sombra-${id}`} x="-30%" y="-30%" width="160%" height="160%">
                    <feDropShadow dx="0" dy="14" stdDeviation="12" style={{ floodColor: tono === 'marca' ? 'var(--brand-primary)' : '#000' }} floodOpacity={t.opacidadSombra} />
                    {desenfoque > 0 && <feGaussianBlur stdDeviation={desenfoque} />}
                </filter>
            </defs>
            {/* La rotación va dentro del SVG: la animación `flotar` usa el transform del elemento. */}
            <g filter={`url(#sombra-${id})`} strokeLinecap="round" strokeLinejoin="round" transform={rotacion ? `rotate(${rotacion} ${centro(viewBox)})` : undefined}>
                <path d={d} stroke={`url(#cuerpo-${id})`} strokeWidth={ancho} />
                {/* Brillo superior que da sensación de volumen. */}
                <path d={d} stroke="#ffffff" strokeOpacity="0.5" strokeWidth={ancho * 0.22} transform={`translate(${-ancho * 0.18} ${-ancho * 0.22})`} />
            </g>
        </svg>
    );
}
