import { Head, Link } from '@inertiajs/react';
import { Download, MapPinned, Plus, Search } from 'lucide-react';
import { FilterSelect, toItems } from '@/components/padron/filter-select';
import { useFilters } from '@/components/padron/filters';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { Pagination } from '@/components/padron/pagination';
import { StatusBadge } from '@/components/padron/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCan } from '@/hooks/use-can';
import { cleanQuery } from '@/lib/format';
import { create, exportMethod, index, map, show } from '@/routes/merchants';
import type { Merchant, NamedItem, Option, Paginated } from '@/types/padron';

type Props = {
    merchants: Paginated<Merchant>;
    filters: Record<string, string>;
    options: {
        business_types: NamedItem[];
        tianguis: NamedItem[];
        routes: NamedItem[];
        commerce_types: Option[];
        merchant_statuses: Option[];
    };
};

export default function MerchantsIndex({
    merchants,
    filters: initial,
    options,
}: Props) {
    const can = useCan();
    const [filters, set, reset] = useFilters(
        {
            search: initial.search ?? '',
            status: initial.status ?? '',
            business_type_id: initial.business_type_id ?? '',
            tianguis_id: initial.tianguis_id ?? '',
            route_id: initial.route_id ?? '',
            commerce_type: initial.commerce_type ?? '',
        },
        index().url,
    );

    return (
        <>
            <Head title="Padrón de comerciantes" />
            <PageBody>
                <PageHeader
                    title="Padrón de comerciantes"
                    description={`${merchants.meta.total.toLocaleString('es-MX')} comerciantes registrados`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={map()}>
                                    <MapPinned /> Mapa
                                </Link>
                            </Button>
                            {can('reports.export') && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline">
                                            <Download /> Exportar
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        {(['xlsx', 'csv', 'pdf'] as const).map(
                                            (format) => (
                                                <DropdownMenuItem
                                                    key={format}
                                                    asChild
                                                >
                                                    <a
                                                        href={
                                                            exportMethod({
                                                                query: {
                                                                    ...cleanQuery(
                                                                        filters,
                                                                    ),
                                                                    format,
                                                                },
                                                            }).url
                                                        }
                                                    >
                                                        {format === 'xlsx'
                                                            ? 'Excel'
                                                            : format.toUpperCase()}
                                                    </a>
                                                </DropdownMenuItem>
                                            ),
                                        )}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                            {can('merchants.create') && (
                                <Button asChild>
                                    <Link href={create()}>
                                        <Plus /> Registrar
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <div className="relative w-full sm:w-72">
                        <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={filters.search}
                            onChange={(e) => set('search', e.target.value)}
                            placeholder="Folio, nombre o teléfono"
                            className="pl-8"
                            aria-label="Buscar"
                        />
                    </div>
                    <FilterSelect
                        value={filters.business_type_id}
                        onChange={(v) => set('business_type_id', v)}
                        items={toItems(options.business_types)}
                        placeholder="Giro"
                        allLabel="Todos los giros"
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
                        value={filters.commerce_type}
                        onChange={(v) => set('commerce_type', v)}
                        items={options.commerce_types}
                        placeholder="Tipo"
                        allLabel="Todos los tipos"
                    />
                    <FilterSelect
                        value={filters.status}
                        onChange={(v) => set('status', v)}
                        items={options.merchant_statuses}
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
                                <TableHead>Contribuyente</TableHead>
                                <TableHead>Giro</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Tianguis / Ruta</TableHead>
                                <TableHead className="text-right">
                                    Metros
                                </TableHead>
                                <TableHead>Estado</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {merchants.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        No hay comerciantes con esos filtros.
                                    </TableCell>
                                </TableRow>
                            )}
                            {merchants.data.map((m) => (
                                <TableRow key={m.id}>
                                    <TableCell className="font-mono">
                                        <Link
                                            href={show(m.id)}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {m.folio}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="max-w-72 truncate">
                                        <div className="truncate font-medium">
                                            {m.taxpayer_name}
                                        </div>
                                        {m.trade_name && (
                                            <div className="truncate text-xs text-muted-foreground">
                                                {m.trade_name}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>{m.business_type}</TableCell>
                                    <TableCell>
                                        {m.commerce_type_label}
                                    </TableCell>
                                    <TableCell className="max-w-48 truncate">
                                        {[m.tianguis, m.route]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {Number(m.meters)}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={m.status}
                                            label={m.status_label}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>

                <Pagination
                    links={merchants.meta.links}
                    from={merchants.meta.from}
                    to={merchants.meta.to}
                    total={merchants.meta.total}
                />
            </PageBody>
        </>
    );
}

MerchantsIndex.layout = {
    breadcrumbs: [{ title: 'Padrón', href: index() }],
};
