import { Head, Link } from '@inertiajs/react';
import { Banknote, Clock, Receipt as ReceiptIcon, Wallet } from 'lucide-react';
import {
    CashSummaryView,
    CloseCashDialog,
    OpenCashDialog,
} from '@/components/padron/cash-session';
import { FilterSelect, toItems } from '@/components/padron/filter-select';
import { useFilters } from '@/components/padron/filters';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { Pagination } from '@/components/padron/pagination';
import { StatCard } from '@/components/padron/stat-card';
import { StatusBadge } from '@/components/padron/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/cash-sessions';
import type {
    CashSession,
    CashSummary,
    NamedItem,
    Option,
    Paginated,
} from '@/types/padron';

type Props = {
    sessions: Paginated<CashSession>;
    current: { session: { data: CashSession }; summary: CashSummary } | null;
    canOpen: boolean;
    viewAll: boolean;
    filters: Record<string, string>;
    options: { users: NamedItem[]; statuses: Option[] };
};

export default function CashSessionsIndex({
    sessions,
    current,
    canOpen,
    viewAll,
    filters: initial,
    options,
}: Props) {
    const [filters, set, reset] = useFilters(
        {
            user_id: initial.user_id ?? '',
            status: initial.status ?? '',
            date_from: initial.date_from ?? '',
            date_to: initial.date_to ?? '',
        },
        index().url,
    );

    const session = current?.session.data;

    return (
        <>
            <Head title="Cortes de caja" />
            <PageBody>
                <PageHeader
                    title="Cortes de caja"
                    description="Inicio de cobro con fondo de caja y corte final con arqueo del efectivo."
                    actions={
                        session ? (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={show(session.id)}>
                                        Ver corte {session.folio}
                                    </Link>
                                </Button>
                                <CloseCashDialog
                                    session={session}
                                    summary={current.summary}
                                />
                            </>
                        ) : (
                            canOpen && <OpenCashDialog />
                        )
                    }
                />

                {session ? (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard
                                label={`Corte ${session.folio} abierto`}
                                value={session.opened_at_label}
                                hint="Inicio de cobro"
                                icon={Clock}
                            />
                            <StatCard
                                label="Recibos"
                                value={current.summary.receipts}
                                hint={
                                    current.summary.first_folio
                                        ? `${current.summary.first_folio} a ${current.summary.last_folio}`
                                        : 'Sin cobros todavía'
                                }
                                icon={ReceiptIcon}
                            />
                            <StatCard
                                label="Total cobrado"
                                value={money(current.summary.total_collected)}
                                icon={Banknote}
                            />
                            <StatCard
                                label="Efectivo esperado"
                                value={money(current.summary.expected_cash)}
                                hint={`Fondo ${money(current.summary.opening_amount)} + efectivo ${money(current.summary.cash_collected)}`}
                                icon={Wallet}
                            />
                        </div>
                        <CashSummaryView
                            session={session}
                            summary={current.summary}
                        />
                    </>
                ) : (
                    canOpen && (
                        <Card className="border-dashed">
                            <CardContent className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p className="font-medium">
                                        No tiene un corte abierto
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Inicie el cobro para poder registrar
                                        cobros en la web o en la app.
                                    </p>
                                </div>
                                <OpenCashDialog />
                            </CardContent>
                        </Card>
                    )
                )}

                <h2 className="pt-2 text-base font-semibold">
                    {viewAll
                        ? 'Cortes de todos los recaudadores'
                        : 'Mis cortes'}
                </h2>

                <div className="flex flex-wrap items-end gap-2">
                    {viewAll && (
                        <FilterSelect
                            value={filters.user_id}
                            onChange={(v) => set('user_id', v)}
                            items={toItems(options.users)}
                            placeholder="Recaudador"
                            allLabel="Todos los recaudadores"
                        />
                    )}
                    <FilterSelect
                        value={filters.status}
                        onChange={(v) => set('status', v)}
                        items={options.statuses}
                        placeholder="Estado"
                        allLabel="Todos los estados"
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
                    <Button variant="ghost" onClick={reset}>
                        Limpiar
                    </Button>
                </div>

                <Card className="py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Corte</TableHead>
                                {viewAll && <TableHead>Recaudador</TableHead>}
                                <TableHead>Inicio</TableHead>
                                <TableHead>Corte final</TableHead>
                                <TableHead className="text-right">
                                    Cobrado
                                </TableHead>
                                <TableHead className="text-right">
                                    Esperado
                                </TableHead>
                                <TableHead className="text-right">
                                    Contado
                                </TableHead>
                                <TableHead className="text-right">
                                    Diferencia
                                </TableHead>
                                <TableHead>Estado</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {sessions.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={viewAll ? 9 : 8}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        No hay cortes con esos filtros.
                                    </TableCell>
                                </TableRow>
                            )}
                            {sessions.data.map((s) => {
                                const diff = Number(s.difference ?? 0);

                                return (
                                    <TableRow key={s.id}>
                                        <TableCell className="font-mono">
                                            <Link
                                                href={show(s.id)}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {s.folio}
                                            </Link>
                                        </TableCell>
                                        {viewAll && (
                                            <TableCell>
                                                {s.user?.name}
                                            </TableCell>
                                        )}
                                        <TableCell className="tabular-nums">
                                            {s.opened_at_label}
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {s.closed_at_label ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {s.is_open
                                                ? '—'
                                                : money(s.total_collected)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {s.is_open
                                                ? '—'
                                                : money(s.expected_cash)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {s.is_open
                                                ? '—'
                                                : money(s.counted_cash)}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'text-right font-medium tabular-nums',
                                                diff > 0 &&
                                                    'text-emerald-700 dark:text-emerald-400',
                                                diff < 0 &&
                                                    'text-red-700 dark:text-red-400',
                                            )}
                                        >
                                            {s.is_open
                                                ? '—'
                                                : `${diff > 0 ? '+' : diff < 0 ? '−' : ''}${money(Math.abs(diff))}`}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={s.status}
                                                label={s.status_label}
                                            />
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </Card>

                <Pagination
                    links={sessions.meta.links}
                    from={sessions.meta.from}
                    to={sessions.meta.to}
                    total={sessions.meta.total}
                />
            </PageBody>
        </>
    );
}

CashSessionsIndex.layout = {
    breadcrumbs: [{ title: 'Cortes de caja', href: index() }],
};
