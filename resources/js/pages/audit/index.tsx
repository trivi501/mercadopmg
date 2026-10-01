import { Head } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import { FilterSelect, toItems } from '@/components/padron/filter-select';
import { useFilters } from '@/components/padron/filters';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { Pagination } from '@/components/padron/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/audit';
import type { NamedItem, Option, SimplePaginated } from '@/types/padron';

type Log = {
    id: number;
    user: string;
    action: string;
    action_label: string;
    module: string;
    record: string | null;
    description: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    device: string | null;
    date: string;
    time: string;
};

type Props = {
    logs: SimplePaginated<Log>;
    filters: Record<string, string>;
    options: { users: NamedItem[]; actions: Option[]; modules: string[] };
};

function Values({
    title,
    values,
}: {
    title: string;
    values: Record<string, unknown> | null;
}) {
    if (!values || Object.keys(values).length === 0) {
        return null;
    }

    return (
        <div className="min-w-0">
            <div className="mb-1 text-xs font-medium text-muted-foreground">
                {title}
            </div>
            <pre className="overflow-x-auto rounded-md bg-muted p-2 text-xs">
                {JSON.stringify(values, null, 2)}
            </pre>
        </div>
    );
}

export default function Audit({ logs, filters: initial, options }: Props) {
    const [open, setOpen] = useState<number | null>(null);
    const [filters, set, reset] = useFilters(
        {
            search: initial.search ?? '',
            date_from: initial.date_from ?? '',
            date_to: initial.date_to ?? '',
            user_id: initial.user_id ?? '',
            action: initial.action ?? '',
            module: initial.module ?? '',
        },
        index().url,
    );

    return (
        <>
            <Head title="Auditoría" />
            <PageBody>
                <PageHeader
                    title="Auditoría"
                    description="Registro de solo lectura de las operaciones del sistema."
                />

                <div className="flex flex-wrap gap-2">
                    <Input
                        value={filters.search}
                        onChange={(e) => set('search', e.target.value)}
                        placeholder="Buscar en descripción"
                        className="w-full sm:w-64"
                    />
                    <Input
                        type="date"
                        value={filters.date_from}
                        onChange={(e) => set('date_from', e.target.value)}
                        className="w-40"
                        aria-label="Fecha inicial"
                    />
                    <Input
                        type="date"
                        value={filters.date_to}
                        onChange={(e) => set('date_to', e.target.value)}
                        className="w-40"
                        aria-label="Fecha final"
                    />
                    <FilterSelect
                        value={filters.user_id}
                        onChange={(v) => set('user_id', v)}
                        items={toItems(options.users)}
                        placeholder="Usuario"
                        allLabel="Todos los usuarios"
                    />
                    <FilterSelect
                        value={filters.action}
                        onChange={(v) => set('action', v)}
                        items={options.actions}
                        placeholder="Acción"
                        allLabel="Todas las acciones"
                    />
                    <FilterSelect
                        value={filters.module}
                        onChange={(v) => set('module', v)}
                        items={options.modules.map((m) => ({
                            value: m,
                            label: m,
                        }))}
                        placeholder="Módulo"
                        allLabel="Todos los módulos"
                    />
                    <Button variant="ghost" onClick={reset}>
                        Limpiar
                    </Button>
                </div>

                <Card className="py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Acción</TableHead>
                                <TableHead>Módulo</TableHead>
                                <TableHead>Registro</TableHead>
                                <TableHead>Descripción</TableHead>
                                <TableHead>IP / Dispositivo</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {logs.data.map((log) => (
                                <Fragment key={log.id}>
                                    <TableRow
                                        className="cursor-pointer"
                                        onClick={() =>
                                            setOpen(
                                                open === log.id ? null : log.id,
                                            )
                                        }
                                    >
                                        <TableCell className="tabular-nums">
                                            {log.date}{' '}
                                            <span className="text-muted-foreground">
                                                {log.time}
                                            </span>
                                        </TableCell>
                                        <TableCell>{log.user}</TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {log.action_label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{log.module}</TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {log.record ?? '—'}
                                        </TableCell>
                                        <TableCell className="max-w-72 truncate">
                                            {log.description ?? '—'}
                                        </TableCell>
                                        <TableCell className="max-w-56 truncate text-xs text-muted-foreground">
                                            {[log.ip_address, log.device]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </TableCell>
                                    </TableRow>
                                    {open === log.id && (
                                        <TableRow className="hover:bg-transparent">
                                            <TableCell
                                                colSpan={7}
                                                className="whitespace-normal"
                                            >
                                                <div className="grid gap-3 md:grid-cols-2">
                                                    <Values
                                                        title="Valores anteriores"
                                                        values={log.old_values}
                                                    />
                                                    <Values
                                                        title="Valores nuevos"
                                                        values={log.new_values}
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </Fragment>
                            ))}
                        </TableBody>
                    </Table>
                </Card>

                <Pagination
                    links={logs.links}
                    from={logs.from}
                    to={logs.to}
                    total={logs.total}
                />
            </PageBody>
        </>
    );
}

Audit.layout = {
    breadcrumbs: [{ title: 'Auditoría', href: index() }],
};
