import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import { Field } from '@/components/padron/field';
import { FilterSelect } from '@/components/padron/filter-select';
import { useFilters } from '@/components/padron/filters';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { Pagination } from '@/components/padron/pagination';
import { StatusBadge } from '@/components/padron/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import type { Paginated } from '@/types/padron';

export type CatalogField = {
    name: string;
    label: string;
    type?: 'text' | 'textarea' | 'number' | 'select';
    options?: { value: string; label: string }[];
    required?: boolean;
    className?: string;
};

export type CatalogColumn<T> = {
    label: string;
    render: (item: T) => ReactNode;
    className?: string;
};

type Item = { id: number; is_active: boolean };

type Urls = {
    index: string;
    store: string;
    update: (id: number) => string;
    destroy: (id: number) => string;
};

/**
 * Pantalla genérica de catálogo: listado con búsqueda y alta/edición en diálogo.
 */
export function CatalogPage<T extends Item>({
    title,
    description,
    singular,
    items,
    filters: initial,
    fields,
    columns,
    urls,
}: {
    title: string;
    description: string;
    singular: string;
    items: Paginated<T>;
    filters: Record<string, string>;
    fields: CatalogField[];
    columns: CatalogColumn<T>[];
    urls: Urls;
}) {
    const [filters, set] = useFilters(
        { search: initial.search ?? '', active: initial.active ?? '' },
        urls.index,
    );
    const [editing, setEditing] = useState<T | null>(null);
    const [open, setOpen] = useState(false);

    const blank = Object.fromEntries(fields.map((f) => [f.name, ''])) as Record<
        string,
        string
    >;
    const form = useForm<Record<string, string | boolean>>({
        ...blank,
        is_active: true,
    });

    const openFor = (item: T | null) => {
        setEditing(item);
        form.clearErrors();
        form.setData(
            item
                ? {
                      ...Object.fromEntries(
                          fields.map((f) => {
                              const value = (item as Record<string, unknown>)[
                                  f.name
                              ];

                              return [
                                  f.name,
                                  value === null || value === undefined
                                      ? ''
                                      : String(
                                            value as string | number | boolean,
                                        ),
                              ];
                          }),
                      ),
                      is_active: item.is_active,
                  }
                : { ...blank, is_active: true },
        );
        setOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((d) =>
            Object.fromEntries(
                Object.entries(d).map(([k, v]) => [k, v === '' ? null : v]),
            ),
        );
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.put(urls.update(editing.id), options);
        } else {
            form.post(urls.store, options);
        }
    };

    const remove = (item: T) => {
        if (
            confirm(
                `¿Eliminar ${singular.toLowerCase()}? Si está en uso, desactívelo en su lugar.`,
            )
        ) {
            router.delete(urls.destroy(item.id), { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={title} />
            <PageBody>
                <PageHeader
                    title={title}
                    description={description}
                    actions={
                        <Button onClick={() => openFor(null)}>
                            <Plus /> Nuevo
                        </Button>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <div className="relative w-full sm:w-72">
                        <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={filters.search}
                            onChange={(e) => set('search', e.target.value)}
                            placeholder="Buscar"
                            className="pl-8"
                        />
                    </div>
                    <FilterSelect
                        value={filters.active}
                        onChange={(v) => set('active', v)}
                        items={[
                            { value: '1', label: 'Activos' },
                            { value: '0', label: 'Inactivos' },
                        ]}
                        placeholder="Estado"
                    />
                </div>

                <Card className="py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                {columns.map((c) => (
                                    <TableHead
                                        key={c.label}
                                        className={c.className}
                                    >
                                        {c.label}
                                    </TableHead>
                                ))}
                                <TableHead>Estado</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={columns.length + 2}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        Sin registros.
                                    </TableCell>
                                </TableRow>
                            )}
                            {items.data.map((item) => (
                                <TableRow key={item.id}>
                                    {columns.map((c) => (
                                        <TableCell
                                            key={c.label}
                                            className={c.className}
                                        >
                                            {c.render(item)}
                                        </TableCell>
                                    ))}
                                    <TableCell>
                                        <StatusBadge
                                            status={
                                                item.is_active
                                                    ? 'active'
                                                    : 'inactive'
                                            }
                                            label={
                                                item.is_active
                                                    ? 'Activo'
                                                    : 'Inactivo'
                                            }
                                        />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            onClick={() => openFor(item)}
                                            aria-label="Editar"
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            onClick={() => remove(item)}
                                            aria-label="Eliminar"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>

                <Pagination
                    links={items.meta.links}
                    from={items.meta.from}
                    to={items.meta.to}
                    total={items.meta.total}
                />
            </PageBody>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? `Editar ${singular.toLowerCase()}`
                                : `Nuevo ${singular.toLowerCase()}`}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        onSubmit={submit}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        {fields.map((f) => (
                            <Field
                                key={f.name}
                                label={f.label}
                                htmlFor={f.name}
                                error={form.errors[f.name]}
                                required={f.required}
                                className={f.className ?? 'sm:col-span-2'}
                            >
                                {f.type === 'textarea' ? (
                                    <Textarea
                                        id={f.name}
                                        value={String(form.data[f.name] ?? '')}
                                        onChange={(e) =>
                                            form.setData(f.name, e.target.value)
                                        }
                                        rows={3}
                                    />
                                ) : f.type === 'select' ? (
                                    <FilterSelect
                                        id={f.name}
                                        value={String(form.data[f.name] ?? '')}
                                        onChange={(v) =>
                                            form.setData(f.name, v)
                                        }
                                        items={f.options ?? []}
                                        placeholder="Seleccione"
                                        allowAll={!f.required}
                                        allLabel="—"
                                        className="sm:w-full"
                                    />
                                ) : (
                                    <Input
                                        id={f.name}
                                        type={f.type ?? 'text'}
                                        step={
                                            f.type === 'number'
                                                ? '0.01'
                                                : undefined
                                        }
                                        value={String(form.data[f.name] ?? '')}
                                        onChange={(e) =>
                                            form.setData(f.name, e.target.value)
                                        }
                                    />
                                )}
                            </Field>
                        ))}
                        <label className="flex items-center gap-2 sm:col-span-2">
                            <Checkbox
                                checked={Boolean(form.data.is_active)}
                                onCheckedChange={(v) =>
                                    form.setData('is_active', v === true)
                                }
                            />
                            <Label>Activo</Label>
                        </label>
                        <DialogFooter className="sm:col-span-2">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={() => setOpen(false)}
                            >
                                Cerrar
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Guardar
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
