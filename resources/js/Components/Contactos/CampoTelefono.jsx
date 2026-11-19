import { Globe } from 'lucide-react';
import { cx } from '@/Components/ui';

export const TIPOS_TELEFONO = [
    { id: 'movil_pe', etiqueta: 'Móvil PE' },
    { id: 'fijo_pe', etiqueta: 'Fijo PE' },
    { id: 'internacional', etiqueta: 'Internacional' },
];

/** Deja solo los caracteres válidos para el formato elegido y respeta su longitud máxima. */
export function limpiarTelefono(tipo, valor) {
    if (tipo === 'movil_pe') return valor.replace(/\D/g, '').slice(0, 9);
    if (tipo === 'fijo_pe') return valor.replace(/[^\d-]/g, '').slice(0, 11);
    return valor.replace(/[^\d+\s-]/g, '').slice(0, 18);
}

/**
 * Teléfono con selector de formato (móvil peruano, fijo peruano o internacional).
 * Compartido por clientes y proveedores; la validación final sigue en cada formulario.
 */
export default function CampoTelefono({ tipo, onTipo, value, onChange, error, ejemploFijo = '064-241122', ejemploInternacional = '+54 9 11 4321-5678' }) {
    const placeholder = tipo === 'movil_pe' ? '9XXXXXXXX' : tipo === 'fijo_pe' ? ejemploFijo : ejemploInternacional;
    const prefijo = tipo === 'movil_pe' ? '+51' : tipo === 'fijo_pe' ? 'Fijo' : <Globe className="h-3.5 w-3.5" aria-hidden="true" />;

    return (
        <div>
            <div className="mb-1.5 flex flex-wrap items-center justify-between gap-2">
                <label htmlFor="campo-telefono" className="text-xs font-medium text-slate-700">Teléfono</label>
                <div className="flex rounded-md bg-slate-100 p-0.5 text-xs" role="radiogroup" aria-label="Formato de teléfono">
                    {TIPOS_TELEFONO.map((opcion) => (
                        <button
                            key={opcion.id}
                            type="button"
                            role="radio"
                            aria-checked={tipo === opcion.id}
                            onClick={() => onTipo(opcion.id)}
                            className={cx('rounded px-2 py-0.5', tipo === opcion.id ? 'bg-white font-medium text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800')}
                        >
                            {opcion.etiqueta}
                        </button>
                    ))}
                </div>
            </div>
            <div className="flex overflow-hidden rounded-lg border border-slate-300 bg-white focus-within:border-brand focus-within:ring-3 focus-within:ring-brand-ring">
                <span className="grid min-w-11 place-items-center border-r border-slate-200 bg-slate-50 px-2 font-mono text-xs text-slate-600" aria-hidden="true">{prefijo}</span>
                <input
                    id="campo-telefono"
                    type="tel"
                    inputMode={tipo === 'internacional' ? 'tel' : 'numeric'}
                    placeholder={placeholder}
                    value={value}
                    onChange={onChange}
                    aria-invalid={error ? true : undefined}
                    className="!min-h-0 h-9 w-full !rounded-none !border-0 px-3 font-mono text-sm !shadow-none"
                />
            </div>
            {error && <p className="mt-1.5 text-xs font-medium text-rose-600" role="alert">{error}</p>}
        </div>
    );
}
