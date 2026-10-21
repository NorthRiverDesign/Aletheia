<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { createMap, L } from '@/lib/leaflet';

const props = defineProps<{
    latitude: number | null;
    longitude: number | null;
    errors: Partial<Record<string, string>>;
}>();

const latitude = ref<string | number>(props.latitude ?? '');
const longitude = ref<string | number>(props.longitude ?? '');

const container = ref<HTMLElement>();

// Leaflet objects stay out of Vue's reactivity; proxies break them.
let map: L.Map | null = null;
let marker: L.CircleMarker | null = null;

const point = computed<L.LatLngTuple | null>(() => {
    if (latitude.value === '' || longitude.value === '') {
        return null;
    }

    const lat = Number(latitude.value);
    const lng = Number(longitude.value);

    return Math.abs(lat) <= 90 && Math.abs(lng) <= 180 ? [lat, lng] : null;
});

function place() {
    if (!map) {
        return;
    }

    if (!point.value) {
        marker?.remove();
        marker = null;

        return;
    }

    marker ??= L.circleMarker(point.value, {
        radius: 9,
        color: '#ffffff',
        weight: 3,
        fillColor: '#2563eb',
        fillOpacity: 0.9,
    }).addTo(map);
    marker.setLatLng(point.value);
}

function clear() {
    latitude.value = '';
    longitude.value = '';
}

onMounted(() => {
    map = createMap(
        container.value!,
        point.value ? { center: point.value, zoom: 11 } : {},
    );
    map.on('click', (event: L.LeafletMouseEvent) => {
        const { lat, lng } = event.latlng.wrap();
        latitude.value = lat.toFixed(6);
        longitude.value = lng.toFixed(6);
    });
    place();
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    marker = null;
});

watch(point, place);
</script>

<template>
    <div class="grid gap-2">
        <Label for="latitude">Location</Label>
        <p class="text-sm text-muted-foreground">
            Optional. Click the map to place this target so it shows on the map,
            or enter coordinates.
        </p>
        <div
            ref="container"
            class="leaflet-crosshair isolate z-0 h-72 w-full overflow-hidden rounded-md border"
        />
        <div class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-start">
            <div class="grid gap-1">
                <Input
                    id="latitude"
                    v-model="latitude"
                    name="latitude"
                    type="number"
                    step="any"
                    min="-90"
                    max="90"
                    placeholder="Latitude"
                    aria-label="Latitude"
                />
                <InputError :message="errors.latitude" />
            </div>
            <div class="grid gap-1">
                <Input
                    id="longitude"
                    v-model="longitude"
                    name="longitude"
                    type="number"
                    step="any"
                    min="-180"
                    max="180"
                    placeholder="Longitude"
                    aria-label="Longitude"
                />
                <InputError :message="errors.longitude" />
            </div>
            <Button
                type="button"
                variant="outline"
                :disabled="latitude === '' && longitude === ''"
                @click="clear"
            >
                Clear
            </Button>
        </div>
    </div>
</template>
