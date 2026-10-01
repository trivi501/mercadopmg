import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { FilterSelect, toItems } from '@/components/padron/filter-select';
import { useFilters } from '@/components/padron/filters';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { Pagination } from '@/components/padron/pagination';
import { StatCard } from '@/components/padron/stat-card';
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
import { cleanQuery } from '@/lib/format';
import { cn } from '@/lib/utils';
import { exportMethod, index } from '@/routes/reports';
import type { NamedItem, Option, SimplePaginated } from '@/types/padron';

type Props = {
    type: string;
    title: string;
    columns: Record<string, string>;
    rows: SimplePaginated<Record<string, string | number | null>>;
    summary: Record<string, string | number>;
    filters: Record<string, string>;
    reports: { key: string; title: string }[];
    options: {
        tianguis: NamedItem[];
        routes: NamedItem[];
        users: NamedItem[];
        concepts: { id: number; code: string; name: string }[];
        merchant_statuses: Option[];
        business_types: NamedItem[];
        commerce_types: Option[];
        annuality_statuses: Option[];
    };
    canExport: boolean;
};

/** Filtros disponibles por tipo de reporte. */
const FILTERS: Record<string, string[]> = {
    merchants: [
        'search',
        'status',
        'business_type_id',
        'tianguis_id',
        'route_id',
        'commerce_type',
    ],
    revenue: [
        'date_from',
        'date_to',
        'user_id',
        'tianguis_id',
        'route_id',
        'concept_id',
    ],
    tickets: ['date_from', 'date_to', 'user_id', 'tianguis_id', 'route_id'],
    annualities: ['fiscal_year', 'status', 'tianguis_id', 'route_id'],
    reprints: ['date_from', 'date_to', 'user_id', 'search'],
};

const NUMERIC = new Set([
    'quantity',
    'unit_price',
    'total',
    'amount',
    'meters',
    'number',
]);

