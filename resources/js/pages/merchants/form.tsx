import { Head, Link, useForm } from '@inertiajs/react';
import { Crosshair, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Field } from '@/components/padron/field';
import { FilterSelect, toItems } from '@/components/padron/filter-select';
import { LocationPicker } from '@/components/padron/location-picker';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store, update } from '@/routes/merchants';
import type { Merchant, NamedItem, Option } from '@/types/padron';

type Props = {
    merchant: { data: Merchant } | Merchant | null;
    options: {
        business_types: NamedItem[];
        tianguis: NamedItem[];
        routes: NamedItem[];
        commerce_types: Option[];
        merchant_statuses: Option[];
    };
};

function unwrap(m: Props['merchant']): Merchant | null {
    if (!m) {
        return null;
    }

    return 'data' in m ? m.data : m;
}

export default function MerchantForm({ merchant: raw, options }: Props) {
    const merchant = unwrap(raw);
    const editing = merchant !== null;
    const [locating, setLocating] = useState(false);

    const form = useForm({
        taxpayer_name: merchant?.taxpayer_name ?? '',
        trade_name: merchant?.trade_name ?? '',
        business_type_id: merchant?.business_type_id
            ? String(merchant.business_type_id)
            : '',
        business_description: merchant?.business_description ?? '',
        phone: merchant?.phone ?? '',
        address: merchant?.address ?? '',
        neighborhood: merchant?.neighborhood ?? '',
        municipality: merchant?.municipality ?? '',
        state: merchant?.state ?? '',
        location: merchant?.location ?? '',
        location_street: merchant?.location_street ?? '',
        location_number: merchant?.location_number ?? '',
        location_neighborhood: merchant?.location_neighborhood ?? '',
        location_reference: merchant?.location_reference ?? '',
        latitude: merchant?.latitude ?? '',
        longitude: merchant?.longitude ?? '',
        commerce_type: merchant?.commerce_type ?? 'tianguis',
        tianguis_id: merchant?.tianguis_id ? String(merchant.tianguis_id) : '',
        route_id: merchant?.route_id ? String(merchant.route_id) : '',
        meters: merchant?.meters ? String(Number(merchant.meters)) : '',
        meters_reason: '',
        status: merchant?.status ?? 'active',
        registered_at:
            merchant?.registered_at ?? new Date().toISOString().slice(0, 10),
        notes: merchant?.notes ?? '',
    });
    const { data, setData, errors, processing } = form;
    const metersChanged =
        editing && Number(data.meters) !== Number(merchant.meters);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const payload = (d: typeof data) =>
            Object.fromEntries(
                Object.entries(d).map(([k, v]) => [k, v === '' ? null : v]),
            );
        form.transform(payload);

        if (editing) {
            form.put(update(merchant.id).url);
        } else {
            form.post(store().url);
        }
    };

    const locateWithGps = () => {
        if (!navigator.geolocation) {
            return;
        }

        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setData((d) => ({
                    ...d,
                    latitude: pos.coords.latitude.toFixed(7),
                    longitude: pos.coords.longitude.toFixed(7),
                }));
                setLocating(false);
            },
            () => setLocating(false),
            { enableHighAccuracy: true, timeout: 15000 },
        );
    };

    const text = (
        name: keyof typeof data,
        label: string,
        props: {
            required?: boolean;
            placeholder?: string;
            className?: string;
            type?: string;
        } = {},
    ) => (
        <Field
            label={label}
            htmlFor={name}
            error={errors[name]}
            required={props.required}
            className={props.className}
        >
            <Input
                id={name}
                type={props.type ?? 'text'}
                value={data[name]}
                onChange={(e) => setData(name, e.target.value)}
                placeholder={props.placeholder}
                aria-invalid={!!errors[name]}
            />
        </Field>
    );

    const title = editing
        ? `Editar ${merchant.folio}`
        : 'Registrar comerciante';

    return (
        <>
            <Head title={title} />
            <PageBody>
                <PageHeader
                    title={title}
                    description={
                        editing
                            ? merchant.taxpayer_name
                            : 'El folio COM-###### se asigna automáticamente al guardar.'
                    }
                />

                <form onSubmit={submit} className="grid gap-4 lg:grid-cols-3">
                    <div className="grid gap-4 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Contribuyente
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                {text(
                                    'taxpayer_name',
                                    'Nombre del contribuyente',
                                    {
                                        required: true,
                                        className: 'sm:col-span-2',
                                    },
                                )}
                                {text('trade_name', 'Nombre comercial')}
                                {text('phone', 'Teléfono', { type: 'tel' })}
                                <Field
                                    label="Giro"
                                    htmlFor="business_type_id"
                                    error={errors.business_type_id}
                                    required
                                >
                                    <FilterSelect
                                        id="business_type_id"
                                        value={data.business_type_id}
                                        onChange={(v) =>
                                            setData('business_type_id', v)
                                        }
                                        items={toItems(options.business_types)}
                                        placeholder="Seleccione"
                                        allowAll={false}
                                        className="sm:w-full"
                                    />
                                </Field>
                                {text(
                                    'business_description',
                                    'Descripción del giro',
                                    { placeholder: 'Ej. Venta de ropa' },
                                )}
                                {text('address', 'Domicilio', {
                                    className: 'sm:col-span-2',
                                })}
                                {text('neighborhood', 'Colonia')}
                                {text('municipality', 'Municipio')}
                                {text('state', 'Estado')}
                                {text('registered_at', 'Fecha de alta', {
                                    type: 'date',
                                })}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Ubicación del puesto
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                {text('location', 'Dirección / ubicación', {
                                    className: 'sm:col-span-2',
                                })}
                                {text('location_street', 'Calle')}
                                {text('location_number', 'Número')}
                                {text('location_neighborhood', 'Colonia')}
                                {text('location_reference', 'Referencia')}
                                <div className="grid gap-2 sm:col-span-2">
                                    <div className="flex flex-wrap items-end gap-2">
                                        {text('latitude', 'Latitud', {
                                            className: 'w-40',
                                        })}
                                        {text('longitude', 'Longitud', {
                                            className: 'w-40',
                                        })}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={locateWithGps}
                                            disabled={locating}
                                        >
                                            {locating ? (
                                                <Spinner />
                                            ) : (
                                                <Crosshair />
                                            )}{' '}
                                            Usar mi ubicación
                                        </Button>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Haga clic en el mapa para fijar la
                                        ubicación.
                                    </p>
                                    <LocationPicker
                                        lat={
                                            data.latitude === ''
                                                ? null
                                                : Number(data.latitude)
                                        }
                                        lng={
                                            data.longitude === ''
                                                ? null
                                                : Number(data.longitude)
                                        }
                                        onPick={(lat, lng) =>
                                            setData((d) => ({
                                                ...d,
                                                latitude: String(lat),
                                                longitude: String(lng),
                                            }))
                                        }
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid content-start gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Asignación
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <Field
                                    label="Tipo de comercio"
                                    htmlFor="commerce_type"
                                    error={errors.commerce_type}
                                    required
                                >
                                    <FilterSelect
                                        id="commerce_type"
                                        value={data.commerce_type}
                                        onChange={(v) =>
                                            setData('commerce_type', v)
                                        }
                                        items={options.commerce_types}
                                        placeholder="Seleccione"
                                        allowAll={false}
                                        className="sm:w-full"
                                    />
                                </Field>
                                <Field
                                    label="Tianguis"
                                    htmlFor="tianguis_id"
                                    error={errors.tianguis_id}
                                    required={data.commerce_type === 'tianguis'}
                                >
                                    <FilterSelect
                                        id="tianguis_id"
                                        value={data.tianguis_id}
                                        onChange={(v) =>
                                            setData('tianguis_id', v)
                                        }
                                        items={toItems(options.tianguis)}
                                        placeholder="Ninguno"
                                        allLabel="Ninguno"
                                        className="sm:w-full"
                                    />
                                </Field>
                                <Field
                                    label="Ruta"
                                    htmlFor="route_id"
                                    error={errors.route_id}
                                    required={data.commerce_type === 'route'}
                                >
                                    <FilterSelect
                                        id="route_id"
                                        value={data.route_id}
                                        onChange={(v) => setData('route_id', v)}
                                        items={toItems(options.routes)}
                                        placeholder="Ninguna"
                                        allLabel="Ninguna"
                                        className="sm:w-full"
                                    />
                                </Field>
                                {text('meters', 'Metros asignados', {
                                    required: true,
                                    type: 'number',
                                })}
                                {metersChanged && (
                                    <Field
                                        label="Motivo del cambio de metros"
                                        htmlFor="meters_reason"
                                        error={errors.meters_reason}
                                        hint="Queda registrado en el histórico de mediciones."
                                    >
                                        <Input
                                            id="meters_reason"
                                            value={data.meters_reason}
                                            onChange={(e) =>
                                                setData(
                                                    'meters_reason',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                )}
                                {editing && (
                                    <Field
                                        label="Estado"
                                        htmlFor="status"
                                        error={errors.status}
                                    >
                                        <FilterSelect
                                            id="status"
                                            value={data.status}
                                            onChange={(v) =>
                                                setData(
                                                    'status',
                                                    v as typeof data.status,
                                                )
                                            }
                                            items={options.merchant_statuses}
                                            placeholder="Estado"
                                            allowAll={false}
                                            className="sm:w-full"
                                        />
                                    </Field>
                                )}
                                <Field
                                    label="Observaciones"
                                    htmlFor="notes"
                                    error={errors.notes}
                                >
                                    <Textarea
                                        id="notes"
                                        value={data.notes}
                                        onChange={(e) =>
                                            setData('notes', e.target.value)
                                        }
                                        rows={3}
                                    />
                                </Field>
                            </CardContent>
                        </Card>

                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="flex-1"
                            >
                                {processing ? <Spinner /> : <Save />} Guardar
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <Link
                                    href={editing ? show(merchant.id) : index()}
                                >
                                    Cancelar
                                </Link>
                            </Button>
                        </div>
                    </div>
                </form>
            </PageBody>
        </>
    );
}

MerchantForm.layout = {
    breadcrumbs: [{ title: 'Padrón', href: index() }],
};
