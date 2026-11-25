import { FormaTubo } from '@/Components/ui';

/*
 * Íconos de cocina y restaurante (wok al fuego, bowl con palitos, gorro de chef, olla
 * humeante, cubiertos, campana de servicio, taza de té, llama y comanda) dibujados como
 * tubos con volumen: degradado, brillo y sombra en el color de marca de Datos Generales
 * sobre un fondo claro. Solo geometría decorativa, sin imágenes. viewBox de 100 × 100.
 */
const ICONOS = {
    // Wok con su mango largo y dos hilos de vapor.
    wok: 'M8 46 H68 C68 66 54 78 38 78 C22 78 8 66 8 46 Z M68 52 L94 46 M26 34 C22 28 30 24 26 16 M44 34 C40 28 48 24 44 16',
    // Bowl con base y palitos chinos cruzando por encima.
    bowl: 'M10 50 H90 C90 72 72 86 50 86 C28 86 10 72 10 50 Z M40 86 V92 H60 V86 M54 42 L82 10 M64 44 L92 16',
    // Gorro de chef: copa abombada y banda con costura.
    chef: 'M28 60 C12 58 10 36 26 32 C26 16 44 10 50 22 C56 10 74 16 74 32 C90 36 88 58 72 60 Z M28 60 V84 H72 V60 M28 72 H72',
    // Olla con asas, tapa y vapor.
    olla: 'M18 46 H82 V76 C82 82 78 86 72 86 H28 C22 86 18 82 18 76 Z M8 50 H18 M82 50 H92 M14 46 H86 M32 34 C28 28 36 24 32 16 M50 34 C46 28 54 24 50 16 M68 34 C64 28 72 24 68 16',
    // Tenedor de tres dientes y cuchillo.
    cubiertos: 'M22 10 V32 C22 42 42 42 42 32 V10 M32 10 V34 M32 42 V90 M70 90 V10 C84 22 84 46 70 52',
    // Campana de servicio sobre su bandeja.
    campana: 'M16 72 C16 50 32 36 50 36 C66 36 84 50 84 72 Z M8 80 H92 M50 36 V28 M42 26 H58',
    // Taza de té con asa y vapor.
    taza: 'M16 42 H68 V62 C68 76 58 84 42 84 C26 84 16 76 16 62 Z M68 48 H76 a10 10 0 0 1 0 20 H68 M32 32 C28 26 36 22 32 14 M50 32 C46 26 54 22 50 14',
    // Llama del fuego de wok.
    llama: 'M50 90 C30 90 20 74 26 58 C30 48 40 42 38 26 C54 34 60 48 58 60 C62 56 64 50 64 44 C74 54 80 66 76 76 C72 86 62 90 50 90 Z',
    // Comanda con borde dentado y renglones de platos.
    comanda: 'M26 10 V88 L34 82 L42 88 L50 82 L58 88 L66 82 L74 88 V10 Z M37 28 H63 M37 42 H63 M37 56 H54',
};

/*
 * Órbita centrada en la tarjeta de acceso (960 × 720 px, la tarjeta ocupa el centro):
 * los íconos rodean el formulario a la misma distancia en cualquier pantalla, sin taparlo.
 * Pocos íconos y bien espaciados; los giros leves evitan que se vean alineados.
 */
const ORBITA = [
    // Izquierda
    { icono: 'wok', x: 130, y: 40, tam: 112, ancho: 8, rotacion: -8, animacion: 'animate-[flotar_11s_ease-in-out_infinite]' },
    { icono: 'chef', x: 40, y: 230, tam: 92, ancho: 8, rotacion: 10, animacion: 'animate-[flotar_9s_ease-in-out_infinite_reverse]' },
    { icono: 'cubiertos', x: 170, y: 400, tam: 72, ancho: 8, rotacion: -12, animacion: 'animate-[flotar_10s_ease-in-out_infinite]' },
    { icono: 'comanda', x: 60, y: 540, tam: 84, ancho: 8, rotacion: -10, animacion: 'animate-[flotar_12s_ease-in-out_infinite]' },
    // Derecha
    { icono: 'bowl', x: 710, y: 50, tam: 104, ancho: 8, rotacion: 6, animacion: 'animate-[flotar_13s_ease-in-out_infinite_reverse]' },
    { icono: 'llama', x: 810, y: 270, tam: 68, ancho: 8, rotacion: 0, animacion: 'animate-[flotar_8s_ease-in-out_infinite]' },
    { icono: 'campana', x: 720, y: 470, tam: 108, ancho: 8, rotacion: 4, animacion: 'animate-[flotar_9s_ease-in-out_infinite_reverse]' },
];

/** Grandes y desenfocados en las esquinas de la pantalla (profundidad de campo). */
const ESQUINAS = [
    { icono: 'olla', clase: '-right-10 bottom-[2%] w-72 opacity-25', ancho: 9, desenfoque: 4, rotacion: -10 },
    { icono: 'taza', clase: '-left-12 bottom-[0%] w-72 opacity-20', ancho: 9, desenfoque: 5, rotacion: 10 },
];

/*
 * Móvil: la tarjeta ocupa casi todo el ancho, así que los íconos van pequeños en la franja
 * de arriba y de abajo, asomando por los bordes, sin competir con el formulario.
 */
const MOVIL = [
    { icono: 'wok', clase: '-left-3 top-[3%] w-20', rotacion: -12, animacion: 'animate-[flotar_11s_ease-in-out_infinite]' },
    { icono: 'chef', clase: 'left-1/2 top-[1.5%] w-14 -translate-x-1/2 opacity-60', rotacion: 0, animacion: '' },
    { icono: 'bowl', clase: '-right-3 top-[4%] w-20', rotacion: 10, animacion: 'animate-[flotar_13s_ease-in-out_infinite_reverse]' },
    { icono: 'cubiertos', clase: 'left-2 bottom-[3%] w-14', rotacion: -14, animacion: 'animate-[flotar_10s_ease-in-out_infinite]' },
    { icono: 'llama', clase: 'right-3 bottom-[3%] w-14', rotacion: 6, animacion: 'animate-[flotar_9s_ease-in-out_infinite_reverse]' },
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
 * Capa decorativa. En pantallas medianas la órbita se reduce al 80 %; en móviles quedan
 * íconos pequeños arriba y abajo de la tarjeta, además de las formas desenfocadas.
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

            {MOVIL.map((forma, indice) => (
                <FormaTubo
                    key={`movil-${indice}`}
                    tono="marca"
                    d={ICONOS[forma.icono]}
                    viewBox="0 0 100 100"
                    ancho={8}
                    rotacion={forma.rotacion}
                    className={`absolute md:hidden ${forma.clase} ${forma.animacion}`}
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
