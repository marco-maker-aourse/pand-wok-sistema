import { FormaTubo } from '@/Components/ui';

/*
 * Íconos del negocio (carrito, tienda, caja, etiqueta, ticket, código de barras, bolsa,
 * moneda y camión de reparto) dibujados como tubos con volumen: degradado, brillo y sombra en el color de marca
 * de Datos Generales sobre un fondo claro. Solo geometría decorativa, sin imágenes.
 * Cada trazo usa un viewBox de 100 × 100.
 */

const ICONOS = {
    // Carrito: manija, canasta con su división y dos ruedas.
    carrito: 'M8 20 H21 L31 64 H77 L87 32 H26 M29 48 H82 M38 82 a6 6 0 1 0 12 0 a6 6 0 1 0 -12 0 M64 82 a6 6 0 1 0 12 0 a6 6 0 1 0 -12 0',
    // Tienda: techo con toldo festoneado, local y puerta.
    tienda: 'M14 38 L22 16 H78 L86 38 M14 38 a9 9 0 0 0 18 0 a9 9 0 0 0 18 0 a9 9 0 0 0 18 0 a9 9 0 0 0 18 0 M20 50 V86 H80 V50 M42 86 V64 H58 V86',
    // Caja de mercadería en perspectiva.
    caja: 'M50 12 L86 30 V70 L50 88 L14 70 V30 Z M14 30 L50 48 L86 30 M50 48 V88 M32 21 L68 39',
    // Etiqueta de precio con su ojal.
    etiqueta: 'M16 16 H52 L86 50 L50 86 L16 52 Z M30 34 a5 5 0 1 0 10 0 a5 5 0 1 0 -10 0',
    // Ticket con borde dentado y líneas de detalle.
    ticket: 'M26 10 V88 L34 82 L42 88 L50 82 L58 88 L66 82 L74 88 V10 Z M37 28 H63 M37 42 H63 M37 56 H54',
    // Código de barras con barras de distinta altura.
    barras: 'M18 20 V80 M28 20 V72 M38 20 V80 M50 20 V72 M60 20 V80 M70 20 V72 M82 20 V80',
    // Bolsa de compras.
    bolsa: 'M20 36 H80 L74 88 H26 Z M38 36 V28 a12 12 0 0 1 24 0 V36',
    // Moneda con el símbolo de soles.
    moneda: 'M14 50 a36 36 0 1 0 72 0 a36 36 0 1 0 -72 0 M60 36 C56 30 40 30 40 40 C40 50 60 48 60 58 C60 68 44 70 40 64',
    // Camión de reparto.
    camion: 'M8 28 H58 V68 H8 Z M58 42 H74 L88 56 V68 H58 M17 76 a7 7 0 1 0 14 0 a7 7 0 1 0 -14 0 M63 76 a7 7 0 1 0 14 0 a7 7 0 1 0 -14 0',
};

/*
 * Órbita centrada en la tarjeta de acceso (960 × 720 px, la tarjeta ocupa el centro):
 * los íconos rodean el formulario a la misma distancia en cualquier pantalla, sin taparlo.
 * Pocos íconos y bien espaciados; los giros leves evitan que se vean alineados.
 */
const ORBITA = [
    // Izquierda
    { icono: 'carrito', x: 140, y: 40, tam: 108, ancho: 8.5, rotacion: -8, animacion: 'animate-[flotar_11s_ease-in-out_infinite]' },
    { icono: 'etiqueta', x: 50, y: 240, tam: 84, ancho: 9, rotacion: 14, animacion: 'animate-[flotar_9s_ease-in-out_infinite_reverse]' },
    { icono: 'barras', x: 170, y: 400, tam: 68, ancho: 7, rotacion: -6, animacion: 'animate-[flotar_10s_ease-in-out_infinite]' },
    { icono: 'ticket', x: 60, y: 540, tam: 84, ancho: 8, rotacion: -10, animacion: 'animate-[flotar_12s_ease-in-out_infinite]' },
    // Derecha
    { icono: 'caja', x: 710, y: 50, tam: 100, ancho: 8, rotacion: 8, animacion: 'animate-[flotar_13s_ease-in-out_infinite_reverse]' },
    { icono: 'moneda', x: 800, y: 280, tam: 70, ancho: 9, rotacion: 0, animacion: 'animate-[flotar_8s_ease-in-out_infinite]' },
    { icono: 'tienda', x: 720, y: 480, tam: 104, ancho: 8, rotacion: 4, animacion: 'animate-[flotar_9s_ease-in-out_infinite_reverse]' },
];

/** Grandes y desenfocados en las esquinas de la pantalla (profundidad de campo). */
const ESQUINAS = [
    { icono: 'bolsa', clase: '-right-10 bottom-[2%] w-72 opacity-25', ancho: 10, desenfoque: 4, rotacion: -12 },
    { icono: 'carrito', clase: '-left-12 bottom-[0%] w-72 opacity-20', ancho: 10, desenfoque: 5, rotacion: 10 },
];

/** Fondo claro del acceso: base neutra con un halo muy tenue del color de marca. */
export const FONDO_ACCESO = {
    backgroundColor: '#f6f7f9',
    backgroundImage: 'radial-gradient(55% 50% at 50% 45%, color-mix(in srgb, var(--brand-primary) 9%, transparent), transparent 75%)',
};

const MASCARA_PUNTOS = 'radial-gradient(70% 65% at 50% 45%, #000 30%, transparent 85%)';

/** Retícula de puntos que se desvanece hacia los bordes. */
export function RejillaPuntos() {
    return (
        <div
            aria-hidden="true"
            className="pointer-events-none absolute inset-0"
            style={{
                backgroundImage: 'radial-gradient(rgb(15 23 42 / 0.09) 1px, transparent 1px)',
                backgroundSize: '22px 22px',
                maskImage: MASCARA_PUNTOS,
                WebkitMaskImage: MASCARA_PUNTOS,
            }}
        />
    );
}

/**
 * Capa decorativa. En pantallas medianas la órbita se reduce al 80 %; en móviles solo quedan
 * las formas desenfocadas de las esquinas para no competir con el formulario.
 */
export default function FormasFlotantes() {
    return (
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden">
            {ESQUINAS.map((forma, indice) => (
                <FormaTubo
                    key={`esquina-${indice}`}
                    tono="marca"
                    d={ICONOS[forma.icono]}
                    viewBox="0 0 100 100"
                    ancho={forma.ancho}
                    desenfoque={forma.desenfoque}
                    rotacion={forma.rotacion}
                    className={`absolute ${forma.clase}`}
                />
            ))}

            <div className="absolute left-1/2 top-1/2 hidden h-[720px] w-[960px] -translate-x-1/2 -translate-y-1/2 scale-[0.8] md:block xl:scale-100">
                {ORBITA.map((forma, indice) => (
                    <FormaTubo
                        key={`orbita-${indice}`}
                        tono="marca"
                        d={ICONOS[forma.icono]}
                        viewBox="0 0 100 100"
                        ancho={forma.ancho}
                        desenfoque={forma.desenfoque}
                        rotacion={forma.rotacion}
                        className={`absolute ${forma.animacion}`}
                        style={{ left: forma.x, top: forma.y, width: forma.tam, opacity: forma.opacidad }}
                    />
                ))}
            </div>
        </div>
    );
}
