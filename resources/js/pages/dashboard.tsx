import { Deferred, Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    Banknote,
    CalendarCheck,
    Printer,
    ReceiptText,
    Store,
    Tent,
    Ticket,
    Wallet,
} from 'lucide-react';
import { BarChartCard } from '@/components/charts/bar-chart-card';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { StatCard } from '@/components/padron/stat-card';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useCan } from '@/hooks/use-can';
import { money, number } from '@/lib/format';
import { dashboard } from '@/routes';
import { create as chargeCreate } from '@/routes/charges';
import { create as merchantCreate } from '@/routes/merchants';

type Series = { label: string; value: number }[];

type Props = {
    indicators: {
        merchants_total: number;
        merchants_active: number;
        merchants_inactive: number;
        tianguistas: number;
        revenue_today: string;
        revenue_month: string;
        charges_today: number;
        tickets_today: number;
        tickets_month: number;
        annualities_paid: number;
        annualities_paid_today: number;
        annualities_amount: string;
        debts_count: number;
        debts_amount: string;
        receipts_month: number;
        reprints_month: number;
        fiscal_year: number;
    };
    charts?: {
        revenue_by_day: Series;
        revenue_by_month: Series;
        merchants_by_business_type: Series;
        merchants_by_tianguis: Series;
        merchants_by_route: Series;
    };
};

export default function Dashboard({ indicators: i, charts }: Props) {
    const can = useCan();
    const moneyTick = (v: number) => money(v);

    return (
        <>
            <Head title="Dashboard" />
            <PageBody>
                <PageHeader
                    title="Dashboard"
                    description="Padrón de comerciantes y recaudación en vía pública"
                    actions={
                        <>
                            {can('merchants.create') && (
                                <Button variant="outline" asChild>
                                    <Link href={merchantCreate()}>
                                        Registrar comerciante
                                    </Link>
                                </Button>
                            )}
                            {can('payments.create') && (
                                <Button asChild>
                                    <Link href={chargeCreate()}>
                                        <Wallet /> Cobrar
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <section
                    aria-label="Recaudación"
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <StatCard
                        label="Recaudación del día"
                        value={money(i.revenue_today)}
                        hint={`${number(i.charges_today)} cobros`}
                        icon={Banknote}
                    />
                    <StatCard
                        label="Recaudación del mes"
                        value={money(i.revenue_month)}
                        hint={`${number(i.receipts_month)} tickets emitidos`}
                        icon={ReceiptText}
                    />
                    <StatCard
                        label="Boletos vendidos (mes)"
                        value={number(i.tickets_month)}
                        hint={`${number(i.tickets_today)} hoy`}
                        icon={Ticket}
                    />
                    <StatCard
                        label={`Anualidades cobradas ${i.fiscal_year}`}
                        value={number(i.annualities_paid)}
                        hint={money(i.annualities_amount)}
                        icon={CalendarCheck}
                    />
                </section>

                <section
                    aria-label="Padrón"
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <StatCard
                        label="Comerciantes"
                        value={number(i.merchants_total)}
                        hint={`${number(i.merchants_active)} activos · ${number(i.merchants_inactive)} inactivos`}
                        icon={Store}
                    />
                    <StatCard
                        label="Tianguistas"
                        value={number(i.tianguistas)}
                        icon={Tent}
                    />
                    <StatCard
                        label="Adeudos de anualidad"
                        value={number(i.debts_count)}
                        hint={`${money(i.debts_amount)} por recaudar`}
                        icon={AlertCircle}
                    />
                    <StatCard
                        label="Reimpresiones (mes)"
                        value={number(i.reprints_month)}
                        icon={Printer}
                    />
                </section>

                <Deferred
                    data="charts"
                    fallback={
                        <div className="grid gap-4 lg:grid-cols-2">
                            {[0, 1, 2, 3].map((k) => (
                                <Skeleton key={k} className="h-72 rounded-xl" />
                            ))}
                        </div>
                    }
                >
                    {charts && (
                        <div className="grid gap-4 lg:grid-cols-2">
                            <BarChartCard
                                title="Recaudación por día"
                                summary="Últimos 30 días, sin recibos cancelados"
                                data={charts.revenue_by_day}
                                format={moneyTick}
                            />
                            <BarChartCard
                                title="Recaudación por mes"
                                summary="Últimos 12 meses"
                                data={charts.revenue_by_month}
                                format={moneyTick}
                            />
                            <BarChartCard
                                title="Comerciantes por giro"
                                data={charts.merchants_by_business_type}
                                horizontal
                            />
                            <BarChartCard
                                title="Comerciantes por tianguis"
                                data={charts.merchants_by_tianguis}
                                horizontal
                            />
                            <BarChartCard
                                title="Comerciantes por ruta"
                                data={charts.merchants_by_route}
                                horizontal
                            />
                        </div>
                    )}
                </Deferred>
            </PageBody>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
