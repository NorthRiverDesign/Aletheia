<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    arc,
    createMap,
    defaultCenter,
    defaultZoom,
    L,
    riskColor,
    searchRadius,
    vectorColor,
} from '@/lib/leaflet';
import { isFresh, isRunning, plural } from '@/lib/mapPlaces';
import type { MapLink, MapPlace } from '@/types';

export type MapLayers = {
    areas: boolean;
    links: boolean;
    heat: boolean;
};

const props = defineProps<{
    places: MapPlace[];
    links: MapLink[];
    selectedId: number | null;
    hoveredId?: number | null;
    /** Narrow connections to one vector's searches and leads. */
    vectorId?: number | null;
    /** While true, a click on the map picks a spot instead of clearing the selection. */
    placing?: boolean;
    layers: MapLayers;
}>();

const emit = defineEmits<{
    select: [id: number | null];
    hover: [id: number | null];
    place: [latitude: number, longitude: number];
}>();

const container = ref<HTMLElement>();

// Leaflet objects stay out of Vue's reactivity; proxies break them.
let map: L.Map | null = null;
const groups: Record<
    'pins' | 'areas' | 'heat' | 'links' | 'pings',
    L.LayerGroup | null
> = {
    pins: null,
    areas: null,
    heat: null,
    links: null,
    pings: null,
};
const pins = new Map<number, { marker: L.Marker; signature: string }>();
/** The cluster overlay key each clustered place is drawn in. */
const clusterOf = new Map<number, string>();
const overlays = new Map<string, { layer: L.Layer; signature: string }>();
const timers = new Set<ReturnType<typeof setTimeout>>();

const located = computed(() =>
    props.places.filter(
        (place) => place.latitude !== null && place.longitude !== null,
    ),
);

const PIN_PATH = 'M15 38.5S2 24.6 2 14.5a13 13 0 0 1 26 0c0 10.1-13 24-13 24z';

const latLng = (place: MapPlace) => L.latLng(place.latitude!, place.longitude!);

const findings = (place: MapPlace) => place.findings_count ?? 0;
const fresh = (place: MapPlace) => isFresh(place, new Date());
const searching = isRunning;
const scanning = (place: MapPlace) =>
    place.searches?.some((search) => search.status === 'running') ?? false;

function pinColor(place: MapPlace) {
    if (place.findings_count === null) {
        return '#2563eb';
    }

    return findings(place) > 0
        ? riskColor(place.max_risk_score ?? 0)
        : '#94a3b8';
}

function badges(element: HTMLElement, places: MapPlace[]) {
    if (places.some(searching)) {
        const live = document.createElement('span');
        live.className = 'map-pin-live';
        element.append(live);
    }

    if (places.some(fresh)) {
        const recent = document.createElement('span');
        recent.className = 'map-pin-new';
        element.append(recent);
    }
}

function pinIcon(place: MapPlace, selected: boolean, offset: number) {
    const pin = document.createElement('div');
    pin.className = selected ? 'map-pin map-pin-selected' : 'map-pin';
    pin.style.setProperty('--pin', pinColor(place));
    pin.innerHTML = `<svg viewBox="0 0 30 40" aria-hidden="true"><path d="${PIN_PATH}"/></svg>`;

    const mark = document.createElement('span');

    // Reviewers see open leads on the pin; members, who can't, see community votes.
    const count =
        place.findings_count === null ? place.votes_count : findings(place);

    if (count > 0) {
        mark.className = 'map-pin-count';
        mark.textContent = count > 99 ? '99+' : String(count);
    } else {
        mark.className = 'map-pin-dot';
    }

    pin.append(mark);
    badges(pin, [place]);

    // Shown beside the pin once zoomed in to street level (see `map-near`).
    const label = document.createElement('span');
    label.className = 'map-pin-label';
    label.textContent = place.name;
    pin.append(label);

    return L.divIcon({
        html: pin,
        className: 'map-pin-icon',
        iconSize: [30, 40],
        iconAnchor: [15 - offset, 39],
        tooltipAnchor: [0, -38],
    });
}

