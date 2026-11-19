import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

export default forwardRef(function TextInput(
    { type = 'text', className = '', isFocused = false, icon = null, error = false, ...props },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    const input = <input
        {...props}
        type={type}
        aria-invalid={error || undefined}
        className={`min-h-11 rounded-xl border-slate-200 bg-white/80 text-sm font-semibold text-slate-800 transition-all placeholder:text-slate-400 focus:border-brand focus:ring-brand-ring disabled:cursor-not-allowed disabled:bg-slate-100 disabled:opacity-60 ${error ? 'border-rose-400 bg-rose-50 focus:border-rose-500 focus:ring-rose-500/10' : ''} ${icon ? 'pl-11' : ''} ${className}`}
        ref={localRef}
    />;

    return icon ? (
        <span className="group relative block">
            <span className="pointer-events-none absolute inset-y-0 left-0 z-10 flex w-11 items-center justify-center text-slate-400 transition-colors group-focus-within:text-brand-ink" aria-hidden="true">{icon}</span>
            {input}
        </span>
    ) : input;
});
