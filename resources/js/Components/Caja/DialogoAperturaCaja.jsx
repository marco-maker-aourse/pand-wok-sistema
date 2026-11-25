import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Coins, AlertCircle, Loader2 } from 'lucide-react';
import { Dialog } from '@/Components/ui';

export default function DialogoAperturaCaja({ isOpen, onClose, redirectTo = '/ventas/create', terminales = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        monto_apertura: '',
        pin_cajero: '',
        id_terminal: '',
        redirect_to: redirectTo,
    });
    const requiereTerminal = terminales.length > 0;

    useEffect(() => {
        if (isOpen && terminales.length === 1 && !data.id_terminal) {
            setData('id_terminal', String(terminales[0].id_terminal));
        }
    }, [isOpen, terminales, data.id_terminal, setData]);

    if (!isOpen) return null;

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/caja/aperturar', {
            onSuccess: () => onClose(),
        });
    };

    return (
        <Dialog open={isOpen} onClose={processing ? () => {} : onClose} size="sm">
            <div className="p-6">
                <div className="w-12 h-12 rounded-lg bg-brand-soft text-brand-ink flex items-center justify-center mb-4">
                    <Coins className="w-6 h-6" />
                </div>

                <h3 className="text-base font-semibold text-slate-900 tracking-tight">
                    Apertura de Turno de Caja
                </h3>
                <p className="text-xs text-slate-500 font-medium mt-1">
                    Ingrese el saldo inicial en efectivo.
                </p>

                <form onSubmit={handleSubmit} className="mt-5 space-y-4">
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1.5">
                            Terminal POS
                        </label>
                        <select
                            value={data.id_terminal}
                            onChange={(e) => setData('id_terminal', e.target.value)}
                            required={requiereTerminal}
                            className="w-full h-11 px-3.5 font-semibold text-sm text-slate-900 bg-white border border-slate-200 rounded-xl transition"
                        >
                            <option value="" disabled={requiereTerminal}>
                                {requiereTerminal ? 'Selecciona una terminal' : 'Sin terminal configurada'}
                            </option>
                            {terminales.map((terminal) => (
                                <option key={terminal.id_terminal} value={terminal.id_terminal}>
                                    {terminal.nombre} · {terminal.codigo}{terminal.impresora ? ` · ${terminal.impresora.nombre}` : ''}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-xs text-slate-500">La misma terminal se usa en cada turno de esta caja. Permite imprimir automáticamente los comprobantes aceptados.</p>
                        {errors.id_terminal && (
                            <p className="text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                                <AlertCircle className="w-3.5 h-3.5" /> {errors.id_terminal}
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1.5">
                            Monto Inicial / Fondo Fijo (S/) <span className="text-rose-500">*</span>
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            placeholder="Ej. 100.00"
                            value={data.monto_apertura}
                            onChange={(e) => setData('monto_apertura', e.target.value)}
                            className="w-full h-11 px-3.5 font-mono font-bold text-sm text-slate-900 bg-white border border-slate-200 rounded-xl transition"
                            autoFocus
                        />
                        {errors.monto_apertura && (
                            <p className="text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                                <AlertCircle className="w-3.5 h-3.5" /> {errors.monto_apertura}
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1.5">
                            PIN de caja
                        </label>
                        <input
                            type="password"
                            inputMode="numeric"
                            autoComplete="off"
                            maxLength="6"
                            placeholder="4 a 6 dígitos"
                            value={data.pin_cajero}
                            onChange={(e) => setData('pin_cajero', e.target.value.replace(/\D/g, ''))}
                            className="w-full h-11 px-3.5 font-mono font-bold text-sm text-slate-900 bg-white border border-slate-200 rounded-xl transition"
                        />
                        <p className="mt-1 text-xs text-slate-500">Confirma que eres el cajero antes de abrir el turno.</p>
                        {errors.pin_cajero && (
                            <p className="text-rose-600 text-xs font-bold mt-1 flex items-center gap-1">
                                <AlertCircle className="w-3.5 h-3.5" /> {errors.pin_cajero}
                            </p>
                        )}
                    </div>

                    <div className="flex gap-2 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="flex-1 h-10 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={processing || !data.monto_apertura || (requiereTerminal && !data.id_terminal)}
                            className="volumen-marca text-on-brand flex-1 h-10 rounded-xl font-semibold text-xs transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2 hover:brightness-105"
                        >
                            {processing ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Abrir Turno'}
                        </button>
                    </div>
                </form>
            </div>
        </Dialog>
    );
}
