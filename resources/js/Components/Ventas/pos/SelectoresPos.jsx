import { useEffect, useRef, useState } from 'react';
import { Check, ChevronDown } from 'lucide-react';
import { formatoMoneda } from './formato';

/*
 * Selectores desplegables del punto de venta (tipo de documento, lote y
 * presentación). Se trasladaron sin cambios de comportamiento desde Ventas/Create.
 */
export function SelectorTipoDocumento({ tipos, seleccionadoId, onSeleccionar }) {
    const [open, setOpen] = useState(false);
    const dropRef = useRef(null);

    useEffect(() => {
        const handleClick = (e) => {
            if (dropRef.current && !dropRef.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    const actual = tipos.find(t => String(t.id_tipo_documento) === String(seleccionadoId)) || tipos[0];

    return (
        <div className="relative w-32 sm:w-36 shrink-0" ref={dropRef}>
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="w-full h-9 px-3 bg-white hover:bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between text-xs font-semibold text-slate-800 transition cursor-pointer"
            >
                <span className="truncate">{actual?.nombre || 'Doc'}</span>
                <ChevronDown className={`w-3.5 h-3.5 text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <div className="absolute top-10.5 left-0 z-50 w-48 bg-white border border-slate-200 rounded-lg shadow-xl p-1.5 space-y-0.5">
                    <div className="px-2 py-1 text-xs font-semibold uppercase text-slate-400">
                        Tipo de Documento
                    </div>
                    {tipos.map((t) => {
                        const isSelected = String(t.id_tipo_documento) === String(seleccionadoId);
                        return (
                            <button
                                key={t.id_tipo_documento}
                                type="button"
                                onClick={() => { onSeleccionar(t); setOpen(false); }}
                                className={`w-full text-left px-2.5 py-1.5 rounded-xl text-xs font-semibold flex items-center justify-between transition cursor-pointer ${
                                    isSelected ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100'
                                }`}
                            >
                                <span className="truncate">{t.nombre}</span>
                                {isSelected && <Check className="w-3.5 h-3.5 stroke-[3]" />}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

export function SelectorLote({ lotes = [], loteActual, onCambiarLote }) {
    const [open, setOpen] = useState(false);
    const dropRef = useRef(null);

    useEffect(() => {
        const handleClick = (e) => {
            if (dropRef.current && !dropRef.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    const hayLotes = lotes && lotes.length > 0;

    return (
        <div className="relative inline-flex items-center" ref={dropRef}>
            <button
                type="button"
                onClick={() => hayLotes && setOpen(!open)}
                disabled={!hayLotes}
                className="inline-flex items-center gap-1.5 px-2 py-1 bg-white hover:bg-slate-50 border border-slate-200/90 rounded-xl text-xs font-bold text-slate-800 transition cursor-pointer disabled:cursor-default disabled:opacity-70"
            >
                <span className="px-1.5 py-0.2 rounded bg-brand-soft text-brand-ink font-semibold text-xs uppercase">
                    FIFO
                </span>
                <span className="font-mono text-slate-800 font-bold truncate max-w-[130px]">
                    {loteActual ? `Lote: ${loteActual}` : 'Lote General'}
                </span>
                {hayLotes && <ChevronDown className={`w-3 h-3 text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`} />}
            </button>

            {open && hayLotes && (
                <div className="absolute left-0 bottom-full mb-1.5 z-50 w-72 bg-white border border-slate-200 rounded-lg shadow-xl p-2 space-y-1">
                    <div className="px-2 py-1 text-xs font-semibold uppercase text-slate-400 border-b border-slate-100 flex justify-between items-center">
                        <span>Lotes en Almacén (Prioridad FIFO)</span>
                        <span className="text-xs font-mono">Stock</span>
                    </div>

                    <div className="max-h-40 overflow-y-auto space-y-1 [scrollbar-width:thin] pr-1">
                        {lotes.map((l, idx) => {
                            const isSelected = l.codigo_lote === loteActual;
                            return (
                                <button
                                    key={l.id_lote || l.codigo_lote}
                                    type="button"
                                    onClick={() => { onCambiarLote(l.codigo_lote); setOpen(false); }}
                                    className={`w-full text-left p-2 rounded-xl text-xs transition flex items-center justify-between cursor-pointer ${
                                        isSelected ? 'bg-slate-900 text-white font-bold' : 'hover:bg-slate-50 border border-slate-100'
                                    }`}
                                >
                                    <div>
                                        <div className="flex items-center gap-1.5">
                                            {idx === 0 && (
                                                <span className={`text-xs font-semibold px-1.5 py-0.2 rounded uppercase ${isSelected ? 'bg-brand text-white' : 'bg-brand-soft text-brand-ink'}`}>
                                                    Recomendado
                                                </span>
                                            )}
                                            <span className="font-mono font-bold">{l.codigo_lote}</span>
                                        </div>
                                        {l.fecha_vencimiento && (
                                            <p className={`text-xs mt-0.5 ${isSelected ? 'text-slate-300' : 'text-slate-500'}`}>
                                                Caduca: {l.fecha_vencimiento}
                                            </p>
                                        )}
                                    </div>
                                    <span className={`font-mono font-semibold text-xs ${isSelected ? 'text-white' : 'text-slate-900'}`}>
                                        {l.stock}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}

export function SelectorPresentacion({ presentaciones = [], seleccionadoId, onCambiar, unidadNombre }) {
    const [open, setOpen] = useState(false);
    const dropRef = useRef(null);

    useEffect(() => {
        const handleClick = (e) => {
            if (dropRef.current && !dropRef.current.contains(e.target)) setOpen(false);
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    const actual = presentaciones.find(p => String(p.id_prod_pres) === String(seleccionadoId)) || presentaciones[0];

    return (
        <div className="relative w-full" ref={dropRef}>
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="w-full h-8 px-2.5 bg-slate-50 hover:bg-white border border-slate-200 rounded-xl flex items-center justify-between text-xs font-semibold text-slate-800 transition cursor-pointer"
            >
                <div className="flex items-center gap-1.5 truncate">
                    <span className="truncate">{actual?.nombre || 'Unidad'}</span>
                    <span className="text-xs font-mono text-slate-400">
                        (x{actual?.factor_conversion || 1})
                    </span>
                </div>
                <div className="flex items-center gap-1.5 shrink-0 ml-1">
                    <span className="font-mono text-emerald-800 font-semibold">
                        {formatoMoneda.format(Number(actual?.precio_venta || 0))}
                    </span>
                    <ChevronDown className={`w-3 h-3 text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`} />
                </div>
            </button>

            {open && (
                <div className="absolute top-9 left-0 z-50 w-64 bg-white border border-slate-200 rounded-lg shadow-xl p-1.5 space-y-0.5">
                    <div className="px-2 py-1 text-xs font-semibold uppercase text-slate-400">
                        Modalidad de venta
                    </div>
                    {presentaciones.map((p) => {
                        const isSelected = String(p.id_prod_pres) === String(seleccionadoId);
                        const factor = Number(p.factor_conversion || 1);
                        return (
                            <button
                                key={p.id_prod_pres}
                                type="button"
                                onClick={() => { onCambiar(p.id_prod_pres); setOpen(false); }}
                                className={`w-full text-left p-2 rounded-xl text-xs font-semibold flex items-center justify-between transition cursor-pointer ${
                                    isSelected ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-50'
                                }`}
                            >
                                <div>
                                    <p className="leading-tight">{p.nombre}</p>
                                    <span className={`text-xs font-normal ${isSelected ? 'text-slate-300' : 'text-slate-400'}`}>
                                        Contiene {factor} {unidadNombre}{factor === 1 ? '' : 's'}
                                    </span>
                                </div>
                                <span className={`font-mono text-xs ${isSelected ? 'text-white' : 'text-emerald-800'}`}>
                                    {formatoMoneda.format(Number(p.precio_venta || 0))}
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
