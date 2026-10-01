import { Head, router, useForm } from '@inertiajs/react';
import { KeyRound, Pencil, Plus, Search } from 'lucide-react';
import type { FormEvent } from 'react';
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
import { index, revokeTokens, store, update } from '@/routes/users';
import type { Option, Paginated } from '@/types/padron';

type Row = {
    id: number;
    name: string;
    username: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    role: string | null;
    role_label: string | null;
    last_login_at: string | null;
    tokens_count: number;
};

type Props = {
    users: Paginated<Row>;
    filters: Record<string, string>;
    options: { roles: Option[] };
};

const blank = {
    name: '',
    username: '',
    email: '',
    phone: '',
    role: 'collector',
    is_active: true,
    password: '',
    password_confirmation: '',
};

export default function Users({ users, filters: initial, options }: Props) {
    const [filters, set] = useFilters(
        { search: initial.search ?? '', role: initial.role ?? '' },
        index().url,
    );
    const [editing, setEditing] = useState<Row | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm(blank);

    const openFor = (user: Row | null) => {
        setEditing(user);
        form.clearErrors();
        form.setData(
            user
                ? {
                      ...blank,
                      name: user.name,
                      username: user.username,
                      email: user.email,
                      phone: user.phone ?? '',
                      role: user.role ?? 'collector',
                      is_active: user.is_active,
                  }
                : blank,
        );
        setOpen(true);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.put(update(editing.id).url, options);
        } else {
            form.post(store().url, options);
        }
    };

    return (
        <>
            <Head title="Usuarios" />
            <PageBody>
                <PageHeader
                    title="Usuarios"
                    description="Administradores, recaudadores, inspectores y capturistas."
                    actions={
                        <Button onClick={() => openFor(null)}>
                            <Plus /> Nuevo usuario
                        </Button>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <div className="relative w-full sm:w-72">
                        <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={filters.search}
                            onChange={(e) => set('search', e.target.value)}
                            placeholder="Nombre, usuario o correo"
                            className="pl-8"
                        />
                    </div>
                    <FilterSelect
                        value={filters.role}
                        onChange={(v) => set('role', v)}
                        items={options.roles}
                        placeholder="Rol"
                        allLabel="Todos los roles"
                    />
                </div>

                <Card className="py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Usuario</TableHead>
                                <TableHead>Rol</TableHead>
                                <TableHead>Último acceso</TableHead>
                                <TableHead>Sesiones móviles</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.map((u) => (
                                <TableRow key={u.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {u.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {u.email}
                                        </div>
                                    </TableCell>
                                    <TableCell className="font-mono">
                                        {u.username}
                                    </TableCell>
                                    <TableCell>{u.role_label ?? '—'}</TableCell>
                                    <TableCell>
                                        {u.last_login_at
                                            ? new Date(
                                                  u.last_login_at,
                                              ).toLocaleString('es-MX')
                                            : 'Nunca'}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {u.tokens_count}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={
                                                u.is_active
                                                    ? 'active'
                                                    : 'inactive'
                                            }
                                            label={
                                                u.is_active
                                                    ? 'Activo'
                                                    : 'Inactivo'
                                            }
                                        />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {u.tokens_count > 0 && (
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                aria-label="Revocar sesiones móviles"
                                                title="Revocar sesiones móviles"
                                                onClick={() =>
                                                    confirm(
                                                        `¿Cerrar las ${u.tokens_count} sesiones móviles de ${u.name}?`,
                                                    ) &&
                                                    router.delete(
                                                        revokeTokens(u.id).url,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <KeyRound />
                                            </Button>
                                        )}
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            onClick={() => openFor(u)}
                                            aria-label="Editar"
                                        >
                                            <Pencil />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>

                <Pagination
                    links={users.meta.links}
                    from={users.meta.from}
                    to={users.meta.to}
                    total={users.meta.total}
                />
            </PageBody>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? `Editar ${editing.name}`
                                : 'Nuevo usuario'}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        onSubmit={submit}
                        className="grid gap-4 sm:grid-cols-2"
                    >
                        <Field
                            label="Nombre"
                            htmlFor="name"
                            error={form.errors.name}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Usuario"
                            htmlFor="username"
                            error={form.errors.username}
                            required
                        >
                            <Input
                                id="username"
                                value={form.data.username}
                                onChange={(e) =>
                                    form.setData('username', e.target.value)
                                }
                                autoComplete="off"
                            />
                        </Field>
                        <Field
                            label="Teléfono"
                            htmlFor="phone"
                            error={form.errors.phone}
                        >
                            <Input
                                id="phone"
                                value={form.data.phone}
                                onChange={(e) =>
                                    form.setData('phone', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Correo electrónico"
                            htmlFor="email"
                            error={form.errors.email}
                            required
                            className="sm:col-span-2"
                        >
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Rol"
                            htmlFor="role"
                            error={form.errors.role}
                            required
                            className="sm:col-span-2"
                        >
                            <FilterSelect
                                id="role"
                                value={form.data.role}
                                onChange={(v) => form.setData('role', v)}
                                items={options.roles}
                                placeholder="Rol"
                                allowAll={false}
                                className="sm:w-full"
                            />
                        </Field>
                        <Field
                            label={editing ? 'Nueva contraseña' : 'Contraseña'}
                            htmlFor="password"
                            error={form.errors.password}
                            required={!editing}
                            hint={
                                editing
                                    ? 'Déjela vacía para conservar la actual.'
                                    : undefined
                            }
                        >
                            <Input
                                id="password"
                                type="password"
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData('password', e.target.value)
                                }
                                autoComplete="new-password"
                            />
                        </Field>
                        <Field
                            label="Confirmar contraseña"
                            htmlFor="password_confirmation"
                        >
                            <Input
                                id="password_confirmation"
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(e) =>
                                    form.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                autoComplete="new-password"
                            />
                        </Field>
                        <label className="flex items-center gap-2 sm:col-span-2">
                            <Checkbox
                                checked={form.data.is_active}
                                onCheckedChange={(v) =>
                                    form.setData('is_active', v === true)
                                }
                            />
                            <Label>
                                Activo (al desactivarlo se cierran sus sesiones
                                móviles)
                            </Label>
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

Users.layout = {
    breadcrumbs: [{ title: 'Usuarios', href: index() }],
};
