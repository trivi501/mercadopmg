import { Form, Head } from '@inertiajs/react';
import { Field } from '@/components/padron/field';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { general } from '@/routes/settings';
import { update } from '@/routes/settings/general';

type Props = {
    settings: Record<
        string,
        { key: string; value: string | null; description: string | null }
    >;
};

const FIELDS: { key: string; label: string; required?: boolean }[] = [
    { key: 'municipality_name', label: 'Nombre del municipio', required: true },
    { key: 'municipality_state', label: 'Estado' },
    { key: 'treasury_name', label: 'Área recaudadora' },
    { key: 'ticket_footer', label: 'Leyenda al pie del ticket' },
];

export default function GeneralSettings({ settings }: Props) {
    return (
        <>
            <Head title="Configuración" />
            <PageBody>
                <PageHeader
                    title="Configuración"
                    description="Datos que aparecen en tickets, recibos y reportes."
                />
                <Card className="max-w-2xl">
                    <CardContent>
                        <Form
                            {...update.form()}
                            options={{ preserveScroll: true }}
                            className="grid gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    {FIELDS.map((f) => (
                                        <Field
                                            key={f.key}
                                            label={f.label}
                                            htmlFor={f.key}
                                            error={errors[f.key]}
                                            hint={
                                                settings[f.key]?.description ??
                                                undefined
                                            }
                                            required={f.required}
                                        >
                                            <Input
                                                id={f.key}
                                                name={f.key}
                                                defaultValue={
                                                    settings[f.key]?.value ?? ''
                                                }
                                                required={f.required}
                                            />
                                        </Field>
                                    ))}
                                    <div>
                                        <Button disabled={processing}>
                                            Guardar
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </PageBody>
        </>
    );
}

GeneralSettings.layout = {
    breadcrumbs: [{ title: 'Configuración', href: general() }],
};
