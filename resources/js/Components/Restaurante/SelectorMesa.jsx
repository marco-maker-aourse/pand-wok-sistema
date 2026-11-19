import { useState } from 'react';
import { Dialog, EmptyState, cx } from '@/Components/ui';
import { LayoutGrid } from 'lucide-react';
import { ESTADO_MESA } from './estados';
import { soles } from './formato';

/**
 * Elegir mesa desde el POS. Una mesa ocupada abre su cuenta; una libre o reservada se
 * asigna al ticket nuevo (la cuenta se crea al enviar a cocina o al cobrar).
 */
export default function SelectorMesa({ abierto, onCerrar, areas = [], onElegir }) {
    const [areaActiva, setAreaActiva] = useState(null);
    const area = areas.find((a) => a.id_area === areaActiva) ?? areas[0];

    return (
        <Dialog open={abierto} onClose={onCerrar} title="Elegir mesa" size="3xl">
            {areas.length === 0 ? (
                <EmptyState icon={LayoutGrid} title="No hay mesas" description="Configúralas en Salón → Mesas y áreas." />
            ) : (
                <div className="space-y-4 p-5">
                    <div className="flex gap-2 overflow-x-auto" role="tablist">
                        {areas.map((a) => (
                            <button
                                key={a.id_area}
                                type="button"
                                role="tab"
                                aria-selected={a.id_area === area?.id_area}
                                onClick={() => setAreaActiva(a.id_area)}
                                className={cx('h-10 shrink-0 rounded-full px-4 text-sm font-medium ring-1', a.id_area === area?.id_area ? 'bg-brand text-on-brand ring-brand' : 'ring-slate-200 hover:bg-slate-50')}
                            >
                                {a.nombre}
                            </button>
                        ))}
                    </div>
                    <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                        {area?.mesas.map((mesa) => {
                            const estilo = ESTADO_MESA[mesa.estado] ?? ESTADO_MESA.DISPONIBLE;
                            const fuera = mesa.estado === 'FUERA_SERVICIO';
                            return (
                                <button
                                    key={mesa.id_mesa}
                                    type="button"
                                    disabled={fuera}
                                    onClick={() => onElegir(mesa)}
                                    className={cx('flex min-h-20 flex-col justify-between rounded-xl p-3 text-left ring-1 active:scale-[0.97] disabled:cursor-not-allowed', estilo.tarjeta)}
                                >
                                    <span className="text-lg font-semibold">{mesa.codigo}</span>
                                    <span className="text-xs text-slate-600">{mesa.cuenta ? soles(mesa.cuenta.total) : estilo.etiqueta}</span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            )}
        </Dialog>
    );
}
