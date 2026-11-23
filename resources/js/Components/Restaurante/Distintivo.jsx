import { cx } from '@/Components/ui';

/** Colores fijos para que "Menú" y "Combinación" se distingan de un vistazo. */
const TONOS = {
    'Menú': 'bg-sky-100 text-sky-800',
    'Combinación': 'bg-violet-100 text-violet-800',
};

/**
 * Etiqueta de la categoría que diferencia platos parecidos (el "Chaufa con Lomo" de la
 * combinación y el "Chaufa c/Lomo" del menú). La decide el servidor (config/restaurante.php).
 */
export default function Distintivo({ texto, grande = false, className }) {
    if (!texto) return null;

    return (
        <span className={cx('inline-flex shrink-0 items-center rounded-md font-semibold', grande ? 'px-2 py-0.5 text-sm' : 'px-1.5 py-px text-xs',
            TONOS[texto] ?? 'bg-slate-100 text-slate-700', className)}>
            {texto}
        </span>
    );
}
