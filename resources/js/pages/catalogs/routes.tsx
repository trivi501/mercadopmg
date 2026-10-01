import { CatalogPage } from '@/components/padron/catalog-page';
import type {
    CatalogColumn,
    CatalogField,
} from '@/components/padron/catalog-page';
import { destroy, index, store, update } from '@/routes/catalogs/routes';
import type { Paginated } from '@/types/padron';

type Row = {
    id: number;
    name: string;
    zone: string | null;
    itinerary: string | null;
    description: string | null;
    is_active: boolean;
    merchants_count: number;
};
type Props = { items: Paginated<Row>; filters: Record<string, string> };

export default function Page({ items, filters }: Props) {
    const fields: CatalogField[] = [
        {
            name: 'name',
            label: 'Nombre',
            required: true,
            className: 'sm:col-span-1',
        },
        { name: 'zone', label: 'Zona', className: 'sm:col-span-1' },
        { name: 'itinerary', label: 'Recorrido', type: 'textarea' },
        { name: 'description', label: 'Descripción', type: 'textarea' },
    ];

    const columns: CatalogColumn<Row>[] = [
        {
            label: 'Nombre',
            render: (r) => <span className="font-medium">{r.name}</span>,
        },
        { label: 'Zona', render: (r) => r.zone ?? '—' },
        {
            label: 'Recorrido',
            render: (r) => r.itinerary ?? '—',
            className: 'max-w-80 truncate',
        },
        {
            label: 'Comerciantes',
            render: (r) => r.merchants_count,
            className: 'text-right tabular-nums',
        },
    ];

    return (
        <CatalogPage
            title="Rutas"
            description="Rutas de comercio ambulante y su recorrido."
            singular="Ruta"
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
    breadcrumbs: [{ title: 'Rutas', href: index() }],
};
