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
