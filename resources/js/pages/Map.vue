<script setup lang="ts">
import { Head, Link, usePage, usePoll } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import RiskScore from '@/components/RiskScore.vue';
import TargetMap from '@/components/TargetMap.vue';
import { riskColor } from '@/lib/leaflet';
import { cn } from '@/lib/utils';
import { map } from '@/routes';
import {
    edit as editTarget,
    index as targetsIndex,
    show as showTarget,
} from '@/routes/targets';
import type { MapPlace } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Map', href: map() }],
    },
});

const props = defineProps<{
    places: MapPlace[];
    unplaced: MapPlace[];
    unplacedCount: number;
}>();

const isAdmin = computed(() => usePage().props.auth.user.is_admin);

const isRunning = (place: MapPlace) => (place.running_count ?? 0) > 0;
const hasLeads = (place: MapPlace) => (place.findings_count ?? 0) > 0;

type Filter = 'all' | 'running' | 'leads';

const filter = ref<Filter>('all');

const filters = computed(() => [
    { value: 'all' as const, label: 'All targets', count: props.places.length },
    {
        value: 'running' as const,
        label: 'Searches running',
        count: props.places.filter(isRunning).length,
    },
    {
        value: 'leads' as const,
        label: 'Open leads',
        count: props.places.filter(hasLeads).length,
    },
]);

// Riskiest and busiest places first for reviewers; members get the server's alphabetical order.
const visible = computed(() =>
    props.places
        .filter((place) =>
            filter.value === 'running'
                ? isRunning(place)
                : filter.value === 'leads'
                  ? hasLeads(place)
                  : true,
        )
        .sort(
            (a, b) =>
                (b.max_risk_score ?? -1) - (a.max_risk_score ?? -1) ||
                (b.running_count ?? 0) - (a.running_count ?? 0),
        ),
);

const selectedId = ref<number | null>(null);
const list = ref<HTMLElement>();

function select(id: number) {
    selectedId.value = id;
    void nextTick(() =>
        list.value
            ?.querySelector(`[data-place="${id}"]`)
            ?.scrollIntoView({ block: 'nearest' }),
    );
}

watch(visible, (places) => {
    if (!places.some((place) => place.id === selectedId.value)) {
        selectedId.value = null;
    }
});

function plural(count: number, noun: string) {
    return `${count} ${noun}${count === 1 ? '' : 's'}`;
}

function activity(place: MapPlace) {
    return [
        isRunning(place)
            ? `${plural(place.running_count!, 'search')} running`
            : null,
        hasLeads(place) ? plural(place.findings_count!, 'open lead') : null,
    ]
        .filter(Boolean)
        .join(' · ');
}

const legend = [
    { label: 'High risk', color: riskColor(75) },
    { label: 'Elevated', color: riskColor(50) },
    { label: 'Moderate', color: riskColor(25) },
    { label: 'Low', color: riskColor(0) },
    { label: 'No open leads', color: '#94a3b8' },
];

const inProgress = computed(() =>
    [...props.places, ...props.unplaced].some(isRunning),
);

const { start, stop } = usePoll(
    10000,
    { only: ['places', 'unplaced'] },
    { autoStart: false },
);

// Keep the map fresh while any search is still queued or running.
watch(inProgress, (running) => (running ? start() : stop()), {
    immediate: true,
});
</script>

<template>
    <Head title="Map" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            title="Map"
            :description="
                isAdmin
                    ? 'Where searches are running and where analyses have found open leads. Markers are sized by open leads and colored by their highest risk score.'
                    : 'Active targets that have a location.'
            "
        />

        <div
            v-if="isAdmin"
            role="group"
            aria-label="Show on map"
            class="flex flex-wrap gap-1 self-start rounded-lg bg-muted p-1 text-sm"
        >
            <button
                v-for="option in filters"
                :key="option.value"
                type="button"
                :aria-pressed="filter === option.value"
                :class="
                    cn(
                        'flex items-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition-colors',
                        filter === option.value
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground',
                    )
                "
                @click="filter = option.value"
            >
                {{ option.label }}
                <span class="text-xs text-muted-foreground tabular-nums">{{
                    option.count
                }}</span>
            </button>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="space-y-3">
                <div class="relative">
                    <TargetMap
                        :places="visible"
                        :selected-id="selectedId"
                        @select="select"
                    />
                    <p
                        v-if="!visible.length"
                        class="pointer-events-none absolute inset-x-0 top-4 z-10 mx-auto w-fit max-w-[90%] rounded-md bg-background/90 px-3 py-1.5 text-center text-sm shadow"
                    >
                        {{
                            filter === 'running'
                                ? 'No searches are running right now.'
                                : filter === 'leads'
                                  ? 'No open leads at targets on the map.'
                                  : 'No targets have a location yet.'
                        }}
                    </p>
                </div>

                <ul
                    v-if="isAdmin"
                    class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                >
                    <li class="flex items-center gap-1.5">
                        <span
                            class="size-3 rounded-full border-2 border-blue-600 bg-blue-400"
                        />
                        Search running
                    </li>
                    <li
                        v-for="item in legend"
                        :key="item.label"
                        class="flex items-center gap-1.5"
                    >
                        <span
                            class="size-3 rounded-full"
                            :style="{ backgroundColor: item.color }"
                        />
                        {{ item.label }}
                    </li>
                </ul>
            </div>

            <ul
                v-if="visible.length"
                ref="list"
                class="divide-y self-start overflow-y-auto rounded-xl border lg:max-h-[60vh]"
            >
                <li
                    v-for="place in visible"
                    :key="place.id"
                    :data-place="place.id"
                    :class="
                        cn(
                            'flex items-center gap-3 p-3',
                            place.id === selectedId && 'bg-accent/60',
                        )
                    "
                >
                    <button
                        type="button"
                        class="min-w-0 flex-1 text-left"
                        :aria-pressed="place.id === selectedId"
                        @click="select(place.id)"
                    >
                        <span class="block truncate font-medium">{{
                            place.name
                        }}</span>
                        <span
                            class="block truncate text-sm text-muted-foreground"
                        >
                            {{ place.type
                            }}{{ place.region ? ` · ${place.region}` : '' }}
                        </span>
                        <span
                            v-if="activity(place)"
                            class="block truncate text-xs text-muted-foreground"
                            >{{ activity(place) }}</span
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
        </div>

        <section v-if="unplaced.length" class="space-y-3">
            <Heading
                variant="small"
                title="Not on the map"
                description="These targets have searches or open leads but no location. Add one to show them on the map."
            />
            <ul class="divide-y rounded-xl border">
                <li
                    v-for="place in unplaced"
                    :key="place.id"
                    class="flex items-center gap-3 p-3"
                >
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="showTarget(place.id)"
                            class="block truncate font-medium hover:underline"
                        >
                            {{ place.name }}
                        </Link>
                        <div class="truncate text-xs text-muted-foreground">
                            {{ activity(place) }}
                        </div>
                    </div>
                    <Link
                        :href="editTarget(place.id)"
                        class="text-sm font-medium text-primary hover:underline"
                    >
                        Add location
                    </Link>
                </li>
            </ul>
        </section>

        <p v-if="unplacedCount" class="text-sm text-muted-foreground">
            {{ plural(unplacedCount, 'active target') }}
            {{ unplacedCount === 1 ? "doesn't" : "don't" }} have a location yet.
            <Link :href="targetsIndex()" class="text-primary hover:underline">
                Browse targets
            </Link>
        </p>
    </div>
</template>
