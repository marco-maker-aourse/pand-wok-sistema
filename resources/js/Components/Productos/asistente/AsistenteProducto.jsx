import { ChevronLeft, ChevronRight, Save } from 'lucide-react';
import { Button, Dialog, cx } from '@/Components/ui';
import DialogoAltaRapida from '../DialogoAltaRapida';
import PasoAlmacen from './PasoAlmacen';
import PasoIdentificacion from './PasoIdentificacion';
import PasoPrecios from './PasoPrecios';

const PASOS = ['Identificación', 'Almacén', 'Precios y márgenes'];

/** Diálogo del asistente de producto en 3 pasos. `f` es el resultado de useFormularioProducto. */
export default function AsistenteProducto({ f }) {
    const { isModalOpen, closeModal, quickCatalog, editingProduct, pasoActual, setPasoActual, avanzarPaso, submit, guardandoProducto, processing } = f;

    return (
        <Dialog
            open={isModalOpen}
            onClose={closeModal}
            dismissible={!quickCatalog}
            size="2xl"
            title={editingProduct ? 'Editar producto' : 'Nuevo producto'}
            description={`Paso ${pasoActual} de 3 · ${PASOS[pasoActual - 1]}`}
            footer={
                <div className="flex w-full items-center justify-between">
                    {pasoActual > 1 ? (
                        <Button variant="secondary" icon={ChevronLeft} onClick={() => setPasoActual((paso) => paso - 1)}>Atrás</Button>
                    ) : (
                        <Button variant="ghost" onClick={closeModal}>Cancelar</Button>
                    )}
                    {pasoActual < 3 ? (
                        <Button onClick={avanzarPaso}>
                            Continuar
                            <ChevronRight className="h-4 w-4" aria-hidden="true" />
                        </Button>
                    ) : (
                        <Button type="submit" form="formulario-producto" icon={Save} loading={guardandoProducto || processing}>
                            {editingProduct ? 'Guardar cambios' : 'Registrar producto'}
                        </Button>
                    )}
                </div>
            }
        >
            <ol className="grid grid-cols-3 gap-2 px-5 pt-4" aria-label="Progreso del asistente">
                {PASOS.map((paso, indice) => (
                    <li key={paso} aria-current={pasoActual === indice + 1 ? 'step' : undefined}>
                        <span className={cx('block h-1 rounded-full', pasoActual >= indice + 1 ? 'bg-brand' : 'bg-slate-200')} />
                        <span className={cx('mt-1.5 block text-xs', pasoActual === indice + 1 ? 'font-medium text-slate-900' : 'text-slate-500')}>{paso}</span>
                    </li>
                ))}
            </ol>
            {/* Enter no envía: el registro solo ocurre con el botón del paso 3. */}
            <form
                id="formulario-producto"
                onSubmit={submit}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') e.preventDefault();
                }}
                className="min-h-[24rem] p-5"
                noValidate
            >
                {pasoActual === 1 && <PasoIdentificacion f={f} />}
                {pasoActual === 2 && <PasoAlmacen f={f} />}
                {pasoActual === 3 && <PasoPrecios f={f} />}
            </form>
            {/* Anidado en el diálogo (fuera del form) para que Headless UI gestione el foco de ambos. */}
            <DialogoAltaRapida f={f} />
        </Dialog>
    );
}
