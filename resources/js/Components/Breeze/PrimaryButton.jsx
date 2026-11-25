import { buttonClasses } from '@/Components/ui/Button';

/** Adaptador de Breeze sobre el botón del sistema de diseño (variante "primary"). */
export default function PrimaryButton({ className = '', type = 'submit', disabled, children, ...props }) {
    return (
        <button {...props} type={type} disabled={disabled} className={buttonClasses({ variant: 'primary', className })}>
            {children}
        </button>
    );
}