// Target names come from members, so tooltips are text nodes, never HTML.
function tooltip(...lines: (string | null)[]) {
    const content = document.createElement('div');

    lines.filter(Boolean).forEach((line, index) => {
        const row = document.createElement('div');
        row.textContent = line;

        if (index === 0) {
            row.style.fontWeight = '600';
        }

        content.append(row);
    });

    return content;
}

function pinTooltip(place: MapPlace) {
    const activity = [
        searching(place)
            ? `${plural(place.running_count!, 'search')} in progress`
            : null,
        findings(place) > 0
            ? `${plural(findings(place), 'open lead')}, top risk ${place.max_risk_score}`
            : null,
    ]
        .filter(Boolean)
        .join(' · ');

    return tooltip(
        place.name,
        [place.type.label, place.region].filter(Boolean).join(' · '),
        activity || null,
        fresh(place) ? 'New lead in the last 24 hours' : null,
        place.findings_count === null
            ? plural(place.votes_count, 'community vote')
            : null,
    );
}

/** Below this zoom, pins closer than CLUSTER_RADIUS pixels merge into one bubble. */
const CLUSTER_MAX_ZOOM = 13;
const CLUSTER_RADIUS = 36;

/**
 * Group places that would overlap at the current zoom. The selected place always stands alone, and
 * places left on the same spot (from CLUSTER_MAX_ZOOM up) fan out sideways so each can be picked.
 */
function layout() {
    const singles: { place: MapPlace; offset: number }[] = [];
    const groups: MapPlace[][] = [];

    if (!map) {
        return { singles, groups };
    }

    const zoom = map.getZoom();
    const point = new Map(
        located.value.map((place) => [
            place.id,
            map!.project(latLng(place), zoom),
        ]),
    );
    // Riskiest and busiest first, so they anchor their groups.
    const order = [...located.value].sort(
        (a, b) =>
            (b.max_risk_score ?? -1) - (a.max_risk_score ?? -1) ||
            (b.running_count ?? 0) - (a.running_count ?? 0) ||
            a.id - b.id,
    );
    const taken = new Set<number>();
    const clustering = zoom < CLUSTER_MAX_ZOOM;
    const radius = clustering ? CLUSTER_RADIUS : 4;

    for (const place of order) {
        if (taken.has(place.id)) {
            continue;
        }

        taken.add(place.id);

        const anchor = point.get(place.id)!;
        const near = order.filter(
            (other) =>
                !taken.has(other.id) &&
                (!clustering ||
                    (other.id !== props.selectedId &&
                        place.id !== props.selectedId)) &&
                anchor.distanceTo(point.get(other.id)!) < radius,
        );

        near.forEach((other) => taken.add(other.id));

        if (!near.length) {
            singles.push({ place, offset: 0 });
        } else if (clustering) {
            groups.push([place, ...near]);
        } else {
            [place, ...near].forEach((member, index) =>
                singles.push({
                    place: member,
                    offset: (index - near.length / 2) * 26,
                }),
            );
        }
    }

    return { singles, groups };
}

/** Zoom in far enough to pull a cluster apart, never stopping short of declustering. */
function expand(members: MapPlace[]) {
    if (!map) {
        return;
    }

    const bounds = L.latLngBounds(members.map(latLng));
    const zoom = Math.min(
        Math.max(
            map.getBoundsZoom(bounds, false, L.point(80, 80)),
            map.getZoom() + 2,
        ),
        CLUSTER_MAX_ZOOM,
    );

    map.flyTo(bounds.getCenter(), zoom, {
        animate: !reducedMotion(),
        duration: 0.6,
    });
}

