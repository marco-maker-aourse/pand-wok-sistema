export default function InputLabel({ value, className = '', children, required = false, ...props }) {
    return (
        <label {...props} className={`mb-1.5 block text-sm font-medium text-slate-700 ${className}`}>
            {value || children} {required && <span className="text-rose-600" aria-hidden="true">*</span>}
        </label>
    );
}
