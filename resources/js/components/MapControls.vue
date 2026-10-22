<script setup lang="ts">
import {
    CalendarClock,
    Crosshair,
    Flame,
    LocateFixed,
    Maximize2,
    Minimize2,
    Radar,
    Waypoints,
} from '@lucide/vue';
import { computed } from 'vue';
import type { MapLayers } from '@/components/TargetMap.vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    layers: MapLayers;
    follow: boolean;
    timeline: boolean;
    expanded: boolean;
    /** Reviewers get the layer, follow and timeline toggles; everyone gets fit and full screen. */
    reviewer: boolean;
}>();

const emit = defineEmits<{
    layer: [key: keyof MapLayers];
    follow: [];
    timeline: [];
    fit: [];
    expand: [];
}>();

const toggles = computed(() => [
    { key: 'areas' as const, label: 'Search areas', icon: Radar },
    { key: 'links' as const, label: 'Connections', icon: Waypoints },
    { key: 'heat' as const, label: 'Risk heat', icon: Flame },
]);

const button =
    'grid size-9 place-items-center rounded-lg border shadow-sm transition-colors';
const idle = 'bg-background/95 text-muted-foreground hover:text-foreground';
</script>

<template>
    <div
        class="absolute top-4 right-4 z-10 flex flex-col gap-1.5"
        role="toolbar"
        aria-label="Map controls"
    >
        <template v-if="props.reviewer">
            <button
                v-for="toggle in toggles"
                :key="toggle.key"
                type="button"
                :title="toggle.label"
                :aria-label="toggle.label"
                :aria-pressed="props.layers[toggle.key]"
                :class="
                    cn(
                        button,
                        props.layers[toggle.key]
                            ? 'border-primary bg-primary text-primary-foreground'
                            : idle,
                    )
                "
                @click="emit('layer', toggle.key)"
            >
                <component :is="toggle.icon" class="size-4" />
            </button>
            <button
                type="button"
                title="Follow live: jump to places as things happen"
                aria-label="Follow live"
                :aria-pressed="props.follow"
                :class="
                    cn(
                        button,
                        props.follow
                            ? 'border-emerald-600 bg-emerald-600 text-white'
                            : idle,
                    )
                "
                @click="emit('follow')"
            >
                <Crosshair class="size-4" />
            </button>
            <button
                type="button"
                title="Timeline: replay the last 30 days"
                aria-label="Timeline"
                :aria-pressed="props.timeline"
                :class="
                    cn(
                        button,
                        props.timeline
                            ? 'border-primary bg-primary text-primary-foreground'
                            : idle,
                    )
                "
                @click="emit('timeline')"
            >
                <CalendarClock class="size-4" />
            </button>
            <span class="my-0.5 h-px bg-border" />
        </template>
        <button
            type="button"
            title="Show all places"
            aria-label="Show all places"
            :class="cn(button, idle)"
            @click="emit('fit')"
        >
            <LocateFixed class="size-4" />
        </button>
        <button
            type="button"
            :title="props.expanded ? 'Exit full screen' : 'Full screen'"
            :aria-label="props.expanded ? 'Exit full screen' : 'Full screen'"
            :class="cn(button, idle)"
            @click="emit('expand')"
        >
            <component
                :is="props.expanded ? Minimize2 : Maximize2"
                class="size-4"
            />
        </button>
    </div>
</template>