function clusterOverlay(members: MapPlace[]): Overlay {
    const leads = members.reduce((total, place) => total + findings(place), 0);
    const top = Math.max(...members.map((place) => place.max_risk_score ?? -1));
    const color =
        members[0].findings_count === null
            ? '#2563eb'
            : leads > 0
              ? riskColor(top)
              : '#94a3b8';
    const center = L.latLngBounds(members.map(latLng)).getCenter();
    const names = members.map((place) => place.name);
    const size = Math.round(34 + Math.min(members.length, 12));

    return {
        key: `cluster-${members
            .map((place) => place.id)
            .sort((a, b) => a - b)
            .join('-')}`,
        group: 'pins',
        signature: [
            color,
            leads,
            members.some(searching),
            members.some(fresh),
            center.lat,
            center.lng,
            ...names,
        ].join('|'),
        make: () => {
            const bubble = document.createElement('div');
            bubble.className = 'map-cluster';
            bubble.style.setProperty('--pin', color);
            bubble.textContent = String(members.length);
            badges(bubble, members);

            return (
                L.marker(center, {
                    icon: L.divIcon({
                        html: bubble,
                        className: 'map-cluster-icon',
                        iconSize: [size, size],
                        tooltipAnchor: [0, -size / 2],
                    }),
                    zIndexOffset: 5000 + top * 10,
                    riseOnHover: true,
                })
                    .bindTooltip(
                        tooltip(
                            `${members.length} places here`,
                            names.slice(0, 5).join(', ') +
                                (names.length > 5 ? ', …' : ''),
                            leads > 0
                                ? `${plural(leads, 'open lead')}, top risk ${top}`
                                : null,
                            'Select to zoom in',
                        ),
                        { direction: 'top' },
                    )
                    // While placing, a tap on a cluster places at its center rather than zooming.
                    .on('click', () =>
                        props.placing
                            ? emit('place', center.lat, center.lng)
                            : expand(members),
                    )
                    .on('add', (event) =>
                        (event.target as L.Marker)
                            .getElement()
                            ?.setAttribute(
                                'aria-label',
                                `${members.length} places: ${names.join(', ')}`,
                            ),
                    )
            );
        },
    };
}

function syncPins(singles: { place: MapPlace; offset: number }[]) {
    const seen = new Set<number>();

    for (const { place, offset } of singles) {
        const selected = place.id === props.selectedId;
        const signature = [
            offset,
            fresh(place),
            place.latitude,
            place.longitude,
            pinColor(place),
            findings(place),
            place.votes_count,
            searching(place),
            selected,
            place.name,
            place.region,
            place.max_risk_score,
            place.running_count,
        ].join('|');
        const zIndexOffset = selected
            ? 10000
            : (place.max_risk_score ?? 0) * 10 + (searching(place) ? 500 : 0);
        const existing = pins.get(place.id);

        seen.add(place.id);

        if (existing?.signature === signature) {
            continue;
        }

        if (existing) {
            existing.marker
                .setLatLng(latLng(place))
                .setIcon(pinIcon(place, selected, offset))
                .setZIndexOffset(zIndexOffset)
                .setTooltipContent(pinTooltip(place));
            existing.marker
                .getElement()
                ?.setAttribute('aria-label', place.name);
            existing.signature = signature;

            continue;
        }

        const marker = L.marker(latLng(place), {
            icon: pinIcon(place, selected, offset),
            zIndexOffset,
            riseOnHover: true,
        })
            .bindTooltip(pinTooltip(place), { direction: 'top' })
            // While placing, a tap on a pin places the new target at the same spot.
            .on('click', () =>
                props.placing
                    ? emit(
                          'place',
                          marker.getLatLng().lat,
                          marker.getLatLng().lng,
                      )
                    : emit('select', place.id),
            )
            .on('mouseover', () => emit('hover', place.id))
            .on('mouseout', () => emit('hover', null))
            .addTo(groups.pins!);

        marker.getElement()?.setAttribute('aria-label', place.name);

        pins.set(place.id, { marker, signature });
    }

    pins.forEach(({ marker }, id) => {
        if (!seen.has(id)) {
            marker.remove();
            pins.delete(id);
        }
    });
}

type Overlay = {
    key: string;
    group: 'pins' | 'areas' | 'heat' | 'links';
    signature: string;
    make: () => L.Layer;
};

