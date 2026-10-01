import { CatalogPage } from '@/components/padron/catalog-page';
import type {
    CatalogColumn,
    CatalogField,
} from '@/components/padron/catalog-page';
import { destroy, index, store, update } from '@/routes/catalogs/concepts';
import type { Paginated } from '@/types/padron';

import { money } from '@/lib/format';
import type { Option } from '@/types/padron';

type Row = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    type: string;
    type_label: string;
    amount: string;
    periodicity: string;
    periodicity_label: string;
    is_active: boolean;
};
type Props = {
    items: Paginated<Row>;
    filters: Record<string, string>;
    types: Option[];
    periodicities: Option[];
};

export default function Page({ items, filters, types, periodicities }: Props) {
    const fields: CatalogField[] = [
        {
            name: 'code',
            label: 'Clave',
            required: true,
            className: 'sm:col-span-1',
        },
        {
            name: 'amount',
            label: 'Importe',
            type: 'number',
            required: true,
            className: 'sm:col-span-1',
        },
        { name: 'name', label: 'Nombre', required: true },
        {
            name: 'type',
            label: 'Tipo',
            type: 'select',
            options: types,
            required: true,
            className: 'sm:col-span-1',
        },
        {
            name: 'periodicity',
            label: 'Periodicidad',
            type: 'select',
            options: periodicities,
            required: true,
            className: 'sm:col-span-1',
        },
        { name: 'description', label: 'Descripción', type: 'textarea' },
    ];

    const columns: CatalogColumn<Row>[] = [
        {
            label: 'Clave',
            render: (r) => <span className="font-mono">{r.code}</span>,
        },
        {
            label: 'Concepto',
            render: (r) => <span className="font-medium">{r.name}</span>,
        },
        { label: 'Tipo', render: (r) => r.type_label },
        { label: 'Periodicidad', render: (r) => r.periodicity_label },
        {
            label: 'Importe',
            render: (r) => money(r.amount),
            className: 'text-right tabular-nums',
        },
    ];

    return (
        <CatalogPage
            title="Conceptos de cobro"
            description="Importes vigentes. Cambiar un importe no modifica recibos ya emitidos."
            singular="Concepto"
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
    breadcrumbs: [{ title: 'Conceptos de cobro', href: index() }],
};
