import Dialog from '@/Components/ui/Dialog';

/**
 * Adaptador de la API heredada de Breeze (`show`, `maxWidth`, `closeable`) sobre el
 * Dialog del sistema de diseño. El contenido define su propio encabezado y padding.
 */
export default function Modal({ children, show = false, maxWidth = '2xl', closeable = true, onClose = () => {} }) {
    return (
        <Dialog open={show} onClose={onClose} size={maxWidth} dismissible={closeable}>
            {children}
        </Dialog>
    );
}
