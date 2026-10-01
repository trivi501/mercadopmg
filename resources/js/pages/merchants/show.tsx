import { Head, Link, router } from '@inertiajs/react';
import { Download, Pencil, Printer, Trash2, Wallet } from 'lucide-react';
import { LocationPicker } from '@/components/padron/location-picker';
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
import { date, money } from '@/lib/format';
import { create as chargeCreate } from '@/routes/charges';
import { destroy, edit, index, qr as qrRoute, show } from '@/routes/merchants';
import { show as receiptShow } from '@/routes/receipts';
import type { Account, Merchant, Receipt } from '@/types/padron';

type Props = {
    merchant: { data: Merchant };
    account: Account;
    qr: string;
    receipts: { data: Receipt[] };
    reprints: {
        id: number;
        receipt_id: number;
        receipt_folio: string;
        number: number;
        date: string;
        time: string;
        user: string;
        reason: string;
    }[];
    can: { update: boolean; delete: boolean; charge: boolean };
};

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="grid grid-cols-[8rem_minmax(0,1fr)] gap-2 py-1 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 break-words">{value || '—'}</dd>
        </div>
    );
}

export default function MerchantShow({
    merchant: { data: m },
    account,
    qr,
    receipts,
    reprints,
    can,
}: Props) {
    const lat = m.latitude ? Number(m.latitude) : null;
    const lng = m.longitude ? Number(m.longitude) : null;

    const remove = () => {
        if (
            confirm(
                `¿Dar de baja a ${m.folio} del padrón? Los pagos y recibos se conservan.`,
            )
        ) {
            router.delete(destroy(m.id).url);
        }
    };

    return (
        <>
            <Head title={`${m.folio} · ${m.taxpayer_name}`} />
            <PageBody>
                <PageHeader
                    title={
                        <span className="flex flex-wrap items-center gap-2">
                            <span className="font-mono">{m.folio}</span>
                            <span>{m.taxpayer_name}</span>
                            <StatusBadge
                                status={m.status}
                                label={m.status_label}
                            />
                        </span>
                    }
                    description={[
                        m.trade_name,
                        m.business_type,
                        m.commerce_type_label,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                    actions={
                        <>
                            {can.update && (
                                <Button variant="outline" asChild>
                                    <Link href={edit(m.id)}>
                                        <Pencil /> Editar
                                    </Link>
                                </Button>
                            )}
                            {can.delete && (
                                <Button variant="outline" onClick={remove}>
                                    <Trash2 /> Dar de baja
                                </Button>
                            )}
                            {can.charge && (
                                <Button asChild>
                                    <Link
                                        href={chargeCreate({
                                            query: { merchant: m.id },
                                        })}
                                    >
                                        <Wallet /> Cobrar
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Datos del contribuyente
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-x-6 xl:grid-cols-2">
                            <dl>
                                <Row
                                    label="Folio"
                                    value={
                                        <span className="font-mono">
                                            {m.folio}
                                        </span>
                                    }
                                />
                                <Row label="Nombre" value={m.taxpayer_name} />
                                <Row
                                    label="Nombre comercial"
                                    value={m.trade_name}
                                />
                                <Row label="Giro" value={m.business_type} />
                                <Row
                                    label="Descripción"
                                    value={m.business_description}
                                />
                                <Row label="Teléfono" value={m.phone} />
                                <Row
                                    label="Domicilio"
                                    value={[
                                        m.address,
                                        m.neighborhood,
                                        m.municipality,
                                        m.state,
                                    ]
                                        .filter(Boolean)
                                        .join(', ')}
                                />
                            </dl>
                            <dl>
                                <Row
                                    label="Tipo"
                                    value={m.commerce_type_label}
                                />
                                <Row label="Tianguis" value={m.tianguis} />
                                <Row label="Ruta" value={m.route} />
                                <Row
                                    label="Metros"
                                    value={`${Number(m.meters)} m`}
                                />
                                <Row
                                    label="Ubicación"
                                    value={[
                                        m.location,
                                        m.location_street &&
                                            `${m.location_street} ${m.location_number ?? ''}`,
                                        m.location_neighborhood,
                                    ]
                                        .filter(Boolean)
                                        .join(', ')}
                                />
                                <Row
                                    label="Referencia"
                                    value={m.location_reference}
                                />
                                <Row
                                    label="Fecha de alta"
                                    value={date(m.registered_at)}
                                />
                            </dl>
                            {m.notes && (
                                <p className="mt-2 text-sm text-muted-foreground xl:col-span-2">
                                    {m.notes}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Código QR
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col items-center gap-3">
                            <div
                                className="rounded-lg bg-white p-2"
                                dangerouslySetInnerHTML={{ __html: qr }}
                            />
                            <p className="text-center text-xs text-muted-foreground">
                                Contiene solo el folio{' '}
                                <span className="font-mono">{m.qr}</span>.
                                Escanéelo con la app para consultar y cobrar.
                            </p>
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={qrRoute(m.id).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Download /> Descargar QR
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-2">
                        <CardTitle className="text-base">
                            Estado de cuenta {account.fiscal_year}
                        </CardTitle>
                        <div className="flex gap-1">
                            {[
                                account.fiscal_year - 1,
                                account.fiscal_year,
                                new Date().getFullYear(),
                            ]
                                .filter((y, i, a) => a.indexOf(y) === i)
                                .map((y) => (
                                    <Button
                                        key={y}
                                        size="sm"
                                        variant={
                                            y === account.fiscal_year
                                                ? 'default'
                                                : 'outline'
                                        }
                                        asChild
                                    >
                                        <Link
                                            href={show(m.id, {
                                                query: { year: y },
                                            })}
                                            preserveScroll
                                            only={['account']}
                                        >
                                            {y}
                                        </Link>
                                    </Button>
                                ))}
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        <div className="rounded-lg border p-4">
                            <div className="text-sm text-muted-foreground">
                                Anualidad
                            </div>
                            {account.annuality ? (
                                <>
                                    <div className="mt-1 text-sm">
                                        {account.annuality.concept}
                                    </div>
                                    <div className="mt-2 flex items-center justify-between">
                                        <span className="text-xl font-semibold tabular-nums">
                                            {money(account.annuality.amount)}
                                        </span>
                                        <StatusBadge
                                            status={account.annuality.status}
                                            label={
                                                account.annuality.status_label
                                            }
                                        />
                                    </div>
                                    {account.annuality.receipt_folio && (
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            Recibo{' '}
                                            {account.annuality.receipt_folio}
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="mt-1 text-sm text-muted-foreground">
                                    Sin obligación registrada
                                </div>
                            )}
                        </div>
                        <div className="rounded-lg border p-4">
                            <div className="text-sm text-muted-foreground">
                                Boletos
                            </div>
                            <div className="mt-1 text-sm">
                                {account.tickets.quantity} ×{' '}
                                {money(account.tickets.unit_price)}
                            </div>
                            <div className="mt-2 text-xl font-semibold tabular-nums">
                                {money(account.tickets.total)}
                            </div>
                        </div>
                        <div className="rounded-lg border p-4">
                            <div className="text-sm text-muted-foreground">
                                Total pagado {account.fiscal_year}
                            </div>
                            <div className="mt-2 text-xl font-semibold tabular-nums">
                                {money(account.total_paid)}
                            </div>
                            <div className="mt-1 text-sm">
                                Adeudos:{' '}
                                {account.debt.count === 0 ? (
                                    <span className="text-muted-foreground">
                                        sin adeudos
                                    </span>
                                ) : (
                                    <span className="font-medium text-destructive">
                                        {money(account.debt.total)} (
                                        {account.debt.years.join(', ')})
                                    </span>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="py-0 lg:col-span-2">
                        <CardHeader className="pt-6">
                            <CardTitle className="text-base">
                                Historial de recibos
                            </CardTitle>
                        </CardHeader>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Folio</TableHead>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Conceptos</TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    <TableHead>Estado</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {receipts.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            Sin pagos registrados.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {receipts.data.map((r) => (
                                    <TableRow key={r.id}>
                                        <TableCell className="font-mono">
                                            <Link
                                                href={receiptShow(r.id)}
                                                className="text-primary hover:underline"
                                            >
                                                {r.folio}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {r.date} {r.time}
                                        </TableCell>
                                        <TableCell className="max-w-64 truncate">
                                            {r.concepts}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {money(r.total)}
                                        </TableCell>
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

                    <div className="grid content-start gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Ubicación
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {lat !== null && lng !== null ? (
                                    <LocationPicker
                                        lat={lat}
                                        lng={lng}
                                        height={220}
                                    />
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Sin coordenadas registradas.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Histórico de metros
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                {m.measurements?.map((ms) => (
                                    <div
                                        key={ms.id}
                                        className="flex justify-between gap-2 border-b pb-2 last:border-0"
                                    >
                                        <div>
                                            <div className="font-medium">
                                                {Number(ms.meters)} m
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {ms.reason}
                                            </div>
                                        </div>
                                        <div className="text-right text-xs text-muted-foreground">
                                            {date(ms.start_date)} –{' '}
                                            {ms.end_date
                                                ? date(ms.end_date)
                                                : 'vigente'}
                                            <div>{ms.user}</div>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        {reprints.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Printer className="size-4" />{' '}
                                        Reimpresiones
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    {reprints.map((r) => (
                                        <div
                                            key={r.id}
                                            className="border-b pb-2 last:border-0"
                                        >
                                            <div className="flex justify-between">
                                                <Link
                                                    href={receiptShow(
                                                        r.receipt_id,
                                                    )}
                                                    className="font-mono text-primary hover:underline"
                                                >
                                                    {r.receipt_folio}
                                                </Link>
                                                <span>No. {r.number}</span>
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {r.date} {r.time} · {r.user} ·{' '}
                                                {r.reason}
                                            </div>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </PageBody>
        </>
    );
}

MerchantShow.layout = {
    breadcrumbs: [{ title: 'Padrón', href: index() }],
};
