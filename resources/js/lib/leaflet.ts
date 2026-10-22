import 'leaflet/dist/leaflet.css';
import * as L from 'leaflet';

export { L };

/** The contiguous United States, framed when there is nothing else to show. */
export const defaultCenter: L.LatLngTuple = [39.8, -98.6];
export const defaultZoom = 4;

/** Create a Leaflet map with OpenStreetMap tiles. */
export function createMap(element: HTMLElement, options: L.MapOptions = {}) {
    const map = L.map(element, {
        center: defaultCenter,
        zoom: defaultZoom,
        ...options,
    });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    // Leaflet only tracks window resizes; the sidebar collapsing resizes the map too.
    const observer = new ResizeObserver(() => map.invalidateSize());
    observer.observe(element);
    map.on('unload', () => observer.disconnect());

    return map;
}

/** Marker colors for a risk score, in the same bands as RiskScore. */
export function riskColor(score: number) {
    if (score >= 75) {
        return '#dc2626';
    }

    if (score >= 50) {
        return '#f97316';
    }

    if (score >= 25) {
        return '#f59e0b';
    }

    return '#64748b';
}

/**
 * A rough search area, in metres, for a target type. Targets carry a point, not a boundary,
 * so this only suggests the ground a search covers.
 */
export function searchRadius(type: string) {
    switch (type) {
        case 'state':
            return 160_000;
        case 'county':
            return 30_000;
        case 'city':
            return 10_000;
        default:
            return 4_000;
    }
}

/** Points along a gentle arc between two places, so connections that share a place fan out. */
export function arc(from: L.LatLng, to: L.LatLng, bend = 0.18, segments = 32) {
    const control = L.latLng(
        (from.lat + to.lat) / 2 + (to.lng - from.lng) * bend,
        (from.lng + to.lng) / 2 - (to.lat - from.lat) * bend,
    );

    return Array.from({ length: segments + 1 }, (_, step) => {
        const t = step / segments;
        const u = 1 - t;

        return L.latLng(
            u * u * from.lat + 2 * u * t * control.lat + t * t * to.lat,
            u * u * from.lng + 2 * u * t * control.lng + t * t * to.lng,
        );
    });
}

/** A stable color per vector, spread around the hue wheel by the golden angle. */
export function vectorColor(id: number) {
    return `hsl(${Math.round((id * 137.508) % 360)}, 75%, 48%)`;
}
