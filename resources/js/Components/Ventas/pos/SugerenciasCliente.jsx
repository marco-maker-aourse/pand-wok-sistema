import { UserRound } from 'lucide-react';

/**
 * Coincidencias de clientes registrados (por nombre o documento) bajo el campo
 * de búsqueda del POS. Solo muestra datos reales devueltos por el servidor.
 */
export default function SugerenciasCliente({ clientes = [], buscando = false, onElegir }) {
    if (clientes.length === 0) {
        return (
            <p className="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500" role="status">
                {buscando ? 'Buscando clientes registrados…' : 'Sin coincidencias. Para consultar SUNAT o RENIEC ingresa el número de documento.'}
            </p>
        );
    }

    return (
        <ul className="mt-2 max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white [scrollbar-width:thin]" role="listbox" aria-label="Clientes encontrados">
            {clientes.map((cliente) => (
                <li key={cliente.id_cliente}>
                    <button
                        type="button"
                        role="option"
                        aria-selected="false"
                        onClick={() => onElegir(cliente)}
                        className="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-slate-50 focus-visible:bg-slate-50 focus-visible:outline-none"
                    >
                        <UserRound className="h-3.5 w-3.5 shrink-0 text-slate-400" aria-hidden="true" />
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-medium text-slate-900">{cliente.nombre_razon_social}</span>
                            <span className="block font-mono text-xs text-slate-500">{cliente.num_documento}</span>
                        </span>
                    </button>
                </li>
            ))}
        </ul>
    );
}
