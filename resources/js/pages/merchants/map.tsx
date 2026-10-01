import 'leaflet/dist/leaflet.css';
import { Head, Link, router } from '@inertiajs/react';
import {
    Circle,
    CircleMarker,
    MapContainer,
    Popup,
    TileLayer,
    useMapEvents,
} from 'react-leaflet';
import { useState } from 'react';
import { FilterSelect, toItems } from '@/components/padron/filter-select';
import { DEFAULT_CENTER } from '@/components/padron/location-picker';
import { PageBody, PageHeader } from '@/components/padron/page-header';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cleanQuery } from '@/lib/format';
import { index, map as mapRoute, show } from '@/routes/merchants';
import type { NamedItem } from '@/types/padron';

type Point = {
    id: number;
    folio: string;
    taxpayer_name: string;
    business_type: string;
    tianguis: string | null;
    route: string | null;
    status: string;
    latitude: number;
    longitude: number;
    distance_km: number | null;
};

type Props = {
    merchants: Point[];
    zone: { lat: number; lng: number; radius: number } | null;
    filters: { tianguis_id?: string; route_id?: string };
    options: { tianguis: NamedItem[]; routes: NamedItem[] };
};

const RADII = [0.1, 0.25, 0.5, 1, 2];

function ZonePicker({
    onPick,
}: {
    onPick: (lat: number, lng: number) => void;
}) {
    useMapEvents({ click: (e) => onPick(e.latlng.lat, e.latlng.lng) });

    return null;
}

export default function MerchantsMap({
    merchants,
    zone,
    filters,
    options,
}: Props) {
    const [radius, setRadius] = useState(zone?.radius ?? 0.5);
    const first = merchants[0];
    const center: [number, number] = zone
        ? [zone.lat, zone.lng]
        : first
          ? [first.latitude, first.longitude]
          : DEFAULT_CENTER;

    const go = (query: Record<string, unknown>) =>
        router.get(mapRoute().url, cleanQuery(query), {
            preserveState: true,
            preserveScroll: true,
        });

    return (
        <>
            <Head title="Mapa de comerciantes" />
            <PageBody>
                <PageHeader
                    title="Mapa de comerciantes"
                    description={
                        zone
                            ? `${merchants.length} comerciantes a menos de ${radius * 1000} m del punto seleccionado`
                            : `${merchants.length} comerciantes con ubicación. Haga clic en el mapa para consultar quién está en esa zona.`
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <FilterSelect
                        value={filters.tianguis_id}
                        onChange={(v) => go({ ...filters, tianguis_id: v })}
                        items={toItems(options.tianguis)}
                        placeholder="Tianguis"
                        allLabel="Todos los tianguis"
                    />
                    <FilterSelect
                        value={filters.route_id}
                        onChange={(v) => go({ ...filters, route_id: v })}
                        items={toItems(options.routes)}
                        placeholder="Ruta"
                        allLabel="Todas las rutas"
                    />
                    <FilterSelect
                        value={String(radius)}
                        onChange={(v) => {
                            setRadius(Number(v));
                            if (zone) {
                                go({ lat: zone.lat, lng: zone.lng, radius: v });
                            }
                        }}
                        items={RADII.map((r) => ({
                            value: String(r),
                            label: `Radio ${r * 1000} m`,
                        }))}
                        placeholder="Radio"
                        allowAll={false}
                    />
                    {zone && (
                        <Button variant="ghost" onClick={() => go({})}>
                            Quitar zona
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="overflow-hidden py-0 lg:col-span-2">
                        <div className="h-[60vh] min-h-96">
                            <MapContainer
                                center={center}
                                zoom={zone ? 16 : 14}
                                scrollWheelZoom
                                className="size-full"
                            >
                                <TileLayer
                                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                />
                                <ZonePicker
                                    onPick={(lat, lng) =>
                                        go({
                                            lat: lat.toFixed(6),
                                            lng: lng.toFixed(6),
                                            radius,
                                        })
                                    }
                                />
                                {zone && (
                                    <Circle
                                        center={[zone.lat, zone.lng]}
                                        radius={zone.radius * 1000}
                                        pathOptions={{
                                            color: '#2a78d6',
                                            weight: 2,
                                            fillOpacity: 0.08,
                                        }}
                                    />
                                )}
                                {merchants.map((m) => (
                                    <CircleMarker
                                        key={m.id}
                                        center={[m.latitude, m.longitude]}
                                        radius={7}
                                        pathOptions={{
                                            color: '#ffffff',
                                            weight: 2,
                                            fillColor:
                                                m.status === 'active'
                                                    ? '#2a78d6'
                                                    : '#8a8984',
                                            fillOpacity: 1,
                                        }}
                                    >
                                        <Popup>
                                            <div className="space-y-0.5 text-sm">
                                                <div className="font-mono font-semibold">
                                                    {m.folio}
                                                </div>
                                                <div>{m.taxpayer_name}</div>
                                                <div className="text-xs opacity-70">
                                                    {[
                                                        m.business_type,
                                                        m.tianguis,
                                                        m.route,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </div>
                                                <Link href={show(m.id)}>
                                                    Ver ficha
                                                </Link>
                                            </div>
                                        </Popup>
                                    </CircleMarker>
                                ))}
                            </MapContainer>
                        </div>
                    </Card>

                    <Card className="max-h-[60vh] min-h-96 overflow-y-auto py-2">
                        <ul className="divide-y text-sm">
                            {merchants.length === 0 && (
                                <li className="p-4 text-muted-foreground">
                                    Sin comerciantes en esta zona.
                                </li>
                            )}
                            {merchants.map((m) => (
                                <li key={m.id} className="px-4 py-2">
                                    <Link
                                        href={show(m.id)}
                                        className="flex justify-between gap-2 hover:underline"
                                    >
                                        <span className="font-mono">
                                            {m.folio}
                                        </span>
                                        {m.distance_km !== null && (
                                            <span className="text-muted-foreground">
                                                {Math.round(
                                                    m.distance_km * 1000,
                                                )}{' '}
                                                m
                                            </span>
                                        )}
                                    </Link>
                                    <div className="truncate">
                                        {m.taxpayer_name}
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        {m.business_type}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </Card>
                </div>
            </PageBody>
        </>
    );
}

MerchantsMap.layout = {
    breadcrumbs: [
        { title: 'Padrón', href: index() },
        { title: 'Mapa', href: mapRoute() },
    ],
};
