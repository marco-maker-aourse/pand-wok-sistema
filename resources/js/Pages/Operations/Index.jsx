import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronLeft, ChevronRight, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, CardFooter, Input, PageHeader, Table, TableEmpty, TBody, THead, Td, Th, Tr, buttonClasses } from '@/Components/ui';

export default function OperationsIndex({ operation, filters }) {
    const [search, setSearch] = useState(filters.buscar ?? '');
    const rows = operation.rows.data ?? [];
    const canSearch = operation.title === 'Kardex';

    const submit = (event) => {
        event.preventDefault();
        router.get(window.location.pathname, search ? { buscar: search } : {}, { preserveState: true, replace: true });
    };

    const enlacePagina = (href, Icon, label) =>
        href ? (
            <Link href={href} className={buttonClasses({ variant: 'secondary', size: 'sm' })} aria-label={label}>
                <Icon className="h-3.5 w-3.5" aria-hidden="true" />
            </Link>
        ) : null;

    return (
        <AppLayout title={operation.title}>
            <Head title={operation.title} />
            <div className="space-y-4">
                <PageHeader breadcrumbs={[{ label: 'Consulta operativa' }, { label: operation.title }]} title={operation.title} meta={`${operation.rows.total ?? 0} registros`} />
                <Card>
                    {canSearch && (
                        <form onSubmit={submit} className="flex gap-2 border-b border-slate-200 p-3">
                            <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar por producto o código" aria-label="Buscar por producto o código" className="sm:max-w-xs" />
                            <Button type="submit" variant="secondary" icon={Search}>Buscar</Button>
                        </form>
                    )}
                    <Table caption={operation.title}>
                        <THead>
                            <Th>{operation.entity}</Th>
                            {operation.columns.map((column) => <Th key={column}>{column}</Th>)}
                        </THead>
                        <TBody>
                            {rows.length ? (
                                rows.map((row) => (
                                    <Tr key={row.id}>
                                        <Td>
                                            <span className="block font-medium text-slate-900">{row.primary}</span>
                                            {row.secondary && <span className="block text-xs text-slate-500">{row.secondary}</span>}
                                        </Td>
                                        {row.values.map((value, index) => <Td key={operation.columns[index]} className="tabular-nums">{value}</Td>)}
                                    </Tr>
                                ))
                            ) : (
                                <TableEmpty colSpan={operation.columns.length + 1} title="No hay registros disponibles" />
                            )}
                        </TBody>
                    </Table>
                    <CardFooter>
                        <span>{operation.rows.total ?? 0} resultados</span>
                        <div className="flex items-center gap-2">
                            {enlacePagina(operation.rows.prev_page_url, ChevronLeft, 'Página anterior')}
                            <span>Página {operation.rows.current_page ?? 1} de {operation.rows.last_page ?? 1}</span>
                            {enlacePagina(operation.rows.next_page_url, ChevronRight, 'Página siguiente')}
                        </div>
                    </CardFooter>
                </Card>
            </div>
        </AppLayout>
    );
}
