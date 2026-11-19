import { Dialog as HDialog, DialogPanel, DialogTitle, DialogBackdrop } from '@headlessui/react';
import { AlertTriangle, CheckCircle2, Info, XCircle, X } from 'lucide-react';
import Button from './Button';
import { cx } from './cx';

const ANCHOS = { sm: 'max-w-sm', md: 'max-w-md', lg: 'max-w-lg', xl: 'max-w-xl', '2xl': 'max-w-2xl', '3xl': 'max-w-3xl', '4xl': 'max-w-4xl' };

/** Diálogo modal accesible: atrapa el foco, cierra con Escape y se anuncia con su título. */
export default function Dialog({ open, onClose, title, description, size = 'md', children, footer, dismissible = true }) {
    return (
        <HDialog open={open} onClose={dismissible ? onClose : () => {}} className="relative z-50">
            <DialogBackdrop transition className="fixed inset-0 bg-slate-900/45 transition-opacity duration-200 ease-out data-closed:opacity-0" />
            <div className="fixed inset-0 flex items-end justify-center overflow-y-auto p-3 sm:items-center sm:p-6">
                <DialogPanel
                    transition
                    className={cx(
                        'flex max-h-[calc(100dvh-1.5rem)] w-full flex-col overflow-hidden rounded-2xl bg-white shadow-[var(--shadow-flotante)] ring-1 ring-slate-900/5 transition duration-200 ease-[cubic-bezier(0.2,0,0,1)] data-closed:translate-y-3 data-closed:scale-[0.98] data-closed:opacity-0 sm:data-closed:translate-y-0',
                        ANCHOS[size],
                    )}
                >
                    {title && (
                        <div className="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-3.5">
                            <div className="min-w-0">
                                <DialogTitle className="text-base font-semibold text-slate-900">{title}</DialogTitle>
                                {description && <p className="mt-0.5 text-sm text-slate-500">{description}</p>}
                            </div>
                            {dismissible && (
                                <button type="button" onClick={onClose} aria-label="Cerrar" className="-mr-1.5 grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                    <X className="h-4 w-4" aria-hidden="true" />
                                </button>
                            )}
                        </div>
                    )}
                    <div className="min-h-0 flex-1 overflow-y-auto">{children}</div>
                    {footer && <div className="flex flex-wrap justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">{footer}</div>}
                </DialogPanel>
            </div>
        </HDialog>
    );
}

const TONOS = {
    success: { icon: CheckCircle2, className: 'bg-emerald-50 text-emerald-600' },
    danger: { icon: XCircle, className: 'bg-rose-50 text-rose-600' },
    warning: { icon: AlertTriangle, className: 'bg-amber-50 text-amber-600' },
    info: { icon: Info, className: 'bg-sky-50 text-sky-600' },
    destructivo: { icon: AlertTriangle, className: 'bg-rose-50 text-rose-600' },
};

function DialogMessage({ tone, title, children }) {
    const { icon: Icon, className } = TONOS[tone] ?? TONOS.info;
    return (
        <div className="flex gap-3.5 px-5 pt-5 pb-4">
            <span className={cx('grid h-10 w-10 shrink-0 place-items-center rounded-full ring-4 ring-white', className)}>
                <Icon className="h-5 w-5" aria-hidden="true" />
            </span>
            <div className="min-w-0 pt-1">
                <DialogTitle className="text-base font-semibold text-slate-900">{title}</DialogTitle>
                <div className="mt-1 text-sm text-slate-600">{children}</div>
            </div>
        </div>
    );
}

/**
 * Confirmación de acciones con consecuencias. `tone`: 'danger' (destructiva), 'warning' o 'info'.
 * Mientras `loading` es verdadero no se puede cerrar (ni con Escape ni con clic fuera).
 * `error` muestra el fallo de la operación real sin cerrar el diálogo.
 */
export function ConfirmDialog({ open, onClose, onConfirm, title, children, confirmLabel = 'Confirmar', cancelLabel = 'Cancelar', tone = 'danger', loading = false, error = null }) {
    return (
        <Dialog
            open={open}
            onClose={onClose}
            dismissible={!loading}
            size="sm"
            footer={
                <>
                    <Button variant="secondary" onClick={onClose} disabled={loading}>{cancelLabel}</Button>
                    <Button variant={tone === 'danger' ? 'danger' : 'primary'} onClick={onConfirm} loading={loading} autoFocus={tone !== 'danger'}>{confirmLabel}</Button>
                </>
            }
        >
            <DialogMessage tone={tone === 'danger' ? 'destructivo' : tone} title={title}>{children}</DialogMessage>
            {error && (
                <p role="alert" className="mx-5 mb-4 flex items-start gap-2 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">
                    <XCircle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                    {error}
                </p>
            )}
        </Dialog>
    );
}

/** Aviso de resultado. `notice` = { tone: 'success'|'danger'|'warning'|'info', title, message } o null. */
export function NoticeDialog({ notice, onClose }) {
    return (
        <Dialog open={Boolean(notice)} onClose={onClose} size="sm" footer={<Button onClick={onClose} autoFocus>Entendido</Button>}>
            {notice && <DialogMessage tone={notice.tone} title={notice.title}>{notice.message}</DialogMessage>}
        </Dialog>
    );
}
