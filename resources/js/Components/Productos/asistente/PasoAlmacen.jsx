import { Box, Droplet, Scale } from 'lucide-react';
import { CheckboxField } from '@/Components/ui';
import SelectorBusqueda from '../SelectorBusqueda';

const ACCESOS_RAPIDOS = [
    { tipo: 'unidad', icono: Box, titulo: 'Unidad', detalle: 'Tarros, botellas, bolsas' },
    { tipo: 'peso', icono: Scale, titulo: 'Kilogramo', detalle: 'Arroz, azúcar a granel' },
    { tipo: 'liquido', icono: Droplet, titulo: 'Litro', detalle: 'Líquidos sueltos' },
];

/** Paso 2: unidad base de almacén, vencimiento e IGV. */
export default function PasoAlmacen({ f }) {
    const { data, setData, errors, localCatalogs, asignarUnidadRapida, setQuickCatalog, setQuickName, setQuickCode } = f;

    return (
        <div className="space-y-4">
            <fieldset>
                <legend className="text-sm font-medium text-slate-900">¿Cómo cuentas este producto en almacén?</legend>
                <p className="mt-0.5 mb-3 text-xs text-slate-500">Elige la medida mínima suelta. Las cajas y fardos se agregan en el siguiente paso.</p>
                <div className="mb-3 grid grid-cols-3 gap-2">
                    {ACCESOS_RAPIDOS.map(({ tipo, icono: Icono, titulo, detalle }) => (
                        <button
                            key={tipo}
                            type="button"
                            onClick={() => asignarUnidadRapida(tipo)}
                            className="rounded-lg border border-slate-200 bg-white p-3 text-center transition-colors hover:border-brand-border hover:bg-brand-soft"
                        >
                            <Icono className="mx-auto mb-1 h-5 w-5 text-slate-600" aria-hidden="true" />
                            <span className="block text-sm font-medium text-slate-900">{titulo}</span>
                            <span className="block text-xs text-slate-500">{detalle}</span>
                        </button>
                    ))}
                </div>
                <SelectorBusqueda
                    label="Unidad de medida"
                    placeholder="Buscar unidad..."
                    options={localCatalogs.unidades}
                    value={data.id_unidad_base}
                    onChange={(val) => setData('id_unidad_base', val)}
                    required={!data.es_servicio}
                    error={errors.id_unidad_base}
                    allowNone={false}
                    onQuickCreate={() => { setQuickCatalog('unidad'); setQuickName(''); setQuickCode(''); }}
                />
            </fieldset>

            <div className="space-y-3 rounded-2xl bg-white p-3 shadow-[var(--shadow-tarjeta)] ring-1 ring-slate-900/[0.05]">
                <CheckboxField
                    label="Controlar vencimiento y lotes"
                    description="Recomendado para alimentos y bebidas."
                    checked={data.usa_vencimiento}
                    disabled={data.es_servicio}
                    onChange={(valor) => setData('usa_vencimiento', valor)}
                />
                <CheckboxField
                    label="Afecto a IGV (18 %)"
                    description="Se incluye en comprobantes tributarios SUNAT."
                    checked={data.afecta_igv}
                    onChange={(valor) => setData('afecta_igv', valor)}
                />
            </div>
        </div>
    );
}
