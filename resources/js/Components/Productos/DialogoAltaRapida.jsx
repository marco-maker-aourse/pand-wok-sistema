import { Button, Dialog, Field, Input } from '@/Components/ui';

const TITULOS = { categoria: 'Nueva categoría', marca: 'Nueva marca', unidad: 'Nueva unidad de medida' };

/** Alta inmediata de categoría, marca o unidad desde el asistente de producto. */
export default function DialogoAltaRapida({ f }) {
    const { quickCatalog, setQuickCatalog, quickName, setQuickName, quickCode, setQuickCode, quickSaving, saveQuickCatalog } = f;
    const cerrar = () => setQuickCatalog(null);

    return (
        <Dialog
            open={Boolean(quickCatalog)}
            onClose={cerrar}
            size="sm"
            title={TITULOS[quickCatalog] ?? 'Nuevo registro'}
            description="Se crea y queda seleccionado en el producto."
            footer={
                <>
                    <Button variant="secondary" onClick={cerrar} disabled={quickSaving}>Cancelar</Button>
                    <Button type="submit" form="alta-rapida" loading={quickSaving}>Crear y seleccionar</Button>
                </>
            }
        >
            <form id="alta-rapida" onSubmit={saveQuickCatalog} className="space-y-3 p-5">
                <Field label="Nombre" required>
                    <Input value={quickName} onChange={(e) => setQuickName(e.target.value.toUpperCase())} autoFocus />
                </Field>
                {quickCatalog === 'unidad' && (
                    <Field label="Código SUNAT" hint="Opcional. Ej. NIU, KGM, LTR.">
                        <Input className="font-mono uppercase" maxLength={10} value={quickCode} onChange={(e) => setQuickCode(e.target.value.toUpperCase())} />
                    </Field>
                )}
            </form>
        </Dialog>
    );
}
