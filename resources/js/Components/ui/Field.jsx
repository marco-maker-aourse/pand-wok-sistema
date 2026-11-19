import { cloneElement, isValidElement, useId } from 'react';
import { Description as HDescription, Field as HSwitchField, Label as HLabel, Switch as HSwitch } from '@headlessui/react';
import { cx } from './cx';

export const inputClasses = 'block w-full px-3 text-sm';

/**
 * Etiqueta, ayuda y error para un control. El control hijo recibe `id`,
 * `aria-invalid` y `aria-describedby` automáticamente. Cuando el control está
 * envuelto (por ejemplo, un input con un botón al lado), pasa `htmlFor` con el
 * id del control y no se modifica el hijo.
 */
export default function Field({ label, hint, error, required = false, className, children, id, htmlFor }) {
    const generado = useId();
    const fieldId = htmlFor ?? id ?? children?.props?.id ?? generado;
    const describedBy = error ? `${fieldId}-error` : hint ? `${fieldId}-hint` : undefined;

    const control = !htmlFor && isValidElement(children)
        ? cloneElement(children, {
              id: fieldId,
              'aria-invalid': error ? true : undefined,
              'aria-describedby': describedBy,
              required: children.props.required ?? (required || undefined),
          })
        : children;

    return (
        <div className={className}>
            {label && (
                <label htmlFor={fieldId} className="mb-1.5 block text-xs font-medium text-slate-700">
                    {label}
                    {required && <span className="ml-0.5 text-rose-600" aria-hidden="true">*</span>}
                </label>
            )}
            {control}
            {error ? (
                <p id={`${fieldId}-error`} className="mt-1.5 text-xs font-medium text-rose-600" role="alert">{error}</p>
            ) : (
                hint && <p id={`${fieldId}-hint`} className="mt-1.5 text-xs text-slate-500">{hint}</p>
            )}
        </div>
    );
}

export function Input({ className, ...props }) {
    return <input className={cx(inputClasses, className)} {...props} />;
}

export function Select({ className, children, ...props }) {
    return <select className={cx(inputClasses, 'pr-8', className)} {...props}>{children}</select>;
}

export function Textarea({ className, ...props }) {
    return <textarea className={cx(inputClasses, 'min-h-20 resize-y py-2', className)} {...props} />;
}

/**
 * Opción booleana como interruptor (role="switch"). Mantiene la API anterior:
 * `checked` y `onChange(valor: boolean)`.
 */
export function CheckboxField({ label, description, checked, onChange, disabled = false, className }) {
    return (
        <HSwitchField className={cx('flex items-start justify-between gap-4', disabled && 'opacity-60', className)} disabled={disabled}>
            <span className="min-w-0 text-sm">
                <HLabel className="block font-medium text-slate-800">{label}</HLabel>
                {description && <HDescription className="block text-xs text-slate-500">{description}</HDescription>}
            </span>
            <HSwitch
                checked={Boolean(checked)}
                onChange={onChange}
                className="group relative mt-0.5 inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full bg-slate-300 transition-colors duration-200 ease-out data-checked:bg-brand data-disabled:cursor-not-allowed data-focus:outline-2 data-focus:outline-offset-2 data-focus:outline-brand"
            >
                <span
                    aria-hidden="true"
                    className="pointer-events-none mt-0.5 ml-0.5 inline-block h-4 w-4 rounded-full bg-white shadow-[0_1px_2px_rgb(15_23_42/0.25)] transition-transform duration-200 ease-out group-data-checked:translate-x-4"
                />
            </HSwitch>
        </HSwitchField>
    );
}
