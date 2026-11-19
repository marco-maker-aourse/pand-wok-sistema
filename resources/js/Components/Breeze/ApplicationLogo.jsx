import { usePage } from '@inertiajs/react';

export default function ApplicationLogo({ className = "w-8 h-8", ...props }) {
    const { appConfig } = usePage().props || {};

    // Si la empresa subió su propio logo dinámico
    if (appConfig?.logo) {
        return (
            <img
                src={appConfig.logo}
                alt={appConfig?.comercio || "Logo"}
                className={`object-contain ${className}`}
                {...props}
            />
        );
    }

    // Logo SVG vectorial por defecto
    return (
        <svg
            {...props}
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.75"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z" />
            <path d="m3.3 7 8.7 5 8.7-5" />
            <path d="M12 22V12" />
            <path d="m12 7 4.5-2.5" />
        </svg>
    );
}