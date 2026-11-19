/**
 * Reglas de documentos de identidad compartidas por las pantallas.
 * Deben coincidir con App\Rules\Ruc en el servidor, que es quien decide.
 */
export const PREFIJOS_RUC = ['10', '15', '16', '17', '20'];

export const MENSAJE_RUC = 'El RUC debe tener 11 dígitos y empezar con 10, 15, 16, 17 o 20.';

export const esRucValido = (valor) => /^\d{11}$/.test(String(valor ?? '')) && PREFIJOS_RUC.includes(String(valor).slice(0, 2));

/** Permite seguir escribiendo un RUC solo si su inicio puede formar un prefijo válido. */
export const prefijoRucPosible = (valor) => {
    const texto = String(valor ?? '');
    if (texto.length === 0) return true;
    if (texto.length === 1) return PREFIJOS_RUC.some((prefijo) => prefijo.startsWith(texto));
    return PREFIJOS_RUC.includes(texto.slice(0, 2));
};

/** Con letras, el texto se interpreta como nombre o razón social a buscar. */
export const pareceNombre = (valor) => /\p{L}/u.test(String(valor ?? ''));
