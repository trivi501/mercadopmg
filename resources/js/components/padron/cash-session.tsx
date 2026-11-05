import { Form } from '@inertiajs/react';
import { Lock, PlayCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Field } from '@/components/padron/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { money } from '@/lib/format';
import { cn } from '@/lib/utils';
import { close, store } from '@/routes/cash-sessions';
import type { CashSession, CashSummary } from '@/types/padron';

/**
 * Inicio de cobro: el recaudador captura el fondo de caja con el que empieza.
 */
export function OpenCashDialog({ trigger }: { trigger?: ReactNode }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button>
                        <PlayCircle /> Iniciar cobro
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Iniciar cobro</DialogTitle>
                    <DialogDescription>
                        Se abre su corte de caja. Todos los cobros que registre
                        quedarán en este corte hasta que haga el corte final.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={store().url}
                    method="post"
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Fondo inicial de caja"
                                htmlFor="opening_amount"
                                error={errors.opening_amount ?? errors.general}
                                hint="Efectivo con el que empieza (cambio). Capture 0 si no lleva fondo."
                                required
                            >
                                <Input
                                    id="opening_amount"
                                    name="opening_amount"
                                    type="number"
                                    inputMode="decimal"
                                    min={0}
                                    step="0.01"
                                    defaultValue="0"
                                    required
                                    autoFocus
                                />
                            </Field>
                            <Field
                                label="Notas"
                                htmlFor="opening_notes"
                                error={errors.opening_notes}
                            >
                                <Textarea
                                    id="opening_notes"
                                    name="opening_notes"
                                    rows={2}
                                    placeholder="Opcional: tianguis, ruta o turno"
                                />
                            </Field>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    Iniciar cobro
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Corte final: arqueo del efectivo contado contra lo esperado.
 */
export function CloseCashDialog({
    session,
    summary,
    trigger,
}: {
    session: CashSession;
    summary: CashSummary;
    trigger?: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [counted, setCounted] = useState('');
    const difference =
        counted === '' ? null : Number(counted) - Number(summary.expected_cash);

    return (
        <Dialog
            open={open}
            onOpenChange={(v) => {
                setOpen(v);
                setCounted('');
            }}
        >
            <DialogTrigger asChild>
                {trigger ?? (
                    <Button variant="destructive">
                        <Lock /> Corte final
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Corte final {session.folio}</DialogTitle>
                    <DialogDescription>
                        Cuente el efectivo y captúrelo. Después de cerrar ya no
                        podrá registrar cobros en este corte.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-1 rounded-lg bg-muted p-3 text-sm">
                    <Row
                        label="Total cobrado"
                        value={money(summary.total_collected)}
                    />
                    <Row
                        label="Fondo inicial"
                        value={money(summary.opening_amount)}
                    />
                    <Row
                        label="Cobros en efectivo"
                        value={money(summary.cash_collected)}
                    />
                    <Row
                        label="Efectivo esperado"
                        value={money(summary.expected_cash)}
                        strong
                    />
                </div>
                <Form
                    action={close(session.id).url}
                    method="post"
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Efectivo contado"
                                htmlFor="counted_cash"
                                error={errors.counted_cash ?? errors.general}
                                required
                            >
                                <Input
                                    id="counted_cash"
                                    name="counted_cash"
                                    type="number"
                                    inputMode="decimal"
                                    min={0}
                                    step="0.01"
                                    value={counted}
                                    onChange={(e) => setCounted(e.target.value)}
                                    required
                                    autoFocus
                                />
                            </Field>
                            {difference !== null && (
                                <DifferenceNote difference={difference} />
                            )}
                            <Field
                                label="Notas del corte"
                                htmlFor="closing_notes"
                                error={errors.closing_notes}
                            >
                                <Textarea
                                    id="closing_notes"
                                    name="closing_notes"
                                    rows={2}
                                    placeholder="Opcional: explique sobrantes o faltantes"
                                />
                            </Field>
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Cerrar corte
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function DifferenceNote({ difference }: { difference: number }) {
    const label =
        difference > 0 ? 'Sobrante' : difference < 0 ? 'Faltante' : 'Cuadra';

    return (
        <p
            className={cn(
                'rounded-md px-3 py-2 text-sm font-medium',
                difference > 0 &&
                    'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
                difference < 0 &&
                    'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-300',
                difference === 0 && 'bg-muted',
            )}
        >
            {label}
            {difference !== 0 && `: ${money(Math.abs(difference))}`}
        </p>
    );
}

function Row({
    label,
    value,
    strong = false,
}: {
    label: string;
    value: ReactNode;
    strong?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex justify-between gap-4',
                strong && 'border-t pt-1 font-semibold',
            )}
        >
            <span className={cn(!strong && 'text-muted-foreground')}>
                {label}
            </span>
            <span className="tabular-nums">{value}</span>
        </div>
    );
}

/**
 * Totales del corte por concepto y por forma de pago, con el arqueo.
 */
export function CashSummaryView({
    session,
    summary,
}: {
    session: CashSession;
    summary: CashSummary;
}) {
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <Card className="gap-3">
                <CardHeader>
                    <CardTitle className="text-base">Por concepto</CardTitle>
                </CardHeader>
                <CardContent className="px-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-6">Concepto</TableHead>
                                <TableHead className="text-right">
                                    Cant.
                                </TableHead>
                                <TableHead className="pr-6 text-right">
                                    Importe
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {summary.by_concept.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="py-6 text-center text-muted-foreground"
                                    >
                                        Sin cobros todavía.
                                    </TableCell>
                                </TableRow>
                            )}
                            {summary.by_concept.map((c) => (
                                <TableRow key={c.code}>
                                    <TableCell className="pl-6">
                                        {c.name}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {c.quantity}
                                    </TableCell>
                                    <TableCell className="pr-6 text-right tabular-nums">
                                        {money(c.amount)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Card className="gap-3">
                <CardHeader>
                    <CardTitle className="text-base">
                        Por forma de pago
                    </CardTitle>
                </CardHeader>
                <CardContent className="px-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="pl-6">Forma</TableHead>
                                <TableHead className="text-right">
                                    Recibos
                                </TableHead>
                                <TableHead className="pr-6 text-right">
                                    Importe
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {summary.by_method.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="py-6 text-center text-muted-foreground"
                                    >
                                        —
                                    </TableCell>
                                </TableRow>
                            )}
                            {summary.by_method.map((m) => (
                                <TableRow key={m.method}>
                                    <TableCell className="pl-6">
                                        {m.label}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {m.count}
                                    </TableCell>
                                    <TableCell className="pr-6 text-right tabular-nums">
                                        {money(m.amount)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Card className="gap-3">
                <CardHeader>
                    <CardTitle className="text-base">
                        Arqueo de efectivo
                    </CardTitle>
                </CardHeader>
                <CardContent className="grid gap-1 text-sm">
                    <Row
                        label="Total cobrado"
                        value={money(summary.total_collected)}
                    />
                    {summary.cancelled > 0 && (
                        <Row
                            label={`Cancelados (${summary.cancelled}, no suman)`}
                            value={money(summary.cancelled_amount)}
                        />
                    )}
                    <Row
                        label="Fondo inicial"
                        value={money(summary.opening_amount)}
                    />
                    <Row
                        label="Cobros en efectivo"
                        value={money(summary.cash_collected)}
                    />
                    <Row
                        label="Efectivo esperado"
                        value={money(summary.expected_cash)}
                        strong
                    />
                    {!session.is_open && (
                        <>
                            <Row
                                label="Efectivo contado"
                                value={money(session.counted_cash)}
                                strong
                            />
                            <div className="pt-2">
                                <DifferenceNote
                                    difference={Number(session.difference)}
                                />
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