function areaOverlays(): Overlay[] {
    if (!props.layers.areas) {
        return [];
    }

    return located.value.filter(searching).flatMap((place): Overlay[] => {
        const running = scanning(place);
        const status = running ? 'running' : 'queued';

        return [
            {
                key: `area-${place.id}`,
                group: 'areas',
                signature: `${status}|${place.type.value}|${place.latitude}|${place.longitude}`,
                make: () =>
                    L.circle(latLng(place), {
                        pane: 'mapAreas',
                        radius: searchRadius(place.type.value),
                        className: `map-area map-area-${status}`,
                        color: '#2563eb',
                        weight: 1.5,
                        dashArray: running ? '6 8' : '3 6',
                        fillColor: '#3b82f6',
                        fillOpacity: running ? 0.08 : 0.04,
                        interactive: false,
                    }),
            },
            {
                key: `radar-${place.id}`,
                group: 'areas',
                signature: `${status}|${place.latitude}|${place.longitude}`,
                make: () => {
                    const size = running ? 112 : 64;
                    const radar = document.createElement('div');
                    radar.className = running
                        ? 'map-radar'
                        : 'map-radar map-radar-queued';

                    return L.marker(latLng(place), {
                        pane: 'mapRadar',
                        icon: L.divIcon({
                            html: radar,
                            className: '',
                            iconSize: [size, size],
                        }),
                        interactive: false,
                        keyboard: false,
                    });
                },
            },
        ];
    });
}

function heatOverlays(): Overlay[] {
    if (!props.layers.heat) {
        return [];
    }

    return located.value
        .filter((place) => findings(place) > 0)
        .map((place): Overlay => {
            const size = Math.round(
                80 + Math.min(Math.sqrt(findings(place)) * 36, 140),
            );
            const color = riskColor(place.max_risk_score ?? 0);

            return {
                key: `heat-${place.id}`,
                group: 'heat',
                signature: `${size}|${color}|${place.latitude}|${place.longitude}`,
                make: () => {
                    const heat = document.createElement('div');
                    heat.className = 'map-heat';
                    heat.style.setProperty('--heat', color);

                    return L.marker(latLng(place), {
                        pane: 'mapHeat',
                        icon: L.divIcon({
                            html: heat,
                            className: '',
                            iconSize: [size, size],
                        }),
                        interactive: false,
                        keyboard: false,
                    });
                },
            };
        });
}

function linkOverlay(
    key: string,
    from: MapPlace,
    to: MapPlace,
    options: L.PolylineOptions,
    lines: string[],
): Overlay {
    const selected = props.selectedId;
    const state =
        selected === null
            ? ''
            : from.id === selected || to.id === selected
              ? 'map-link-active'
              : 'map-link-dim';

    return {
        key,
        group: 'links',
        signature: [
            state,
            from.latitude,
            from.longitude,
            to.latitude,
            to.longitude,
            options.color,
            options.weight,
            ...lines,
        ].join('|'),
        make: () =>
            L.polyline(arc(latLng(from), latLng(to)), {
                pane: 'mapLinks',
                bubblingMouseEvents: false,
                lineCap: 'round',
                ...options,
                className: `map-link ${options.className ?? ''} ${state}`,
            })
                .bindTooltip(tooltip(...lines), { sticky: true })
                // Lines don't pass clicks on to the map, so a tap on one while placing places here.
                .on('click', (event: L.LeafletMouseEvent) => {
                    if (props.placing) {
                        const { lat, lng } = event.latlng.wrap();

                        emit('place', lat, lng);
                    }
                }),
    };
}

