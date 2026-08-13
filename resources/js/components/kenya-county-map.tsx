import { usePage } from '@inertiajs/react';
import { useEffect, useId, useRef } from 'react';
import kenyaCounties from '@/data/kenya-counties.json';
import countyBoundaries from '@/data/kenya-county-boundaries-osm.json';
import { cn } from '@/lib/utils';

type CountyMapItem = {
    id: string;
    name: string;
    assessmentStatus: string;
    logoUrl?: string | null;
    mapLabel?: string;
    mapTone?: 'active' | 'warning' | 'inactive';
};

type Props<TCounty extends CountyMapItem> = {
    counties: TCounty[];
    showFullCountry: boolean;
    selectedCountyId?: string;
    onSelect: (county: TCounty) => void;
    className?: string;
};

type CountyFeature = {
    type: 'Feature';
    properties: { ADM1_EN: string };
    geometry: {
        type: 'Polygon' | 'MultiPolygon';
        coordinates: number[][][] | number[][][][];
    };
};

const defaultTileUrl = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const defaultAttribution =
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

function normalizeCountyName(name: string): string {
    return name.toLowerCase().replaceAll(/[^a-z]/g, '');
}

function statusLabel(
    county: CountyMapItem,
    copy: Record<string, string>,
): string {
    if (county.mapLabel) {
        return county.mapLabel;
    }

    return ['assessed', 'approved'].includes(county.assessmentStatus)
        ? copy.map_status_assessed
        : copy.map_status_pending;
}

function countyColour(county: CountyMapItem | undefined): string {
    if (!county || county.mapTone === 'inactive') {
        return '#9fb3aa';
    }

    if (
        county.mapTone === 'warning' ||
        (!county.mapTone &&
            !['assessed', 'approved'].includes(county.assessmentStatus))
    ) {
        return '#d79b2b';
    }

    return '#147a55';
}

function tooltipContent(
    county: CountyMapItem | undefined,
    name: string,
    copy: Record<string, string>,
) {
    const container = document.createElement('div');
    container.className = 'flex items-center gap-2';

    if (county?.logoUrl) {
        const logo = document.createElement('img');
        logo.src = county.logoUrl;
        logo.alt = '';
        logo.className = 'size-8 rounded bg-white object-contain p-0.5';
        container.append(logo);
    }

    const content = document.createElement('span');
    const title = document.createElement('strong');
    const status = document.createElement('span');
    title.className = 'block';
    status.className = 'block text-xs';
    title.textContent = name;
    status.textContent = county
        ? statusLabel(county, copy)
        : copy.map_outside_access_scope;
    content.append(title, status);
    container.append(content);

    return container;
}

export default function KenyaCountyMap<TCounty extends CountyMapItem>({
    counties,
    showFullCountry,
    selectedCountyId,
    onSelect,
    className,
}: Props<TCounty>) {
    const copy = usePage().props.localization.common;
    const containerRef = useRef<HTMLDivElement>(null);
    const mapLabelId = useId();

    useEffect(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        let disposed = false;
        let destroyMap: (() => void) | undefined;

        void import('leaflet').then((L) => {
            if (disposed || !containerRef.current) {
                return;
            }

            const countiesByName = new Map(
                counties.map((county) => [
                    normalizeCountyName(county.name),
                    county,
                ]),
            );
            const allFeatures = (
                showFullCountry ? kenyaCounties : countyBoundaries
            ).features as CountyFeature[];
            const visibleFeatures = showFullCountry
                ? allFeatures
                : allFeatures.filter((feature) =>
                      countiesByName.has(
                          normalizeCountyName(feature.properties.ADM1_EN),
                      ),
                  );
            const featureCollection = {
                type: 'FeatureCollection' as const,
                features: visibleFeatures,
            };
            const map = L.map(containerRef.current, {
                attributionControl: true,
                scrollWheelZoom: false,
                zoomControl: true,
            });
            map.attributionControl.setPrefix(
                '<span title="Integrated Devolution Management Information System">IDMIS</span> · State Department for Devolution',
            );
            const tileUrl = import.meta.env.VITE_MAP_TILE_URL || defaultTileUrl;
            const attribution =
                import.meta.env.VITE_MAP_TILE_ATTRIBUTION || defaultAttribution;

            L.tileLayer(tileUrl, {
                attribution,
                maxZoom: 19,
                crossOrigin: true,
            }).addTo(map);

            const boundaryLayer = L.geoJSON(featureCollection, {
                style: (feature) => {
                    const county = feature
                        ? countiesByName.get(
                              normalizeCountyName(
                                  feature.properties?.ADM1_EN ?? '',
                              ),
                          )
                        : undefined;
                    const selected = county?.id === selectedCountyId;

                    return {
                        color: selected ? '#12304a' : '#ffffff',
                        fillColor: countyColour(county),
                        fillOpacity: selected ? 0.62 : 0.46,
                        opacity: 0.95,
                        weight: selected ? 4 : 1.5,
                    };
                },
                onEachFeature: (feature, layer) => {
                    const name = feature.properties?.ADM1_EN ?? 'County';
                    const county = countiesByName.get(
                        normalizeCountyName(name),
                    );

                    layer.bindTooltip(tooltipContent(county, name, copy), {
                        direction: 'top',
                        sticky: true,
                    });

                    if (county) {
                        layer.on('click', () => onSelect(county));
                    }
                },
            }).addTo(map);
            const mapBounds = boundaryLayer.getBounds();

            if (mapBounds.isValid()) {
                map.fitBounds(mapBounds, {
                    padding: showFullCountry ? [18, 18] : [12, 12],
                    maxZoom: showFullCountry ? 7 : 12,
                });
                map.setMaxBounds(mapBounds.pad(showFullCountry ? 0.35 : 0.18));
            }

            destroyMap = () => map.remove();
        });

        return () => {
            disposed = true;
            destroyMap?.();
        };
    }, [copy, counties, onSelect, selectedCountyId, showFullCountry]);

    return (
        <div
            className={cn(
                'relative isolate z-0 min-h-80 w-full overflow-hidden rounded-xl border bg-muted/40',
                className,
            )}
        >
            <p id={mapLabelId} className="sr-only">
                {showFullCountry
                    ? copy.map_kenya_label
                    : copy.map_county_label.replace(
                          ':county',
                          counties[0]?.name ?? copy.your_county,
                      )}
            </p>
            <div
                ref={containerRef}
                className="h-[34rem] min-h-80 w-full"
                role="region"
                aria-labelledby={mapLabelId}
            />
            <div className="pointer-events-none absolute bottom-7 left-2 rounded-md border bg-background/90 px-2 py-1 text-[11px] text-muted-foreground shadow-sm backdrop-blur-sm">
                {copy.map_boundary_notice}
            </div>
        </div>
    );
}
