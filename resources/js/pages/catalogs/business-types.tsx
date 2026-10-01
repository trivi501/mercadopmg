import { CatalogPage } from '@/components/padron/catalog-page';
import type {
    CatalogColumn,
    CatalogField,
} from '@/components/padron/catalog-page';
import {
    destroy,
    index,
    store,
    update,
} from '@/routes/catalogs/business-types';
import type { Paginated } from '@/types/padron';

type Row = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    merchants_count: number;
};
type Props = { items: Paginated<Row>; filters: Record<string, string> };

export default function Page({ items, filters }: Props) {
    const fields: CatalogField[] = [
        { name: 'name', label: 'Nombre', required: true },
        { name: 'description', label: 'Descripción', type: 'textarea' },
    ];

    const columns: CatalogColumn<Row>[] = [
        {
            label: 'Nombre',
            render: (r) => <span className="font-medium">{r.name}</span>,
        },
        {
            label: 'Descripción',
            render: (r) => r.description ?? '—',
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
            title="Giros comerciales"
            description="Catálogo de giros para clasificar a los comerciantes."
            singular="Giro"
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
    breadcrumbs: [{ title: 'Giros comerciales', href: index() }],
};
