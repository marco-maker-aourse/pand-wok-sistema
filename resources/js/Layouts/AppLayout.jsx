import AuthenticatedLayout from './AuthenticatedLayout';

export default function AppLayout({ title, children }) {
    // Adaptador temporal: todos los módulos antiguos usan la misma navegación
    // lateral de Álvaro mientras se modernizan sus contenidos internamente.
    return <AuthenticatedLayout header={title}>{children}</AuthenticatedLayout>;
}
