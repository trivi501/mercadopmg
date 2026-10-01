import 'leaflet/dist/leaflet.css';
import type { LatLngExpression } from 'leaflet';
import {
    CircleMarker,
    MapContainer,
    TileLayer,
    useMap,
    useMapEvents,
} from 'react-leaflet';
import { useEffect } from 'react';

/** Centro por omisión cuando aún no hay coordenadas. */
export const DEFAULT_CENTER: [number, number] = [19.4326, -99.1332];

function ClickHandler({
    onPick,
}: {
    onPick: (lat: number, lng: number) => void;
}) {
    useMapEvents({
        click: (e) =>
            onPick(
                Number(e.latlng.lat.toFixed(7)),
                Number(e.latlng.lng.toFixed(7)),
            ),
    });

    return null;
}

function Recenter({ center }: { center: LatLngExpression }) {
    const map = useMap();
    useEffect(() => {
        map.setView(center);
    }, [center, map]);

    return null;
}

/**
 * Mapa para capturar o consultar la ubicación del puesto. Clic = fijar ubicación.
 */
export function LocationPicker({
    lat,
    lng,
    onPick,
    height = 280,
}: {
    lat: number | null;
    lng: number | null;
    onPick?: (lat: number, lng: number) => void;
    height?: number;
}) {
    const has =
        lat !== null &&
        lng !== null &&
        !Number.isNaN(lat) &&
        !Number.isNaN(lng);
    const center: [number, number] = has ? [lat, lng] : DEFAULT_CENTER;

    return (
        <div className="overflow-hidden rounded-lg border" style={{ height }}>
            <MapContainer
                center={center}
                zoom={has ? 17 : 13}
                scrollWheelZoom
                className="size-full"
            >
                <TileLayer
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                />
                {has && (
                    <>
                        <CircleMarker
                            center={center}
                            radius={9}
                            pathOptions={{
                                color: '#fff',
                                weight: 2,
                                fillColor: '#2a78d6',
                                fillOpacity: 1,
                            }}
                        />
                        <Recenter center={center} />
                    </>
                )}
                {onPick && <ClickHandler onPick={onPick} />}
            </MapContainer>
        </div>
    );
}
