import { Head, Link, usePage } from '@inertiajs/react';
import { Ban, Download, Printer, RotateCcw, Share2 } from 'lucide-react';
import { toast } from 'sonner';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { ReasonDialog } from '@/components/padron/reason-dialog';
import { StatusBadge } from '@/components/padron/status-badge';
import type { Ticket } from '@/components/padron/ticket-preview';
import { TicketPreview } from '@/components/padron/ticket-preview';
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
import { show as merchantShow } from '@/routes/merchants';
import {
    cancel,
    index,
    pdf,
    reprint,
    thermal,
    verify,
} from '@/routes/receipts';
import type { Receipt } from '@/types/padron';

type Props = {
    receipt: { data: Receipt };
    ticket: Ticket & { qr_content: string };
    qr: string;
    can: { print: boolean; reprint: boolean; cancel: boolean };
};

export default function ReceiptShow({
    receipt: { data: r },
    ticket,
    qr,
    can,
}: Props) {
    const { flash } = usePage();
    const newReprintId = (flash as Record<string, unknown>)?.reprint_id as
        | number
        | undefined;

    const openTicket = (query: Record<string, string | number>) =>
        window.open(
            thermal(r.id, { query: { width: 58, ...query } }).url,
            '_blank',
            'width=420,height=760',
        );

    const share = async () => {
        const url = verify(r.uuid).url;
        const absolute = new URL(url, window.location.origin).toString();

        if (navigator.share) {
            await navigator
                .share({
                    title: `Recibo ${r.folio}`,
                    text: `Recibo ${r.folio} por ${money(r.total)}`,
                    url: absolute,
                })
                .catch(() => undefined);
        } else {
            await navigator.clipboard.writeText(absolute);
            toast.success('Enlace de verificación copiado.');
        }
    };

    return (
        <>
            <Head title={`Recibo ${r.folio}`} />
            <PageBody>
                <PageHeader
                    title={
                        <span className="flex flex-wrap items-center gap-2">
                            Recibo <span className="font-mono">{r.folio}</span>
                            <StatusBadge
                                status={r.status}
                                label={r.status_label}
                            />
                        </span>
                    }
                    description={
                        r.merchant && (
                            <Link
                                href={merchantShow(r.merchant.id)}
                                className="hover:underline"
                            >
                                {r.merchant.folio} · {r.merchant.taxpayer_name}
                            </Link>
                        )
                    }
                    actions={
                        <>
                            {can.print && (
                                <Button
                                    onClick={() => openTicket({ print: 1 })}
                                >
                                    <Printer /> Imprimir ticket
                                </Button>
                            )}
                            <Button variant="outline" asChild>
                                <a
                                    href={pdf(r.id).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Download /> PDF
                                </a>
                            </Button>
                            <Button variant="outline" onClick={share}>
                                <Share2 /> Compartir
                            </Button>
                            {can.reprint && (
                                <ReasonDialog
                                    trigger={
                                        <Button variant="outline">
                                            <RotateCcw /> Reimprimir
                                        </Button>
                                    }
                                    title={`Reimprimir recibo ${r.folio}`}
                                    description="La reimpresión no genera un nuevo cobro. Quedará registrada con su número, usuario, fecha y motivo, y el ticket mostrará la leyenda REIMPRESIÓN."
                                    action={{
                                        url: reprint(r.id).url,
                                        method: 'post',
                                    }}
                                    submitLabel="Registrar reimpresión"
                                    suggestions={[
                                        'Ticket dañado',
                                        'Ticket extraviado',
                                        'Falla de impresora',
                                    ]}
                                />
                            )}
                            {can.cancel && (
                                <ReasonDialog
                                    trigger={
                                        <Button variant="destructive">
                                            <Ban /> Cancelar
                                        </Button>
                                    }
                                    title={`Cancelar recibo ${r.folio}`}
                                    description="El recibo se conserva en el historial con estado CANCELADO y su folio no se reutiliza. Las anualidades incluidas vuelven a quedar pendientes."
                                    action={{
                                        url: cancel(r.id).url,
                                        method: 'post',
                                    }}
                                    submitLabel="Cancelar recibo"
                                    destructive
                                />
                            )}
                        </>
                    }
                />

                {newReprintId && (
                    <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                        Reimpresión registrada. Imprima ahora el ticket con la
                        leyenda REIMPRESIÓN.
                        <Button
                            size="sm"
                            onClick={() =>
                                openTicket({ reprint: newReprintId, print: 1 })
                            }
                        >
                            <Printer /> Imprimir reimpresión
                        </Button>
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-[22rem_1fr]">
                    <div className="rounded-xl bg-muted p-4">
                        <TicketPreview ticket={ticket} qr={qr} />
                    </div>

                    <div className="grid content-start gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Detalle
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-x-8 gap-y-1 text-sm xl:grid-cols-2">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Fecha
                                    </span>
                                    <span>
                                        {r.date} {r.time}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Usuario
                                    </span>
                                    <span>{r.user}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Forma de pago
                                    </span>
                                    <span>{r.payment_method_label}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Origen
                                    </span>
                                    <span>
                                        {r.source === 'android'
                                            ? 'App Android'
                                            : 'Web'}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Impreso
                                    </span>
                                    <span>{r.printed_at ? 'Sí' : 'No'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Reimpresiones
                                    </span>
                                    <span>{r.reprint_count}</span>
                                </div>
                                <div className="flex justify-between font-semibold xl:col-span-2">
                                    <span>Total</span>
                                    <span className="tabular-nums">
                                        {money(r.total)} MXN
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        {r.status === 'cancelled' && (
                            <Card className="border-destructive/40">
                                <CardHeader>
                                    <CardTitle className="text-base text-destructive">
                                        Recibo cancelado
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="text-sm">
                                    <p>{r.cancellation_reason}</p>
                                    <p className="mt-1 text-muted-foreground">
                                        {r.canceller} ·{' '}
                                        {r.cancelled_at &&
                                            new Date(
                                                r.cancelled_at,
                                            ).toLocaleString('es-MX')}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        <Card className="py-0">
                            <CardHeader className="pt-6">
                                <CardTitle className="text-base">
                                    Reimpresiones
                                </CardTitle>
                            </CardHeader>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>No.</TableHead>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Usuario</TableHead>
                                        <TableHead>Motivo</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {(r.reprints ?? []).length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="py-6 text-center text-muted-foreground"
                                            >
                                                Sin reimpresiones.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {r.reprints?.map((rp) => (
                                        <TableRow key={rp.id}>
                                            <TableCell>
                                                {rp.reprint_number}
                                            </TableCell>
                                            <TableCell>
                                                {rp.date} {rp.time}
                                            </TableCell>
                                            <TableCell>{rp.user}</TableCell>
                                            <TableCell className="max-w-64 truncate">
                                                {rp.reason}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        openTicket({
                                                            reprint: rp.id,
                                                        })
                                                    }
                                                >
                                                    Ver ticket
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>
                    </div>
                </div>
            </PageBody>
        </>
    );
}

ReceiptShow.layout = {
    breadcrumbs: [{ title: 'Recibos', href: index() }],
};
