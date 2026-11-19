export default function InputLabel({
    value,
    className = '',
    children,
    required = false,
    ...props
}) {
    return (
        <label
            {...props}
            className={
                `mb-2 block text-xs font-bold uppercase tracking-wider text-slate-600 ` +
                className
            }
        >
            {value ? value : children} {required && <span className="text-brand-ink" aria-hidden="true">*</span>}
        </label>
    );
}