function linkOverlays(): Overlay[] {
    if (!props.layers.links) {
        return [];
    }

    const byId = new Map(located.value.map((place) => [place.id, place]));
    const overlays: Overlay[] = [];

    // Places whose open leads name the same people or organizations.
    for (const link of props.links) {
        const from = byId.get(link.source);
        const to = byId.get(link.target);

        if (!from || !to) {
            continue;
        }

        overlays.push(
            linkOverlay(
                `names-${link.source}-${link.target}`,
                from,
                to,
                {
                    color: '#a855f7',
                    weight: 2 + Math.min(link.count, 4),
                    opacity: 0.8,
                    className: 'map-link-names',
                },
                [
                    `${from.name} ↔ ${to.name}`,
                    `${plural(link.count, 'shared name')}: ${link.names.join(', ')}${link.count > link.names.length ? ', …' : ''}`,
                ],
            ),
        );
    }

    // The same vector being searched at several places, chained in the order the searches were queued.
    const sweeps = new Map<
        number,
        { title: string; stops: { place: MapPlace; order: number }[] }
    >();

    for (const place of located.value) {
        for (const search of place.searches ?? []) {
            if (
                !search.vector ||
                (props.vectorId != null && search.vector.id !== props.vectorId)
            ) {
                continue;
            }

            const sweep = sweeps.get(search.vector.id) ?? {
                title: search.vector.title,
                stops: [],
            };

            if (!sweep.stops.some((stop) => stop.place.id === place.id)) {
                sweep.stops.push({ place, order: search.id });
            }

            sweeps.set(search.vector.id, sweep);
        }
    }

    sweeps.forEach(({ title, stops }, vectorId) => {
        stops.sort((a, b) => a.order - b.order);

        for (let index = 1; index < stops.length; index++) {
            const from = stops[index - 1].place;
            const to = stops[index].place;

            overlays.push(
                linkOverlay(
                    `sweep-${vectorId}-${from.id}-${to.id}`,
                    from,
                    to,
                    {
                        color: vectorColor(vectorId),
                        weight: 3,
                        opacity: 0.9,
                        dashArray: '1 9',
                        className: 'map-link-sweep',
                    },
                    [
                        `Searching both for: ${title}`,
                        `${from.name} → ${to.name}`,
                    ],
                ),
            );
        }
    });

    // For the selected place, others with open leads for the same vectors.
    const selected = byId.get(props.selectedId ?? 0);

    if (selected?.patterns?.length) {
        const mine = new Map(
            selected.patterns.map((vector) => [vector.id, vector.title]),
        );

        for (const place of located.value) {
            const shared = (place.patterns ?? [])
                .filter(
                    (vector) =>
                        place.id !== selected.id &&
                        mine.has(vector.id) &&
                        (props.vectorId == null ||
                            vector.id === props.vectorId),
                )
                .map((vector) => vector.title);

            if (shared.length) {
                overlays.push(
                    linkOverlay(
                        `pattern-${selected.id}-${place.id}`,
                        selected,
                        place,
                        {
                            color: '#64748b',
                            weight: 2,
                            opacity: 0.85,
                            dashArray: '4 6',
                            className: 'map-link-pattern',
                        },
                        [
                            `${selected.name} ↔ ${place.name}`,
                            `Open leads for: ${shared.join(', ')}`,
                        ],
                    ),
                );
            }
        }
    }

    return overlays;
}

// Add, replace and remove overlays by key, so unchanged ones keep their animations and hover state.
function syncOverlays(clusters: Overlay[]) {
    const wanted = [
        ...clusters,
        ...areaOverlays(),
        ...heatOverlays(),
        ...linkOverlays(),
    ];
    const keys = new Set(wanted.map((overlay) => overlay.key));

    overlays.forEach(({ layer }, key) => {
        if (!keys.has(key)) {
            layer.remove();
            overlays.delete(key);
        }
    });

    for (const overlay of wanted) {
        const existing = overlays.get(overlay.key);

        if (existing?.signature === overlay.signature) {
            continue;
        }

        existing?.layer.remove();
        overlays.set(overlay.key, {
            layer: overlay.make().addTo(groups[overlay.group]!),
            signature: overlay.signature,
        });
    }
}

function sync() {
    if (!map) {
        return;
    }

    const { singles, groups: clustered } = layout();
    const clusters = clustered.map(clusterOverlay);

    clusterOf.clear();
    clustered.forEach((members, index) =>
        members.forEach((place) =>
            clusterOf.set(place.id, clusters[index].key),
        ),
    );

    syncPins(singles);
    syncOverlays(clusters);
    markHover();
}

let hovered: HTMLElement | null = null;

// Leaflet rewrites an icon's class on setIcon, so the hover mark is reapplied after every sync.
function markHover() {
    hovered?.classList.remove('map-hover');
    hovered = null;

    const id = props.hoveredId ?? null;

    if (id === null) {
        return;
    }

    const cluster = clusterOf.get(id);
    const layer = cluster ? overlays.get(cluster)?.layer : pins.get(id)?.marker;

    hovered = (layer as L.Marker | undefined)?.getElement() ?? null;
    hovered?.classList.add('map-hover');
}