export default function Reports({
    type,
    title,
    columns,
    rows,
    summary,
    filters: initial,
    reports,
    options,
    canExport,
}: Props) {
    const allowed = FILTERS[type] ?? [];
    const [filters, set, reset] = useFilters(
        Object.fromEntries(allowed.map((k) => [k, initial[k] ?? ''])) as Record<
            string,
            string
        >,
        index(type).url,
    );
    const year = new Date().getFullYear();

    const control = (key: string) => {
        switch (key) {
            case 'search':
                return (
                    <Input
                        key={key}
                        value={filters.search}
                        onChange={(e) => set('search', e.target.value)}
                        placeholder="Buscar"
                        className="w-full sm:w-56"
                    />
                );
            case 'date_from':
                return (
                    <Input
                        key={key}
                        type="date"
                        value={filters.date_from}
                        onChange={(e) => set('date_from', e.target.value)}
                        className="w-40"
                        aria-label="Fecha inicial"
                    />
                );
            case 'date_to':
                return (
                    <Input
                        key={key}
                        type="date"
                        value={filters.date_to}
                        onChange={(e) => set('date_to', e.target.value)}
                        className="w-40"
                        aria-label="Fecha final"
                    />
                );
            case 'user_id':
                return (
                    <FilterSelect
                        key={key}
                        value={filters.user_id}
                        onChange={(v) => set('user_id', v)}
                        items={toItems(options.users)}
                        placeholder="Usuario"
                        allLabel="Todos los usuarios"
                    />
                );
            case 'tianguis_id':
                return (
                    <FilterSelect
                        key={key}
                        value={filters.tianguis_id}
                        onChange={(v) => set('tianguis_id', v)}
                        items={toItems(options.tianguis)}
                        placeholder="Tianguis"
                        allLabel="Todos los tianguis"
                    />
                );
            case 'route_id':
                return (
                    <FilterSelect
                        key={key}
                        value={filters.route_id}
                        onChange={(v) => set('route_id', v)}
                        items={toItems(options.routes)}
                        placeholder="Ruta"
                        allLabel="Todas las rutas"
                    />
                );
            case 'concept_id':
                return (
                    <FilterSelect
                        key={key}
                        value={filters.concept_id}
                        onChange={(v) => set('concept_id', v)}
                        items={options.concepts.map((c) => ({
                            value: c.id,
                            label: `${c.code} ${c.name}`,
                        }))}
                        placeholder="Concepto"
                        allLabel="Todos los conceptos"
                    />
                );
            case 'business_type_id':
                return (
                    <FilterSelect
                        key={key}
                        value={filters.business_type_id}
                        onChange={(v) => set('business_type_id', v)}
                        items={toItems(options.business_types)}
                        placeholder="Giro"
                        allLabel="Todos los giros"
                    />
                );
            case 'commerce_type':
                return (
                    <FilterSelect
                        key={key}
                        value={filters.commerce_type}
                        onChange={(v) => set('commerce_type', v)}
                        items={options.commerce_types}
                        placeholder="Tipo"
                        allLabel="Todos los tipos"
                    />
                );
            case 'status':
                return (
                    <FilterSelect
                        key={key}
                        value={filters.status}
                        onChange={(v) => set('status', v)}
                        items={
                            type === 'annualities'
                                ? options.annuality_statuses
                                : options.merchant_statuses
                        }
                        placeholder="Estado"
                        allLabel="Todos los estados"
                    />
                );
            case 'fiscal_year':
                return (
                    <FilterSelect
                        key={key}
                        value={filters.fiscal_year || String(year)}
                        onChange={(v) => set('fiscal_year', v)}
                        items={[
                            year + 1,
                            year,
                            year - 1,
                            year - 2,
                            year - 3,
                        ].map((y) => ({
                            value: String(y),
                            label: `Ejercicio ${y}`,
                        }))}
                        placeholder="Ejercicio"
                        allowAll={false}
                    />
                );
            default:
                return null;
        }
    };

    return (
        <>
            <Head title={title} />
            <PageBody>
                <PageHeader
                    title="Reportes"
                    description={title}
                    actions={
                        canExport &&
                        (['xlsx', 'csv', 'pdf'] as const).map((format) => (
                            <Button key={format} variant="outline" asChild>
                                <a
                                    href={
                                        exportMethod(type, {
                                            query: {
                                                ...cleanQuery(filters),
                                                format,
                                            },
                                        }).url
                                    }
                                >
                                    <Download />{' '}
                                    {format === 'xlsx'
                                        ? 'Excel'
                                        : format.toUpperCase()}
                                </a>
                            </Button>
                        ))
                    }
                />

                <nav
                    className="flex flex-wrap gap-1 border-b"
                    aria-label="Tipo de reporte"
                >
                    {reports.map((r) => (
                        <Link
                            key={r.key}
                            href={index(r.key)}
                            className={cn(
                                '-mb-px border-b-2 px-3 py-2 text-sm',
                                r.key === type
                                    ? 'border-primary font-medium'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {r.title
                                .replace(/^Reporte de /, '')
                                .replace(/^\w/, (c) => c.toUpperCase())}
                        </Link>
                    ))}
                </nav>

                <div className="flex flex-wrap items-center gap-2">
                    {allowed.map(control)}
                    <Button variant="ghost" onClick={reset}>
                        Limpiar
                    </Button>
                </div>

                {Object.keys(summary).length > 0 && (
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {Object.entries(summary).map(([label, value]) => (
                            <StatCard
                                key={label}
                                label={label}
                                value={
                                    typeof value === 'number'
                                        ? value.toLocaleString('es-MX')
                                        : value
                                }
                            />
                        ))}
                    </div>
                )}

                <Card className="py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {Object.entries(columns).map(([key, label]) => (
                                    <TableHead
                                        key={key}
                                        className={
                                            NUMERIC.has(key)
                                                ? 'text-right'
                                                : undefined
                                        }
                                    >
                                        {label}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={Object.keys(columns).length}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        Sin registros para los filtros
                                        seleccionados.
                                    </TableCell>
                                </TableRow>
                            )}
                            {rows.data.map((row, i) => (
                                <TableRow key={i}>
                                    {Object.keys(columns).map((key) => (
                                        <TableCell
                                            key={key}
                                            className={cn(
                                                'max-w-64 truncate',
                                                NUMERIC.has(key) &&
                                                    'text-right tabular-nums',
                                            )}
                                        >
                                            {row[key] ?? '—'}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>

                <Pagination
                    links={rows.links}
                    from={rows.from}
                    to={rows.to}
                    total={rows.total}
                />
            </PageBody>
        </>
    );
}

Reports.layout = {
    breadcrumbs: [{ title: 'Reportes', href: index() }],
};
