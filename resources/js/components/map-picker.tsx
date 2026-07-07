import { Search } from 'lucide-react';
import {
    Component,
    lazy,
    Suspense,
    useState,
    useSyncExternalStore,
} from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';

// The Leaflet surface is loaded on the client only — see map-canvas.tsx.
const MapCanvas = lazy(() => import('@/components/map-canvas'));

type Props = {
    lat: number | null;
    lng: number | null;
    onChange: (lat: number, lng: number) => void;
    /** Prefilled into the address search box (usually the premise address). */
    addressHint?: string;
};

type NominatimResult = { lat: string; lon: string };

// `useSyncExternalStore` reports `false` on the server and `true` on the
// client without a state-in-effect, so the map only renders after hydration.
const subscribe = () => () => {};

/**
 * Reusable interactive map picker. Clicking the map or dragging the marker
 * updates the coordinates; searching an address geocodes it via OpenStreetMap's
 * free Nominatim service. Wrapped in an error boundary by the parent so the
 * form's manual lat/lng inputs remain usable if the map fails to load.
 */
export function MapPicker({ lat, lng, onChange, addressHint }: Props) {
    const { t } = useTranslations();
    const [query, setQuery] = useState('');
    const [searching, setSearching] = useState(false);
    const [notFound, setNotFound] = useState(false);
    const isClient = useSyncExternalStore(
        subscribe,
        () => true,
        () => false,
    );

    async function searchAddress() {
        const term = (query || addressHint || '').trim();

        if (term === '') {
            return;
        }

        setSearching(true);
        setNotFound(false);

        try {
            const url = new URL('https://nominatim.openstreetmap.org/search');
            url.searchParams.set('format', 'json');
            url.searchParams.set('limit', '1');
            url.searchParams.set('q', term);

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
            });
            const results = (await response.json()) as NominatimResult[];

            if (results.length === 0) {
                setNotFound(true);

                return;
            }

            onChange(Number(results[0].lat), Number(results[0].lon));
        } catch {
            setNotFound(true);
        } finally {
            setSearching(false);
        }
    }

    return (
        <div className="grid gap-2">
            <div className="flex gap-2">
                <Input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            void searchAddress();
                        }
                    }}
                    placeholder={t('ui.premises.map.search_placeholder')}
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void searchAddress()}
                    disabled={searching}
                >
                    <Search className="size-4" />
                    {t('ui.premises.map.search')}
                </Button>
            </div>

            {notFound && (
                <p className="text-xs text-destructive">
                    {t('ui.premises.map.not_found')}
                </p>
            )}

            <div className="h-80 overflow-hidden rounded-lg border">
                {isClient && (
                    <Suspense
                        fallback={
                            <div className="flex size-full items-center justify-center text-sm text-muted-foreground">
                                {t('ui.premises.map.loading')}
                            </div>
                        }
                    >
                        <MapCanvas lat={lat} lng={lng} onChange={onChange} />
                    </Suspense>
                )}
            </div>

            <p className="text-xs text-muted-foreground">
                {t('ui.premises.map.hint')}
            </p>
        </div>
    );
}

type BoundaryProps = { fallback: ReactNode; children: ReactNode };
type BoundaryState = { hasError: boolean };

/**
 * Isolates map rendering failures so the surrounding form (including the manual
 * lat/lng inputs) keeps working when Leaflet or the tile server is unavailable.
 */
export class MapErrorBoundary extends Component<BoundaryProps, BoundaryState> {
    state: BoundaryState = { hasError: false };

    static getDerivedStateFromError(): BoundaryState {
        return { hasError: true };
    }

    render(): ReactNode {
        if (this.state.hasError) {
            return this.props.fallback;
        }

        return this.props.children;
    }
}
