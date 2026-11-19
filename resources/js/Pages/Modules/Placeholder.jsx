import { Head } from '@inertiajs/react';
import { Construction } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Card, EmptyState } from '@/Components/ui';

export default function Placeholder({ module, status }) {
    return (
        <AppLayout title={module}>
            <Head title={module} />
            <Card>
                <EmptyState
                    icon={Construction}
                    title={module}
                    description="Este módulo ya tiene su ruta y se conectará al contrato versionado del sistema."
                    action={status && <Badge>{status}</Badge>}
                />
            </Card>
        </AppLayout>
    );
}
