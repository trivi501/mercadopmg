import { CatalogPage } from '@/components/padron/catalog-page';
import type {
    CatalogColumn,
    CatalogField,
} from '@/components/padron/catalog-page';
import { destroy, index, store, update } from '@/routes/catalogs/tianguis';
import type { Paginated } from '@/types/padron';

type Row = {
    id: number;
    name: string;
    description: string | null;
    location: string | null;
    neighborhood: string | null;
    municipality: string | null;
    operating_day: string | null;
    schedule: string | null;
    latitude: string | null;
    longitude: string | null;
    is_active: boolean;
    merchants_count: number;
};
type Props = {
    items: Paginated<Row>;
    filters: Record<string, string>;
    days: string[];
};

export default function Page({ items, filters, days }: Props) {
    const fields: CatalogField[] = [
        { name: 'name', label: 'Nombre', required: true },
        {
            name: 'operating_day',
            label: 'Día de operación',
            type: 'select',
            options: days.map((d) => ({
                value: d,
                label: d.charAt(0).toUpperCase() + d.slice(1),
            })),
            className: 'sm:col-span-1',
        },
        { name: 'schedule', label: 'Horario', className: 'sm:col-span-1' },
        { name: 'location', label: 'Ubicación' },
        { name: 'neighborhood', label: 'Colonia', className: 'sm:col-span-1' },
        {
            name: 'municipality',
            label: 'Municipio',
            className: 'sm:col-span-1',
        },
        {
            name: 'latitude',
            label: 'Latitud',
            type: 'number',
            className: 'sm:col-span-1',
        },
        {
            name: 'longitude',
            label: 'Longitud',
            type: 'number',
            className: 'sm:col-span-1',
        },
        { name: 'description', label: 'Descripción', type: 'textarea' },
    ];

    const columns: CatalogColumn<Row>[] = [
        {
            label: 'Nombre',
            render: (r) => <span className="font-medium">{r.name}</span>,
        },
        {
            label: 'Día',
            render: (r) => r.operating_day ?? '—',
            className: 'capitalize',
        },
        { label: 'Horario', render: (r) => r.schedule ?? '—' },
        {
            label: 'Ubicación',
            render: (r) =>
                [r.location, r.neighborhood].filter(Boolean).join(', ') || '—',
            className: 'max-w-72 truncate',
        },
        {
            label: 'Comerciantes',
            render: (r) => r.merchants_count,
            className: 'text-right tabular-nums',
        },
    ];

    return (
        <CatalogPage
            title="Tianguis"
            description="Tianguis y plazas con su día y horario de operación."
            singular="Tianguis"
            items={items}
            filters={filters}
            fields={fields}
            columns={columns}
            urls={{
                index: index().url,
                store: store().url,
                update: (id) => update(id).url,
                destroy: (id) => destroy(id).url,
            }}
        />
    );
}

Page.layout = {
    breadcrumbs: [{ title: 'Tianguis', href: index() }],
};
