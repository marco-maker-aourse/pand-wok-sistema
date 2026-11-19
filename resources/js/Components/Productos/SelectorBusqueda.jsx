import { useEffect, useMemo, useRef, useState } from 'react';
import { Check, ChevronDown, Plus, Search, X } from 'lucide-react';

/** Combobox con búsqueda y alta rápida opcional. Trasladado sin cambios de comportamiento desde Productos/Index. */
export default function SelectorBusqueda({ 
    label, 
    placeholder = "Selecciona o busca...", 
    options = [], 
    value, 
    onChange, 
    required = false, 
    error = null,
    allowNone = true,
    noneLabel = "Sin especificar",
    onQuickCreate = null,
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [busqueda, setBusqueda] = useState('');
    const wrapperRef = useRef(null);

    useEffect(() => {
        function handleClickOutside(event) {
            if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        }
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    const opcionSeleccionada = useMemo(() => {
        if (!value) return null;
        return options.find(opt => {
            const optId = opt.id_categoria ?? opt.id_marca ?? opt.id_unidad ?? opt.id;
            return String(optId) === String(value);
        });
    }, [options, value]);

    const opcionesFiltradas = useMemo(() => {
        if (!busqueda.trim()) return options;
        const q = busqueda.toLowerCase().trim();
        return options.filter(opt => (opt.nombre || '').toLowerCase().includes(q));
    }, [options, busqueda]);

    const handleSelect = (id) => {
        onChange(id);
        setIsOpen(false);
        setBusqueda('');
    };

    const handleClear = (e) => {
        e.stopPropagation();
        onChange('');
        setBusqueda('');
    };

    return (
        <div className="relative space-y-1" ref={wrapperRef}>
            {label && (
                <div className="flex items-center justify-between gap-2 mb-1">
                    <label className="block text-xs font-semibold text-slate-500">
                        {label} {required && <span className="text-rose-500">*</span>}
                    </label>
                    {onQuickCreate && (
                        <button 
                            type="button" 
                            onClick={(event) => { event.stopPropagation(); onQuickCreate(); }} 
                            className="inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 transition cursor-pointer" 
                        >
                            <span>+ Nuevo</span>
                        </button>
                    )}
                </div>
            )}

            <div 
                onClick={() => setIsOpen(!isOpen)}
                className={`w-full flex items-center justify-between text-xs font-bold bg-slate-50 hover:bg-white border rounded-xl py-2 px-3 cursor-pointer transition ${
                    isOpen ? 'border-slate-800 bg-white ring-2 ring-slate-900/10' : 'border-slate-200'
                } ${error ? 'border-rose-400 bg-rose-50/20' : ''}`}
            >
                <div className="flex items-center gap-2 truncate">
                    {opcionSeleccionada ? (
                        <span className="text-slate-900 font-semibold uppercase truncate">
                            {opcionSeleccionada.nombre}
                        </span>
                    ) : (
                        <span className="text-slate-400 font-semibold uppercase text-xs">
                            {placeholder}
                        </span>
                    )}
                </div>

                <div className="flex items-center gap-1.5 shrink-0 text-slate-400">
                    {opcionSeleccionada && (
                        <button 
                            type="button" 
                            onClick={handleClear} 
                            className="p-1 hover:text-slate-700 hover:bg-slate-200 rounded-md transition cursor-pointer"
                        >
                            <X className="w-3.5 h-3.5" />
                        </button>
                    )}
                    <ChevronDown className={`w-3.5 h-3.5 transition-transform duration-200 ${isOpen ? 'rotate-180 text-slate-800' : ''}`} />
                </div>
            </div>

            {isOpen && (
                <div className="absolute z-50 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-xl p-2 space-y-1 duration-150">
                    <div className="relative">
                        <Search className="w-3.5 h-3.5 absolute inset-y-0 left-2.5 my-auto text-slate-400 pointer-events-none" />
                        <input 
                            type="text" 
                            autoFocus
                            placeholder="Buscar en la lista..."
                            className="w-full text-xs font-bold text-slate-800 pl-8 pr-3 py-1.5 bg-white border border-slate-200 rounded-xl transition uppercase"
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                            onKeyDown={(e) => { if (e.key === 'Enter') e.preventDefault(); }}
                        />
                    </div>

                    <div className="max-h-44 overflow-y-auto space-y-0.5 pr-1 [scrollbar-width:thin]">
                        {allowNone && (
                            <button
                                type="button"
                                onClick={() => handleSelect('')}
                                className={`w-full text-left px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center justify-between cursor-pointer ${
                                    !value ? 'bg-slate-100 text-slate-900 font-semibold' : 'text-slate-500 hover:bg-slate-50'
                                }`}
                            >
                                <span className="uppercase">{noneLabel}</span>
                                {!value && <Check className="w-3.5 h-3.5 stroke-[3]" />}
                            </button>
                        )}

                        {opcionesFiltradas.length > 0 ? (
                            opcionesFiltradas.map((opt) => {
                                const optId = opt.id_categoria ?? opt.id_marca ?? opt.id_unidad ?? opt.id;
                                const isSelected = String(optId) === String(value);

                                return (
                                    <button
                                        key={optId}
                                        type="button"
                                        onClick={() => handleSelect(optId)}
                                        className={`w-full text-left px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center justify-between cursor-pointer ${
                                            isSelected 
                                                ? 'bg-emerald-800 text-white font-semibold' 
                                                : 'text-slate-700 hover:bg-slate-50'
                                        }`}
                                    >
                                        <span className="truncate">{opt.nombre}</span>
                                        {isSelected && <Check className="w-3.5 h-3.5 stroke-[3]" />}
                                    </button>
                                );
                            })
                        ) : (
                            <div className="py-3 text-center text-slate-400 text-xs font-bold uppercase">
                                No hay resultados
                            </div>
                        )}
                    </div>
                </div>
            )}

            {error && <p className="text-xs text-rose-500 font-bold">{error}</p>}
        </div>
    );
}
