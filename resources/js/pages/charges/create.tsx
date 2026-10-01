import { Head, Link, router, useForm } from '@inertiajs/react';
import { Minus, Plus, Search, Wallet } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { FilterSelect } from '@/components/padron/filter-select';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { StatusBadge } from '@/components/padron/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { money } from '@/lib/format';
import { create, store } from '@/routes/charges';
import { show as merchantShow } from '@/routes/merchants';
import type { Account, Concept, Merchant, Option } from '@/types/padron';

type Props = {
    merchant: { data: Merchant } | null;
    account: Account | null;
    search: { data: Merchant[] } | [];
    concepts: Concept[];
    options: { payment_methods: Option[] };
    currentYear: number;
};

type Line = { concept_id: number; quantity: number; fiscal_year?: number };

export default function ChargeCreate({
    merchant: wrapped,
    account,
    search,
    concepts,
    options,
    currentYear,
}: Props) {
    const merchant = wrapped?.data ?? null;
    const results = Array.isArray(search) ? [] : search.data;
    const [q, setQ] = useState('');

    const ticket = concepts.find((c) => c.type === 'ticket');
    const annuality = concepts.find((c) => c.type === 'annuality');
    const others = concepts.filter((c) => c.type === 'other');
    const pendingYears =
        account?.annualities
            .filter((a) => a.status === 'pending')
            .map((a) => a.fiscal_year) ?? [];

    const [tickets, setTickets] = useState(1);
    const [annualityYear, setAnnualityYear] = useState<number | null>(null);
    const [extras, setExtras] = useState<Record<number, number>>({});

    // Un UUID por operación: si el formulario se envía dos veces no se cobra doble.
    const [uuid] = useState(() => crypto.randomUUID());

    const form = useForm<{
        uuid: string;
        merchant_id: number | null;
        payment_method: string;
        reference: string;
        items: Line[];
    }>({
        uuid,
        merchant_id: merchant?.id ?? null,
        payment_method: 'cash',
        reference: '',
        items: [],
    });

    useEffect(() => {
        const timer = setTimeout(() => {
            if (q.trim().length >= 2) {
                router.reload({ data: { q }, only: ['search'] });
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [q]);

    const lines = useMemo(() => {
        const list: (Line & { label: string; unit: number })[] = [];

        if (ticket && tickets > 0) {
            list.push({
                concept_id: ticket.id,
                quantity: tickets,
                label: `${ticket.name} × ${tickets}`,
                unit: Number(ticket.amount),
            });
        }

        if (annuality && annualityYear) {
            const pending = account?.annualities.find(
                (a) => a.fiscal_year === annualityYear,
            );
            list.push({
                concept_id: annuality.id,
                quantity: 1,
                fiscal_year: annualityYear,
                label: `${annuality.name} ${annualityYear}`,
                unit: Number(pending?.amount ?? annuality.amount),
            });
        }

        for (const c of others) {
            if (extras[c.id]) {
                list.push({
                    concept_id: c.id,
                    quantity: extras[c.id],
                    label: `${c.name} × ${extras[c.id]}`,
                    unit: Number(c.amount),
                });
            }
        }

        return list;
    }, [ticket, tickets, annuality, annualityYear, others, extras, account]);

    const total = lines.reduce((sum, l) => sum + l.unit * l.quantity, 0);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            merchant_id: merchant?.id,
            items: lines.map(({ concept_id, quantity, fiscal_year }) => ({
                concept_id,
                quantity,
                fiscal_year,
            })),
        }));
        form.post(store().url);
    };

    return (
        <>
            <Head title="Cobrar" />
            <PageBody>
                <PageHeader
                    title="Registrar cobro"
                    description="Boletos de tianguista, anualidad de uso de suelo y otros conceptos"
                />

                {!merchant ? (
                    <Card className="max-w-2xl">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Buscar comerciante
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Folio (COM-000001), nombre o teléfono"
                                    className="pl-8"
                                    autoFocus
                                />
                            </div>
                            <ul className="divide-y rounded-md border empty:hidden">
                                {results.map((m) => (
                                    <li key={m.id}>
                                        <Link
                                            href={create({
                                                query: { merchant: m.id },
                                            })}
                                            className="flex items-center justify-between gap-2 px-3 py-2 hover:bg-muted"
                                        >
                                            <span>
                                                <span className="font-mono">
                                                    {m.folio}
                                                </span>{' '}
                                                · {m.taxpayer_name}
                                                <span className="block text-xs text-muted-foreground">
                                                    {[
                                                        m.business_type,
                                                        m.tianguis,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </span>
                                            <StatusBadge
                                                status={m.status}
                                                label={m.status_label}
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                            {q.length >= 2 && results.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Sin resultados.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <form
                        onSubmit={submit}
                        className="grid gap-4 lg:grid-cols-3"
                    >
                        <div className="grid content-start gap-4 lg:col-span-2">
                            <Card>
                                <CardContent className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div className="text-lg font-semibold">
                                            <span className="font-mono">
                                                {merchant.folio}
                                            </span>{' '}
                                            · {merchant.taxpayer_name}
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {[
                                                merchant.business_type,
                                                merchant.tianguis,
                                                merchant.route,
                                                `${Number(merchant.meters)} m`,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={merchantShow(merchant.id)}
                                            >
                                                Ver ficha
                                            </Link>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={create()}>Cambiar</Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {merchant.status !== 'active' && (
                                <p className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                                    El comerciante está{' '}
                                    {merchant.status_label.toLowerCase()}; no se
                                    le pueden registrar cobros.
                                </p>
                            )}

                            {ticket && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Boletos de tianguista
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="flex flex-wrap items-center gap-4">
                                        <div className="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                onClick={() =>
                                                    setTickets((t) =>
                                                        Math.max(0, t - 1),
                                                    )
                                                }
                                                aria-label="Menos"
                                            >
                                                <Minus />
                                            </Button>
                                            <Input
                                                type="number"
                                                min={0}
                                                max={500}
                                                value={tickets}
                                                onChange={(e) =>
                                                    setTickets(
                                                        Math.max(
                                                            0,
                                                            Math.min(
                                                                500,
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ) || 0,
                                                            ),
                                                        ),
                                                    )
                                                }
                                                className="w-20 text-center text-lg tabular-nums"
                                                aria-label="Cantidad de boletos"
                                            />
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                onClick={() =>
                                                    setTickets((t) =>
                                                        Math.min(500, t + 1),
                                                    )
                                                }
                                                aria-label="Más"
                                            >
                                                <Plus />
                                            </Button>
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {tickets} × {money(ticket.amount)} ={' '}
                                            <span className="font-semibold text-foreground tabular-nums">
                                                {money(
                                                    tickets *
                                                        Number(ticket.amount),
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex flex-wrap gap-1">
                                            {[1, 2, 3, 4, 5, 10].map((n) => (
                                                <Button
                                                    key={n}
                                                    type="button"
                                                    size="sm"
                                                    variant={
                                                        tickets === n
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                    onClick={() =>
                                                        setTickets(n)
                                                    }
                                                >
                                                    {n}
                                                </Button>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {annuality && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Anualidad · {annuality.name}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2">
                                        {pendingYears.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                Anualidad {currentYear}{' '}
                                                <StatusBadge
                                                    status="paid"
                                                    label="PAGADA"
                                                />
                                            </p>
                                        ) : (
                                            pendingYears.map((year) => (
                                                <label
                                                    key={year}
                                                    className="flex items-center gap-3 text-sm"
                                                >
                                                    <Checkbox
                                                        checked={
                                                            annualityYear ===
                                                            year
                                                        }
                                                        onCheckedChange={(v) =>
                                                            setAnnualityYear(
                                                                v ? year : null,
                                                            )
                                                        }
                                                    />
                                                    Ejercicio {year} —{' '}
                                                    {money(
                                                        account?.annualities.find(
                                                            (a) =>
                                                                a.fiscal_year ===
                                                                year,
                                                        )?.amount ??
                                                            annuality.amount,
                                                    )}
                                                    <StatusBadge
                                                        status="pending"
                                                        label="PENDIENTE"
                                                    />
                                                </label>
                                            ))
                                        )}
                                    </CardContent>
                                </Card>
                            )}

                            {others.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Otros conceptos
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-2">
                                        {others.map((c) => (
                                            <div
                                                key={c.id}
                                                className="flex items-center justify-between gap-2 text-sm"
                                            >
                                                <span>
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {c.code}
                                                    </span>{' '}
                                                    {c.name} · {money(c.amount)}
                                                </span>
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    className="w-20"
                                                    value={extras[c.id] ?? 0}
                                                    onChange={(e) =>
                                                        setExtras((x) => ({
                                                            ...x,
                                                            [c.id]: Math.max(
                                                                0,
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ) || 0,
                                                            ),
                                                        }))
                                                    }
                                                    aria-label={`Cantidad ${c.name}`}
                                                />
                                            </div>
                                        ))}
                                    </CardContent>
                                </Card>
                            )}
                        </div>

                        <Card className="content-start self-start lg:sticky lg:top-4">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Resumen
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <ul className="space-y-1 text-sm">
                                    {lines.length === 0 && (
                                        <li className="text-muted-foreground">
                                            Seleccione al menos un concepto.
                                        </li>
                                    )}
                                    {lines.map((l) => (
                                        <li
                                            key={`${l.concept_id}-${l.fiscal_year ?? ''}`}
                                            className="flex justify-between gap-2"
                                        >
                                            <span>{l.label}</span>
                                            <span className="tabular-nums">
                                                {money(l.unit * l.quantity)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                                <div className="flex items-baseline justify-between border-t pt-3">
                                    <span className="font-medium">TOTAL</span>
                                    <span className="text-2xl font-semibold tabular-nums">
                                        {money(total)}
                                    </span>
                                </div>
                                <div className="grid gap-1.5">
                                    <Label>Forma de pago</Label>
                                    <FilterSelect
                                        value={form.data.payment_method}
                                        onChange={(v) =>
                                            form.setData('payment_method', v)
                                        }
                                        items={options.payment_methods}
                                        placeholder="Forma de pago"
                                        allowAll={false}
                                        className="sm:w-full"
                                    />
                                </div>
                                {form.data.payment_method !== 'cash' && (
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="reference">
                                            Referencia
                                        </Label>
                                        <Input
                                            id="reference"
                                            value={form.data.reference}
                                            onChange={(e) =>
                                                form.setData(
                                                    'reference',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                )}
                                <InputError
                                    message={
                                        Object.entries(form.errors)
                                            .filter(
                                                ([k]) => k !== 'payment_method',
                                            )
                                            .map(([, v]) => v)[0]
                                    }
                                />
                                <Button
                                    type="submit"
                                    size="lg"
                                    className="w-full"
                                    disabled={
                                        form.processing ||
                                        lines.length === 0 ||
                                        merchant.status !== 'active'
                                    }
                                >
                                    {form.processing ? <Spinner /> : <Wallet />}{' '}
                                    Cobrar {money(total)}
                                </Button>
                            </CardContent>
                        </Card>
                    </form>
                )}
            </PageBody>
        </>
    );
}

ChargeCreate.layout = {
    breadcrumbs: [{ title: 'Cobrar', href: create() }],
};
