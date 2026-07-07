import L from 'leaflet';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import 'leaflet/dist/leaflet.css';
import { useEffect, useMemo, useRef } from 'react';
import {
    MapContainer,
    Marker,
    TileLayer,
    useMap,
    useMapEvents,
} from 'react-leaflet';

// Leaflet's default marker icon references bundler-incompatible asset paths;
// point it at the imported (Vite-resolved) URLs so the pin renders.
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

// Santiago, Chile — a sensible default centre when no coordinates are set yet.
const DEFAULT_CENTER: [number, number] = [-33.4489, -70.6693];
const DEFAULT_ZOOM = 12;

type Props = {
    lat: number | null;
    lng: number | null;
    onChange: (lat: number, lng: number) => void;
};

/**
 * Keeps the Leaflet view centred on the current coordinates when they change
 * from outside the map (e.g. an address search result).
 */
function RecenterView({
    lat,
    lng,
}: {
    lat: number | null;
    lng: number | null;
}) {
    const map = useMap();

    useEffect(() => {
        if (lat !== null && lng !== null) {
            map.setView([lat, lng], Math.max(map.getZoom(), 15));
        }
    }, [lat, lng, map]);

    return null;
}

/** Sets the coordinates wherever the user clicks on the map. */
function ClickHandler({
    onChange,
}: {
    onChange: (lat: number, lng: number) => void;
}) {
    useMapEvents({
        click: (event) => onChange(event.latlng.lat, event.latlng.lng),
    });

    return null;
}

/**
 * The Leaflet map surface. Isolated in its own module so it can be lazy-loaded
 * on the client only — Leaflet touches `window`/`document` at import time and
 * would otherwise crash Inertia's server-side render.
 */
export default function MapCanvas({ lat, lng, onChange }: Props) {
    const markerRef = useRef<L.Marker>(null);

    const center = useMemo<[number, number]>(
        () => (lat !== null && lng !== null ? [lat, lng] : DEFAULT_CENTER),
        [lat, lng],
    );

    const markerEventHandlers = useMemo(
        () => ({
            dragend: () => {
                const marker = markerRef.current;

                if (marker) {
                    const position = marker.getLatLng();
                    onChange(position.lat, position.lng);
                }
            },
        }),
        [onChange],
    );

    return (
        <MapContainer
            center={center}
            zoom={lat !== null && lng !== null ? 15 : DEFAULT_ZOOM}
            scrollWheelZoom
            className="size-full"
        >
            <TileLayer
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            <ClickHandler onChange={onChange} />
            <RecenterView lat={lat} lng={lng} />
            {lat !== null && lng !== null && (
                <Marker
                    position={[lat, lng]}
                    draggable
                    ref={markerRef}
                    eventHandlers={markerEventHandlers}
                />
            )}
        </MapContainer>
    );
}
