import AuthenticatedLayout from './AuthenticatedLayout';

/** Alias de AuthenticatedLayout para las pantallas que reciben el título como `title`. */
export default function AppLayout({ title, children, ...opciones }) {
    return <AuthenticatedLayout header={title} {...opciones}>{children}</AuthenticatedLayout>;
}
