import { useState } from 'react';
import { Check } from 'lucide-react';
import { cx } from '@/Components/ui';
import { PALETAS_SISTEMA, contraste, normalizarHex, paletaPorColor } from '@/Utils/theme';

/**
 * Selector del color de marca: paletas sugeridas o un HEX libre.
 * `onChange` solo recibe colores válidos (#rrggbb).
 */
export default function SelectorColorMarca({ value, onChange, error }) {
    const [hexEscrito, setHexEscrito] = useState(value);
    const hexValido = normalizarHex(value);
    const paletaActual = paletaPorColor(value);
    const contrasteTexto = hexValido ? contraste(hexValido, '#ffffff') : 0;

    const cambiarHex = (texto) => {
        const conAlmohadilla = texto.startsWith('#') ? texto : `#${texto}`;
        setHexEscrito(conAlmohadilla);
        const hex = normalizarHex(conAlmohadilla);
        if (hex && conAlmohadilla.length === 7) onChange(hex);
    };

    const elegir = (hex) => {
        setHexEscrito(hex);
        onChange(hex);
    };

    return (
        <div className="space-y-4">
            <fieldset>
                <legend className="mb-2 text-xs font-medium text-slate-700">Paletas sugeridas</legend>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 2xl:grid-cols-5" role="radiogroup">
                    {PALETAS_SISTEMA.map((paleta) => {
                        const seleccionada = paletaActual?.id === paleta.id;
                        return (
                            <button
                                key={paleta.id}
                                type="button"
                                role="radio"
                                aria-checked={seleccionada}
                                onClick={() => elegir(paleta.color)}
                                className={cx(
                                    'flex items-center gap-2.5 rounded-lg border p-2 text-left transition-colors',
                                    seleccionada ? 'border-slate-900 bg-slate-50' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50',
                                )}
                            >
                                <span className="grid h-7 w-7 shrink-0 place-items-center rounded-md text-white" style={{ backgroundColor: paleta.color }}>
                                    {seleccionada && <Check className="h-3.5 w-3.5" aria-hidden="true" />}
                                </span>
                                <span className="min-w-0">
                                    <span className="block truncate text-xs font-medium text-slate-800">{paleta.nombre}</span>
                                    <span className="block truncate text-xs text-slate-500">{paleta.rubro}</span>
                                </span>
                            </button>
                        );
                    })}
                </div>
            </fieldset>

            <div className="flex flex-wrap items-end gap-3">
                <div>
                    <label htmlFor="color-marca-hex" className="mb-1.5 block text-xs font-medium text-slate-700">Color personalizado (HEX)</label>
                    <div className="flex items-center gap-2">
                        <input
                            type="color"
                            aria-label="Elegir color con el selector visual"
                            value={hexValido ?? '#0e7490'}
                            onChange={(e) => elegir(e.target.value)}
                            className="h-[2.375rem] w-11 cursor-pointer rounded-lg border border-slate-300 bg-white p-1"
                        />
                        <input
                            id="color-marca-hex"
                            type="text"
                            maxLength={7}
                            value={hexEscrito}
                            onChange={(e) => cambiarHex(e.target.value.trim())}
                            onBlur={() => setHexEscrito(hexValido ?? value)}
                            aria-invalid={error ? true : undefined}
                            placeholder="#0E7490"
                            className="w-32 px-3 font-mono text-sm uppercase"
                        />
                    </div>
                </div>
                <p className={cx('pb-2 text-xs', contrasteTexto < 3 ? 'text-amber-700' : 'text-slate-500')}>
                    {contrasteTexto < 3
                        ? 'Color muy claro: los textos de marca se oscurecerán automáticamente para mantener la legibilidad.'
                        : 'Se aplica a botones principales, navegación activa e indicadores seleccionados.'}
                </p>
            </div>
            {error && <p className="text-xs font-medium text-rose-600" role="alert">{error}</p>}
        </div>
    );
}
