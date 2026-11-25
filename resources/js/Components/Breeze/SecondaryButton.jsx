import { buttonClasses } from '@/Components/ui/Button';

/** Adaptador de Breeze sobre el botón del sistema de diseño (variante "secondary"). */
export default function SecondaryButton({ className = '', type = 'button', disabled, children, ...props }) {
    return (
        <button {...props} type={type} disabled={disabled} className={buttonClasses({ variant: 'secondary', className })}>
            {children}
        </button>
    );
}
