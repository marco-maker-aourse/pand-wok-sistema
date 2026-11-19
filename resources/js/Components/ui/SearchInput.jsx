import { Search, X } from 'lucide-react';
import { cx } from './cx';

export default function SearchInput({ value, onChange, placeholder = 'Buscar…', label, className, ...props }) {
    return (
        <div className={cx('relative', className)}>
            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
            <input
                type="search"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                aria-label={label ?? placeholder}
                className="block w-full pr-8 pl-9 text-sm [&::-webkit-search-cancel-button]:hidden"
                {...props}
            />
            {value && (
                <button
                    type="button"
                    onClick={() => onChange('')}
                    aria-label="Limpiar búsqueda"
                    className="absolute top-1/2 right-2 grid h-6 w-6 -translate-y-1/2 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                >
                    <X className="h-3.5 w-3.5" aria-hidden="true" />
                </button>
            )}
        </div>
    );
}
