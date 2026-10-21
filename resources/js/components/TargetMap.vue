<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    createMap,
    defaultCenter,
    defaultZoom,
    L,
    riskColor,
} from '@/lib/leaflet';
import type { MapPlace } from '@/types';

const props = defineProps<{
    places: MapPlace[];
    selectedId: number | null;
}>();

const emit = defineEmits<{
    select: [id: number];
}>();

const container = ref<HTMLElement>();

// Leaflet objects stay out of Vue's reactivity; proxies break them.
let map: L.Map | null = null;
let layer: L.LayerGroup | null = null;
const markers = new Map<number, { marker: L.CircleMarker; place: MapPlace }>();

const located = computed(() =>
    props.places.filter(
        (place) => place.latitude !== null && place.longitude !== null,
    ),
);

function style(place: MapPlace): L.CircleMarkerOptions {
    const findings = place.findings_count ?? 0;
    const running = (place.running_count ?? 0) > 0;
    const selected = place.id === props.selectedId;

    return {
        radius:
            findings > 0
                ? 8 + Math.min(Math.sqrt(findings) * 2, 10)
                : running
                  ? 9
                  : 6,
        color: selected ? '#facc15' : running ? '#2563eb' : '#ffffff',
        weight: selected || running ? 4 : 2,
        fillColor:
            findings > 0
                ? riskColor(place.max_risk_score ?? 0)
                : running
                  ? '#60a5fa'
                  : '#94a3b8',
        fillOpacity: 0.85,
        className: running ? 'motion-safe:animate-pulse' : '',
    };
}

function render() {
    if (!map || !layer) {
        return;
    }

    layer.clearLayers();
    markers.clear();

    for (const place of located.value) {
        // Target names come from members, so the tooltip is text, never HTML.
        const label = document.createElement('span');
        label.textContent = place.name;

        const marker = L.circleMarker(
            [place.latitude!, place.longitude!],
            style(place),
        )
            .bindTooltip(label, { direction: 'top', offset: [0, -8] })
            .on('click', () => emit('select', place.id))
            .addTo(layer);

        markers.set(place.id, { marker, place });
    }

    markers.get(props.selectedId ?? 0)?.marker.bringToFront();
}

function fit() {
    if (!map) {
        return;
    }

    if (!located.value.length) {
        map.setView(defaultCenter, defaultZoom);

        return;
    }

    map.fitBounds(
        L.latLngBounds(
            located.value.map((place): L.LatLngTuple => [
                place.latitude!,
                place.longitude!,
            ]),
        ),
        { padding: [40, 40], maxZoom: 10 },
    );
}

onMounted(() => {
    map = createMap(container.value!);
    layer = L.layerGroup().addTo(map);
    render();
    fit();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    layer = null;
    markers.clear();
});

// Redraw when counts change, but only reframe when the set of places does, so polling doesn't move the view.
watch(() => props.places, render);
watch(() => located.value.map((place) => place.id).join(','), fit);

watch(
    () => props.selectedId,
    (id) => {
        markers.forEach(({ marker, place }) => marker.setStyle(style(place)));

        const marker = id === null ? undefined : markers.get(id)?.marker;

        if (map && marker) {
            marker.bringToFront();

            if (!map.getBounds().contains(marker.getLatLng())) {
                map.panTo(marker.getLatLng());
            }
        }
    },
);
</script>

<template>
    <div
        ref="container"
        class="isolate z-0 h-[60vh] min-h-80 w-full overflow-hidden rounded-xl border"
    />
</template>
