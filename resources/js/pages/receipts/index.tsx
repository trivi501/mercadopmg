import { Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { FilterSelect, toItems } from '@/components/padron/filter-select';
import { useFilters } from '@/components/padron/filters';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { Pagination } from '@/components/padron/pagination';
import { StatusBadge } from '@/components/padron/status-badge';
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
import { money, number } from '@/lib/format';
import { show as merchantShow } from '@/routes/merchants';
import { index, show } from '@/routes/receipts';
import type { NamedItem, Option, Paginated, Receipt } from '@/types/padron';

type Props = {
    receipts: Paginated<Receipt>;
    totals: { count: number; amount: string };
    filters: Record<string, string>;
    options: {
        tianguis: NamedItem[];
        routes: NamedItem[];
        users: NamedItem[];
        concepts: { id: number; code: string; name: string }[];
        receipt_statuses: Option[];
    };
};

export default function ReceiptsIndex({
    receipts,
    totals,
    filters: initial,
    options,
}: Props) {
    const [filters, set, reset] = useFilters(
        {
            search: initial.search ?? '',
            date_from: initial.date_from ?? '',
            date_to: initial.date_to ?? '',
            tianguis_id: initial.tianguis_id ?? '',
            route_id: initial.route_id ?? '',
            user_id: initial.user_id ?? '',
            concept_id: initial.concept_id ?? '',
            status: initial.status ?? '',
        },
        index().url,
    );

    return (
        <>
            <Head title="Historial de recibos" />
            <PageBody>
                <PageHeader
                    title="Historial de recibos"
                    description={`${number(totals.count)} recibos · ${money(totals.amount)} recaudado (sin cancelados)`}
                />

                <div className="flex flex-wrap items-end gap-2">
                    <div className="relative w-full sm:w-64">
                        <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={filters.search}
                            onChange={(e) => set('search', e.target.value)}
                            placeholder="Folio o comerciante"
                            className="pl-8"
                            aria-label="Buscar"
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
                        value={filters.tianguis_id}
                        onChange={(v) => set('tianguis_id', v)}
                        items={toItems(options.tianguis)}
                        placeholder="Tianguis"
                        allLabel="Todos los tianguis"
                    />
                    <FilterSelect
                        value={filters.route_id}
                        onChange={(v) => set('route_id', v)}
                        items={toItems(options.routes)}
                        placeholder="Ruta"
                        allLabel="Todas las rutas"
                    />
                    <FilterSelect
                        value={filters.user_id}
                        onChange={(v) => set('user_id', v)}
                        items={toItems(options.users)}
                        placeholder="Usuario"
                        allLabel="Todos los usuarios"
                    />
                    <FilterSelect
                        value={filters.concept_id}
                        onChange={(v) => set('concept_id', v)}
                        items={options.concepts.map((c) => ({
                            value: c.id,
                            label: `${c.code} ${c.name}`,
                        }))}
                        placeholder="Concepto"
                        allLabel="Todos los conceptos"
                    />
                    <FilterSelect
                        value={filters.status}
                        onChange={(v) => set('status', v)}
                        items={options.receipt_statuses}
                        placeholder="Estado"
                        allLabel="Todos los estados"
                    />
                    <Button variant="ghost" onClick={reset}>
                        Limpiar
                    </Button>
                </div>

                <Card className="py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Folio</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Contribuyente</TableHead>
                                <TableHead>Concepto</TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                                <TableHead>Tianguis / Ruta</TableHead>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Estado</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {receipts.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={8}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        No hay recibos con esos filtros.
                                    </TableCell>
                                </TableRow>
                            )}
                            {receipts.data.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell className="font-mono">
                                        <Link
                                            href={show(r.id)}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {r.folio}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {r.date}{' '}
                                        <span className="text-muted-foreground">
                                            {r.time}
                                        </span>
                                    </TableCell>
                                    <TableCell className="max-w-56 truncate">
                                        {r.merchant && (
                                            <Link
                                                href={merchantShow(
                                                    r.merchant.id,
                                                )}
                                                className="hover:underline"
                                            >
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {r.merchant.folio}
                                                </span>{' '}
                                                {r.merchant.taxpayer_name}
                                            </Link>
                                        )}
                                    </TableCell>
                                    <TableCell className="max-w-56 truncate">
                                        {r.items
                                            ?.map(
                                                (i) =>
                                                    `${i.description}${i.quantity > 1 ? ` × ${i.quantity}` : ''}`,
                                            )
                                            .join(', ')}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {money(r.total)}
                                    </TableCell>
                                    <TableCell className="max-w-40 truncate">
                                        {[r.tianguis, r.route]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </TableCell>
                                    <TableCell>{r.user}</TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={r.status}
                                            label={r.status_label}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>

                <Pagination
                    links={receipts.meta.links}
                    from={receipts.meta.from}
                    to={receipts.meta.to}
                    total={receipts.meta.total}
                />
            </PageBody>
        </>
    );
}

ReceiptsIndex.layout = {
    breadcrumbs: [{ title: 'Recibos', href: index() }],
};
