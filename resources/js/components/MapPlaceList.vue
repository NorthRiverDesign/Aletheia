<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import RiskScore from '@/components/RiskScore.vue';
import { Input } from '@/components/ui/input';
import { riskColor } from '@/lib/leaflet';
import { hasLeads, isFresh, isRunning, placeSummary } from '@/lib/mapPlaces';
import { cn } from '@/lib/utils';
import { show as showTarget } from '@/routes/targets';
import type { MapPlace } from '@/types';

const props = defineProps<{
    places: MapPlace[];
    selectedId: number | null;
    hoveredId: number | null;
    /** Reviewers get a status dot per row. */
    reviewer: boolean;
    now: Date;
}>();

const emit = defineEmits<{
    select: [id: number];
    hover: [id: number | null];
}>();

const query = defineModel<string>('query', { required: true });

const list = ref<HTMLElement>();

/** Scroll a row into view inside the list itself, never the page, so the map stays in view. */
function reveal(id: number) {
    const row = list.value?.querySelector(`[data-place="${id}"]`);

    if (list.value && row) {
        const box = list.value.getBoundingClientRect();
        const { top, bottom } = row.getBoundingClientRect();

        list.value.scrollTop +=
            top < box.top ? top - box.top : Math.max(0, bottom - box.bottom);
    }
}

defineExpose({ reveal });
</script>

<template>
    <div class="relative">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            v-model="query"
            type="search"
            class="pl-9"
            placeholder="Find a place"
            aria-label="Find a place"
        />
    </div>

    <ul
        v-if="props.places.length"
        ref="list"
        class="divide-y overflow-y-auto rounded-xl border lg:max-h-[50vh]"
    >
        <li
            v-for="place in props.places"
            :key="place.id"
            :data-place="place.id"
            :class="
                cn(
                    'flex items-center gap-3 p-3 transition-colors',
                    place.id === props.selectedId
                        ? 'bg-accent/60'
                        : place.id === props.hoveredId && 'bg-accent/30',
                )
            "
            @mouseenter="emit('hover', place.id)"
            @mouseleave="emit('hover', null)"
        >
            <span
                v-if="props.reviewer"
                :class="
                    cn(
                        'size-2.5 shrink-0 rounded-full',
                        isRunning(place) &&
                            'ring-2 ring-blue-500/40 motion-safe:animate-pulse',
                    )
                "
                :style="{
                    backgroundColor: hasLeads(place)
                        ? riskColor(place.max_risk_score ?? 0)
                        : isRunning(place)
                          ? '#2563eb'
                          : '#94a3b8',
                }"
            />
            <button
                type="button"
                class="min-w-0 flex-1 text-left"
                :aria-pressed="place.id === props.selectedId"
                @click="emit('select', place.id)"
            >
                <span class="flex items-center gap-1.5">
                    <span class="truncate font-medium">{{ place.name }}</span>
                    <span
                        v-if="isFresh(place, props.now)"
                        class="shrink-0 rounded-full bg-emerald-100 px-1.5 text-[10px] font-semibold tracking-wide text-emerald-700 uppercase dark:bg-emerald-500/15 dark:text-emerald-300"
                        >New</span
                    >
                </span>
                <span class="block truncate text-sm text-muted-foreground">
                    {{ place.type.label
                    }}{{ place.region ? ` · ${place.region}` : '' }}
                </span>
                <span
                    v-if="placeSummary(place)"
                    class="block truncate text-xs text-muted-foreground"
                    >{{ placeSummary(place) }}</span
                >
            </button>
            <RiskScore
                v-if="hasLeads(place) && place.max_risk_score !== null"
                :score="place.max_risk_score"
            />
            <Link
                :href="showTarget(place.id)"
                class="text-sm font-medium text-primary hover:underline"
            >
                Open
            </Link>
        </li>
    </ul>
</template>
