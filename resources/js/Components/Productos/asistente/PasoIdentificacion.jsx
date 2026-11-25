import { Field, Input } from '@/Components/ui';
import SelectorBusqueda from '../SelectorBusqueda';

/** Paso 1: nombre, categoría y código de barras. */
export default function PasoIdentificacion({ f }) {
    const { data, setData, errors, localCatalogs, setQuickCatalog, setQuickName } = f;

    return (
        <div className="space-y-4">
            <Field label="Nombre del producto" error={errors.nombre} required>
                <Input value={data.nombre} onChange={(e) => setData('nombre', e.target.value.toUpperCase())} placeholder="EJ. ACEITE PRIMOR CLÁSICO 1L" autoFocus />
            </Field>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <SelectorBusqueda
                    label="Categoría"
                    placeholder="Buscar categoría..."
                    options={localCatalogs.categorias}
                    value={data.id_categoria}
                    onChange={(val) => setData('id_categoria', val)}
                    required
                    error={errors.id_categoria}
                    allowNone={false}
                    onQuickCreate={() => { setQuickCatalog('categoria'); setQuickName(''); }}
                />
            </div>

            <Field label="Código de barras" hint="Escanéalo con el lector o escríbelo. Es opcional.">
                <Input className="font-mono" value={data.codigo_barras} onChange={(e) => setData('codigo_barras', e.target.value)} />
            </Field>
        </div>
    );
}