function fit() {
    if (!map) {
        return;
    }

    if (!located.value.length) {
        map.setView(defaultCenter, defaultZoom);

        return;
    }

    const padding = map.getSize().x < 480 ? 24 : 48;

    map.fitBounds(L.latLngBounds(located.value.map(latLng)), {
        padding: [padding, padding],
        maxZoom: 10,
    });
}

const reducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Fly to a place, zooming in if the map is showing the whole country. */
function focus(id: number) {
    const place = located.value.find((place) => place.id === id);

    if (map && place) {
        map.flyTo(latLng(place), Math.max(map.getZoom(), 7), {
            animate: !reducedMotion(),
            duration: 0.8,
        });
    }
}

/** Flash a ring at a place where something just happened. */
function ping(id: number, color: string) {
    const place = located.value.find((place) => place.id === id);

    if (!place || !groups.pings) {
        return;
    }

    const ring = document.createElement('div');
    ring.className = 'map-ping';
    ring.style.setProperty('--ping', color);

    const marker = L.marker(latLng(place), {
        pane: 'mapPings',
        icon: L.divIcon({ html: ring, className: '', iconSize: [96, 96] }),
        interactive: false,
        keyboard: false,
    }).addTo(groups.pings);

    const timer = setTimeout(() => {
        marker.remove();
        timers.delete(timer);
    }, 3400);

    timers.add(timer);
}

defineExpose({ fit, focus, ping });

onMounted(() => {
    map = createMap(container.value!);

    // Lowest first: heat glows, search areas, connections, then pins (600) and pings above them.
    const panes: [string, number, boolean][] = [
        ['mapHeat', 350, false],
        ['mapAreas', 380, false],
        ['mapLinks', 420, true],
        ['mapRadar', 450, false],
        ['mapPings', 640, false],
    ];

    for (const [name, zIndex, interactive] of panes) {
        const pane = map.createPane(name);
        pane.style.zIndex = String(zIndex);

        if (!interactive) {
            pane.style.pointerEvents = 'none';
        }
    }

    groups.heat = L.layerGroup().addTo(map);
    groups.areas = L.layerGroup().addTo(map);
    groups.links = L.layerGroup().addTo(map);
    groups.pins = L.layerGroup().addTo(map);
    groups.pings = L.layerGroup().addTo(map);

    map.on('click', (event: L.LeafletMouseEvent) => {
        if (props.placing) {
            const { lat, lng } = event.latlng.wrap();

            emit('place', lat, lng);
        } else {
            emit('select', null);
        }
    });

    // Shrink pins and glows when zoomed out to the whole country, where they crowd each other.
    // Name pins once zoomed in far enough for the labels to fit.
    const crowd = () => {
        container.value?.classList.toggle('map-far', map!.getZoom() < 4);
        container.value?.classList.toggle('map-near', map!.getZoom() >= 9);
    };
    map.on('zoomend', crowd);
    crowd();

    // Pixel distances only change with zoom, so that is when clusters regroup.
    map.on('zoomend', sync);

    sync();
    fit();
});

onBeforeUnmount(() => {
    timers.forEach((timer) => clearTimeout(timer));
    timers.clear();
    map?.remove();
    map = null;
    pins.clear();
    overlays.clear();
    clusterOf.clear();
    hovered = null;
});

watch(
    () => [
        props.places,
        props.links,
        props.selectedId,
        props.layers.areas,
        props.layers.links,
        props.layers.heat,
        props.vectorId,
    ],
    sync,
);

watch(() => props.hoveredId, markHover);

watch(
    () => props.placing,
    (placing) =>
        container.value?.classList.toggle(
            'leaflet-crosshair',
            placing === true,
        ),
);

// Only reframe when the set of places changes, so polling doesn't move the view.
watch(() => located.value.map((place) => place.id).join(','), fit);

watch(
    () => props.selectedId,
    (id) => {
        const marker = id === null ? undefined : pins.get(id)?.marker;

        if (map && marker && !map.getBounds().contains(marker.getLatLng())) {
            map.panTo(marker.getLatLng(), { animate: !reducedMotion() });
        }
    },
);
</script>

<template>
    <div
        ref="container"
        class="isolate z-0 h-full min-h-80 w-full overflow-hidden rounded-xl border"
    />
</template>
