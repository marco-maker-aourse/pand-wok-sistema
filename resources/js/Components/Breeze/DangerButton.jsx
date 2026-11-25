import { buttonClasses } from '@/Components/ui/Button';

/** Adaptador de Breeze sobre el botón del sistema de diseño (variante "danger"). */
export default function DangerButton({ className = '', type = 'submit', disabled, children, ...props }) {
    return (
        <button {...props} type={type} disabled={disabled} className={buttonClasses({ variant: 'danger', className })}>
            {children}
        </button>
    );
}
