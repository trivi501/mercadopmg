import { Head, Link } from '@inertiajs/react';
import { Download, Printer } from 'lucide-react';
import {
    CashSummaryView,
    CloseCashDialog,
} from '@/components/padron/cash-session';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { StatusBadge } from '@/components/padron/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { index, pdf, thermal } from '@/routes/cash-sessions';
import { show as receiptShow } from '@/routes/receipts';
import type { CashSession, CashSummary, Receipt } from '@/types/padron';

type Props = {
    session: { data: CashSession };
    summary: CashSummary;
    receipts: { data: Receipt[] };
    can: { close: boolean };
};

export default function CashSessionShow({
    session: { data: s },
    summary,
    receipts,
    can,
}: Props) {
    const openTicket = () =>
        window.open(
            thermal(s.id, { query: { width: 58, print: 1 } }).url,
            '_blank',
            'width=420,height=760',
        );

    return (
        <>
            <Head title={`Corte ${s.folio}`} />
            <PageBody>
                <PageHeader
                    title={
                        <span className="flex flex-wrap items-center gap-2">
                            Corte <span className="font-mono">{s.folio}</span>
                            <StatusBadge
                                status={s.status}
                                label={s.status_label}
                            />
                        </span>
                    }
                    description={
                        <>
                            {s.user?.name} · Inicio {s.opened_at_label}
                            {s.closed_at_label &&
                                ` · Corte final ${s.closed_at_label}`}
                            {s.closed_by &&
                                s.closed_by.id !== s.user?.id &&
                                ` por ${s.closed_by.name}`}
                        </>
                    }
                    actions={
                        <>
                            <Button variant="outline" onClick={openTicket}>
                                <Printer /> Imprimir ticket
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={pdf(s.id).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Download /> Reporte PDF
                                </a>
                            </Button>
                            {can.close && (
                                <CloseCashDialog
                                    session={s}
                                    summary={summary}
                                />
                            )}
                        </>
                    }
                />

                {s.is_open && (
                    <div className="rounded-lg border border-sky-300 bg-sky-50 p-3 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-200">
                        Corte abierto: los totales se actualizan con cada cobro.
                        El reporte impreso ahora es un corte parcial.
                    </div>
                )}

                <CashSummaryView session={s} summary={summary} />

                {(s.opening_notes || s.closing_notes) && (
                    <Card className="gap-2">
                        <CardContent className="grid gap-1 text-sm">
                            {s.opening_notes && (
                                <p>
                                    <span className="text-muted-foreground">
                                        Notas de inicio:{' '}
                                    </span>
                                    {s.opening_notes}
                                </p>
                            )}
                            {s.closing_notes && (
                                <p>
                                    <span className="text-muted-foreground">
                                        Notas del corte:{' '}
                                    </span>
                                    {s.closing_notes}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card className="gap-3 pb-0">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Recibos del corte ({receipts.data.length})
                        </CardTitle>
                    </CardHeader>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-6">Folio</TableHead>
                                <TableHead>Hora</TableHead>
                                <TableHead>Contribuyente</TableHead>
                                <TableHead>Concepto</TableHead>
                                <TableHead>Forma de pago</TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                                <TableHead className="pr-6">Estado</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {receipts.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        Todavía no hay cobros en este corte.
                                    </TableCell>
                                </TableRow>
                            )}
                            {receipts.data.map((r) => (
                                <TableRow
                                    key={r.id}
                                    className={cn(
                                        r.status === 'cancelled' &&
                                            'text-muted-foreground line-through',
                                    )}
                                >
                                    <TableCell className="pl-6 font-mono">
                                        <Link
                                            href={receiptShow(r.id)}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {r.folio}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {r.time}
                                    </TableCell>
                                    <TableCell className="max-w-56 truncate">
                                        {r.merchant && (
                                            <>
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {r.merchant.folio}
                                                </span>{' '}
                                                {r.merchant.taxpayer_name}
                                            </>
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
                                    <TableCell>
                                        {r.payment_method_label}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {money(r.total)}
                                    </TableCell>
                                    <TableCell className="pr-6">
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
            </PageBody>
        </>
    );
}

CashSessionShow.layout = {
    breadcrumbs: [{ title: 'Cortes de caja', href: index() }],
};
