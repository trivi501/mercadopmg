import { Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FilterSelect, toItems } from '@/components/padron/filter-select';
import { useFilters } from '@/components/padron/filters';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { Pagination } from '@/components/padron/pagination';
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
import { money } from '@/lib/format';
import { reprints as reprintsRoute, show } from '@/routes/receipts';
import type { NamedItem, SimplePaginated } from '@/types/padron';

type Row = {
    id: number;
    receipt_id: number;
    receipt_folio: string;
    merchant: string;
    total: string;
    number: number;
    date: string;
    time: string;
    user: string;
    reason: string;
};

type Props = {
    reprints: SimplePaginated<Row>;
    filters: Record<string, string>;
    options: { users: NamedItem[] };
};

export default function Reprints({
    reprints,
    filters: initial,
    options,
}: Props) {
    const [filters, set, reset] = useFilters(
        {
            search: initial.search ?? '',
            date_from: initial.date_from ?? '',
            date_to: initial.date_to ?? '',
            user_id: initial.user_id ?? '',
        },
        reprintsRoute().url,
    );

    return (
        <>
            <Head title="Control de reimpresiones" />
            <PageBody>
                <PageHeader
                    title="Control de reimpresiones"
                    description="Bitácora inmutable: cada reimpresión queda registrada con usuario, fecha y motivo."
                />

                <div className="flex flex-wrap gap-2">
                    <div className="relative w-full sm:w-56">
                        <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={filters.search}
                            onChange={(e) => set('search', e.target.value)}
                            placeholder="Folio del recibo"
                            className="pl-8"
                        />
                    </div>
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
                    <Button variant="ghost" onClick={reset}>
                        Limpiar
                    </Button>
                </div>

                <Card className="py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Recibo</TableHead>
                                <TableHead>Contribuyente</TableHead>
                                <TableHead className="text-right">
                                    Importe
                                </TableHead>
                                <TableHead>No.</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Motivo</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {reprints.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        Sin reimpresiones.
                                    </TableCell>
                                </TableRow>
                            )}
                            {reprints.data.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell className="font-mono">
                                        <Link
                                            href={show(r.receipt_id)}
                                            className="text-primary hover:underline"
                                        >
                                            {r.receipt_folio}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="max-w-64 truncate">
                                        {r.merchant}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {money(r.total)}
                                    </TableCell>
                                    <TableCell>{r.number}</TableCell>
                                    <TableCell>
                                        {r.date} {r.time}
                                    </TableCell>
                                    <TableCell>{r.user}</TableCell>
                                    <TableCell className="max-w-72 truncate">
                                        {r.reason}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>

                <Pagination
                    links={reprints.links}
                    from={reprints.from}
                    to={reprints.to}
                    total={reprints.total}
                />
            </PageBody>
        </>
    );
}

Reprints.layout = {
    breadcrumbs: [{ title: 'Reimpresiones', href: reprintsRoute() }],
};
