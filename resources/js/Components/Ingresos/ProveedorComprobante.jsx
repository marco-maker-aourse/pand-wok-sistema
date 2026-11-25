import { useEffect, useRef } from 'react';
import { Building2, CheckCircle2, Loader2, Search, X } from 'lucide-react';
import { Field, Input, Select } from '@/Components/ui';
import { docProveedor, nombreProveedor } from './useIngresoCompra';

/** Proveedor (búsqueda local o consulta de RUC) y comprobante que respalda la compra. */
export default function ProveedorComprobante({ proveedor, data, setData, tiposComprobante, onTipo }) {
    const contenedor = useRef(null);

    const { setListaAbierta } = proveedor;
    useEffect(() => {
        const cerrar = (e) => { if (contenedor.current && !contenedor.current.contains(e.target)) setListaAbierta(false); };
        document.addEventListener('mousedown', cerrar);
        return () => document.removeEventListener('mousedown', cerrar);
    }, [setListaAbierta]);

    return (
        <div className="space-y-3">
            <div ref={contenedor} className="relative">
                <label htmlFor="input_proveedor" className="mb-1.5 flex items-center justify-between text-xs font-medium text-slate-700">
                    <span className="flex items-center gap-1.5"><Building2 className="h-3.5 w-3.5" aria-hidden="true" />Proveedor</span>
                    {proveedor.activo && <span className="inline-flex items-center gap-1 text-emerald-700"><CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />Identificado</span>}
                </label>
                <div className="flex gap-2">
                    <div className="relative min-w-0 flex-1">
                        <Input
                            id="input_proveedor"
                            value={proveedor.busqueda}
                            onChange={(e) => proveedor.cambiarBusqueda(e.target.value)}
                            onFocus={() => proveedor.setListaAbierta(true)}
                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); proveedor.buscar(); } }}
                            placeholder="RUC (11 dígitos) o razón social"
                            autoComplete="off"
                            className="pr-8"
                            aria-expanded={proveedor.listaAbierta && !proveedor.activo}
                            aria-controls="lista-proveedores"
                        />
                        {proveedor.busqueda && (
                            <button type="button" onClick={proveedor.limpiar} aria-label="Limpiar proveedor" className="absolute top-1/2 right-2 grid h-6 w-6 -translate-y-1/2 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                <X className="h-3.5 w-3.5" aria-hidden="true" />
                            </button>
                        )}
                    </div>
                    <button type="button" onClick={proveedor.buscar} disabled={proveedor.buscando} aria-label="Buscar proveedor o consultar RUC" title="Buscar o consultar RUC"
                        className="volumen-marca grid h-[2.375rem] w-11 shrink-0 place-items-center rounded-lg text-on-brand disabled:opacity-60">
                        {proveedor.buscando ? <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" /> : <Search className="h-4 w-4" aria-hidden="true" />}
                    </button>
                </div>

                {proveedor.activo && (
                    <p className="mt-2 truncate text-xs text-slate-500">RUC <span className="font-mono">{docProveedor(proveedor.activo) || '—'}</span></p>
                )}

                {proveedor.listaAbierta && !proveedor.activo && (
                    <ul id="lista-proveedores" className="absolute top-full left-0 z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-xl bg-white p-1 shadow-[var(--shadow-flotante)] ring-1 ring-slate-900/5">
                        {proveedor.filtrados.length > 0 ? proveedor.filtrados.map((p) => (
                            <li key={p.id_proveedor}>
                                <button type="button" onClick={() => proveedor.seleccionar(p)} className="block w-full rounded-lg px-3 py-2 text-left hover:bg-slate-50">
                                    <span className="block truncate text-sm font-medium text-slate-800">{nombreProveedor(p)}</span>
                                    <span className="font-mono text-xs text-slate-500">{docProveedor(p) || 'Sin RUC'}</span>
                                </button>
                            </li>
                        )) : (
                            <li className="px-3 py-2.5 text-center text-xs text-slate-500">
                                {/^\d{11}$/.test(proveedor.busqueda.trim()) ? 'No está registrado. Toca la lupa para consultarlo en SUNAT.' : 'No hay proveedores registrados con ese nombre.'}
                            </li>
                        )}
                    </ul>
                )}
            </div>

            <div className="grid grid-cols-[minmax(0,1.4fr)_5rem_minmax(0,1fr)] gap-2">
                <Field label="Comprobante">
                    <Select value={data.id_tipo_comprobante} onChange={(e) => onTipo(Number(e.target.value))}>
                        {tiposComprobante.map((t) => <option key={t.id_tipo_comprobante} value={t.id_tipo_comprobante}>{t.descripcion}</option>)}
                    </Select>
                </Field>
                <Field label="Serie">
                    <Input maxLength={4} placeholder="F001" className="text-center font-mono uppercase" value={data.serie_comprobante} onChange={(e) => setData('serie_comprobante', e.target.value.toUpperCase())} required />
                </Field>
                <Field label="Número">
                    <Input inputMode="numeric" placeholder="0001234" className="text-center font-mono" value={data.num_comprobante} onChange={(e) => setData('num_comprobante', e.target.value.replace(/\D/g, ''))} required />
                </Field>
            </div>
        </div>
    );
}
