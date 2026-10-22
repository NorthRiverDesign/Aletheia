<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { MapPin, X } from '@lucide/vue';
import {
    useDocumentVisibility,
    useEventListener,
    useIntervalFn,
    useLocalStorage,
    useNow,
    useOnline,
    useScrollLock,
} from '@vueuse/core';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import MapActivityFeed from '@/components/MapActivityFeed.vue';
import MapControls from '@/components/MapControls.vue';
import MapFilterCards from '@/components/MapFilterCards.vue';
import MapLegend from '@/components/MapLegend.vue';
import MapLiveStatus from '@/components/MapLiveStatus.vue';
import MapPlaceDetail from '@/components/MapPlaceDetail.vue';
import MapPlaceList from '@/components/MapPlaceList.vue';
import MapReplayBar from '@/components/MapReplayBar.vue';
import MapTicker from '@/components/MapTicker.vue';
import MapTimeline from '@/components/MapTimeline.vue';
import RiskScore from '@/components/RiskScore.vue';
import TargetMap from '@/components/TargetMap.vue';
import type { MapLayers } from '@/components/TargetMap.vue';
import { NativeSelect } from '@/components/ui/native-select';
import { useMapReplay } from '@/composables/useMapReplay';
import { riskColor, vectorColor } from '@/lib/leaflet';
import { hasLeads, isRunning, placeSummary, plural } from '@/lib/mapPlaces';
import { cn, timeAgo } from '@/lib/utils';
import { map } from '@/routes';
import {
    edit as editTarget,
    index as targetsIndex,
    location as targetLocation,
    show as showTarget,
} from '@/routes/targets';
import type {
    MapEvent,
    MapLink,
    MapPlace,
    MapHistory,
    PickerOption,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Map', href: map() }],
    },
});

const props = defineProps<{
    places: MapPlace[];
    unplaced: MapPlace[];
    unplacedCount: number;
    links: MapLink[];
    activity: MapEvent[];
    vectors: PickerOption[];
    /** Only loaded once the timeline is opened; null for members. */
    timeline?: MapHistory | null;
}>();

const isAdmin = computed(() => usePage().props.auth.user.is_admin);

const isHighRisk = (place: MapPlace) =>
    hasLeads(place) && (place.max_risk_score ?? 0) >= 75;
const sum = (places: MapPlace[], count: (place: MapPlace) => number) =>
    places.reduce((total, place) => total + count(place), 0);

const now = useNow({ interval: 10000 });
const mapView = ref<InstanceType<typeof TargetMap>>();

// Replay: the timeline's history drives the pins and cards instead of the live counts.
const {
    at: replayAt,
    playing,
    active: replaying,
    range: timelineRange,
    toggle: toggleReplay,
    live: goLive,
    seek,
    asOf,
} = useMapReplay({
    history: () => props.timeline ?? undefined,
    now,
    flash: (event) =>
        mapView.value?.ping(
            event.target_id,
            event.kind === 'lead'
                ? riskColor(event.risk_score ?? 0)
                : '#2563eb',
        ),
});

/** The places as they are now, or as they were at the replay time. */
const basePlaces = computed(() =>
    replaying.value ? asOf(props.places) : props.places,
);

type Filter = 'all' | 'running' | 'leads' | 'high';

const filter = ref<Filter>('all');
const query = ref('');

const filters = computed(() => {
    const running = basePlaces.value.filter(isRunning);
    const leads = basePlaces.value.filter(hasLeads);
    const high = basePlaces.value.filter(isHighRisk);

    return [
        {
            value: 'all' as const,
            label: 'On the map',
            count: basePlaces.value.length,
            detail: 'targets with a location',
            tone: 'bg-slate-400',
        },
        {
            value: 'running' as const,
            label: 'Searches running',
            count: sum(running, (place) => place.running_count ?? 0),
            detail: `at ${plural(running.length, 'place')}`,
            tone: 'bg-blue-600',
        },
        {
            value: 'leads' as const,
            label: 'Open leads',
            count: sum(leads, (place) => place.findings_count ?? 0),
            detail: `at ${plural(leads.length, 'place')}`,
            tone: 'bg-orange-500',
        },
        {
            value: 'high' as const,
            label: 'High risk',
            count: high.length,
            detail:
                high.length === 1 ? 'place scored 75+' : 'places scored 75+',
            tone: 'bg-red-600',
        },
    ];
});

const tests: Record<Filter, (place: MapPlace) => boolean> = {
    all: () => true,
    running: isRunning,
    leads: hasLeads,
    high: isHighRisk,
};

/** The vector the map is narrowed to, if any. */
const lens = ref<number | null>(null);

const vectorsAt = (place: MapPlace) =>
    new Map([
        ...(place.patterns ?? []).map(
            (vector) => [vector.id, vector.title] as const,
        ),
        ...(place.searches ?? []).flatMap((search) =>
            search.vector
                ? [[search.vector.id, search.vector.title] as const]
                : [],
        ),
    ]);

// Vectors being searched or with open leads somewhere on the map, most widespread first.
const lenses = computed(() => {
    const found = new Map<
        number,
        { id: number; title: string; places: number }
    >();

    for (const place of props.places) {
        vectorsAt(place).forEach((title, id) => {
            const entry = found.get(id) ?? { id, title, places: 0 };
            entry.places++;
            found.set(id, entry);
        });
    }

    // Keep the chosen vector listed after its last search ends, so the lens doesn't jump.
    if (lens.value !== null && !found.has(lens.value)) {
        const title = props.vectors.find(
            (vector) => vector.id === lens.value,
        )?.label;

        found.set(lens.value, {
            id: lens.value,
            title: title ?? 'Chosen vector',
            places: 0,
        });
    }

    return [...found.values()].sort(
        (a, b) => b.places - a.places || a.title.localeCompare(b.title),
    );
});

const inLens = (place: MapPlace) =>
    lens.value === null || vectorsAt(place).has(lens.value);

// Riskiest and busiest places first for reviewers; most-voted first for members.
// Which places show, and in what order, follows the live data so a replay doesn't reshuffle or
// re-frame the map on every tick; during a replay each place then shows its counts at the cursor.
const visible = computed(() => {
    const needle = query.value.trim().toLowerCase();
    const ordered = props.places
        .filter(tests[filter.value])
        .filter(inLens)
        .filter(
            (place) =>
                !needle ||
                [place.name, place.region, place.type.label].some((text) =>
                    text?.toLowerCase().includes(needle),
                ),
        )
        .sort(
            (a, b) =>
                (b.max_risk_score ?? -1) - (a.max_risk_score ?? -1) ||
                (b.running_count ?? 0) - (a.running_count ?? 0) ||
                b.votes_count - a.votes_count,
        );

    if (!replaying.value) {
        return ordered;
    }

    const atCursor = new Map(
        basePlaces.value.map((place) => [place.id, place]),
    );

    return ordered.map((place) => atCursor.get(place.id) ?? place);
});

const placeIds = computed(() => new Set(props.places.map((place) => place.id)));

const selectedId = ref<number | null>(null);
// Live data for the details panel; the map card follows the replay.
const selected = computed(
    () => props.places.find((place) => place.id === selectedId.value) ?? null,
);
const selectedShown = computed(
    () =>
        basePlaces.value.find((place) => place.id === selectedId.value) ?? null,
);
const placeList = ref<InstanceType<typeof MapPlaceList>>();
const hoveredId = ref<number | null>(null);

/** A target from "Not on the map" waiting for a spot to be picked on the map. */
const placing = ref<MapPlace | null>(null);

function startPlacing(place: MapPlace) {
    placing.value = place;
    select(null);

    void nextTick(() =>
        (mapView.value?.$el as HTMLElement | undefined)?.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
        }),
    );
}

function placeAt(latitude: number, longitude: number) {
    const target = placing.value;

    if (!target) {
        return;
    }

    placing.value = null;

    router.patch(
        targetLocation(target.id).url,
        { latitude: latitude.toFixed(6), longitude: longitude.toFixed(6) },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                select(target.id);
                void nextTick(() => mapView.value?.ping(target.id, '#10b981'));
            },
        },
    );
}

/** Select a place; `focus` also flies the map to it, for picks made outside the map. */
function select(id: number | null, focus = false) {
    selectedId.value = id;

    if (id === null) {
        // Touch screens never fire mouseleave, so clear the hover a tap left behind.
        hoveredId.value = null;

        return;
    }

    // Picking a place the filters hide (from a connection or the feed) shows everything again.
    if (!visible.value.some((place) => place.id === id)) {
        filter.value = 'all';
        query.value = '';
        lens.value = null;
    }

    void nextTick(() => {
        if (focus) {
            // On a phone the list sits under the map: bring the map back into view to watch it fly.
            (mapView.value?.$el as HTMLElement | undefined)?.scrollIntoView({
                block: 'nearest',
            });
            mapView.value?.focus(id);
        }

        placeList.value?.reveal(id);
    });
}

watch(visible, (places) => {
    if (!places.some((place) => place.id === selectedId.value)) {
        selectedId.value = null;
    }
});

const layers = useLocalStorage<MapLayers>(
    'aletheia.map.layers',
    { areas: true, links: true, heat: false },
    { mergeDefaults: true },
);
const follow = useLocalStorage('aletheia.map.follow', false);

const expanded = ref(false);
const scrollLock = useScrollLock(
    typeof document === 'undefined' ? null : document.body,
);

watch(expanded, (value) => (scrollLock.value = value));

useEventListener('keydown', (event: KeyboardEvent) => {
    if (event.key !== 'Escape') {
        return;
    }

    if (placing.value) {
        placing.value = null;
    } else if (expanded.value) {
        expanded.value = false;
    } else if (
        !(event.target instanceof HTMLInputElement) &&
        !(event.target instanceof HTMLSelectElement)
    ) {
        select(null);
    }
});

// Keep the selected place in the address, so a shared link or a reload opens the map on it.
watch([selectedId, lens], ([id, vector]) => {
    const url = new URL(window.location.href);

    for (const [key, value] of [
        ['place', id],
        ['vector', vector],
    ] as const) {
        if (value === null) {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, String(value));
        }
    }

    if (url.href !== window.location.href) {
        router.replace({
            url: url.pathname + url.search,
            preserveScroll: true,
            preserveState: true,
        });
    }
});

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const vector = Number(params.get('vector'));
    const id = Number(params.get('place'));

    if (lenses.value.some((entry) => entry.id === vector)) {
        lens.value = vector;
    }

    if (props.places.some((place) => place.id === id)) {
        select(id, true);
    }
});

const detail = ref<InstanceType<typeof MapPlaceDetail>>();

/** Leave full screen and scroll to the selected place's details, which sit under the map on a phone. */
function showDetails() {
    expanded.value = false;

    void nextTick(() =>
        (detail.value?.$el as HTMLElement | undefined)?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        }),
    );
}

const panel = ref<'places' | 'activity'>('places');
const tabs = computed(() => [
    { value: 'places' as const, label: 'Places', count: visible.value.length },
    {
        value: 'activity' as const,
        label: 'Live activity',
        count: props.activity.length,
    },
]);

const inProgress = computed(() =>
    [...props.places, ...props.unplaced].some(isRunning),
);

const timelineOpen = ref(false);

// Live updates: poll every 5 seconds while a search is queued or running, every 30 otherwise,
// and not at all while the tab is hidden (it catches up as soon as it is shown again).
const visibility = useDocumentVisibility();

// The history is the heaviest prop and changes slowly, so an open timeline refreshes once a minute.
let timelineLoadedAt = 0;

const online = useOnline();
const failures = ref(0);
const connection = computed(() =>
    !online.value ? 'offline' : failures.value > 0 ? 'retrying' : 'live',
);

// When the last refresh started, so a slow one isn't stacked on; cleared when it ends.
let pollingSince = 0;

function refresh() {
    if (
        visibility.value === 'hidden' ||
        !online.value ||
        (pollingSince && Date.now() - pollingSince < 20000)
    ) {
        return;
    }

    const withTimeline =
        timelineOpen.value && Date.now() - timelineLoadedAt > 60000;

    if (withTimeline) {
        timelineLoadedAt = Date.now();
    }

    const failed = () => {
        failures.value++;

        // Keep the map on screen and retry, instead of Inertia's error dialog.
        return false;
    };

    pollingSince = Date.now();
    router.reload({
        only: [
            'places',
            'unplaced',
            'unplacedCount',
            'links',
            'activity',
            ...(withTimeline ? ['timeline'] : []),
        ],
        onSuccess: () => {
            failures.value = 0;
        },
        onHttpException: failed,
        onNetworkError: failed,
        onFinish: () => (pollingSince = 0),
        onCancel: () => (pollingSince = 0),
    });
}

// After failures, back off: 10, 20, 40 seconds, then once a minute.
const poll = useIntervalFn(
    refresh,
    () =>
        failures.value > 0
            ? Math.min(60000, 5000 * 2 ** failures.value)
            : inProgress.value
              ? 5000
              : 30000,
    { immediate: false },
);

watch(isAdmin, (admin) => (admin ? poll.resume() : poll.pause()), {
    immediate: true,
});

watch(visibility, (state, before) => {
    if (isAdmin.value && state === 'visible' && before === 'hidden') {
        refresh();
    }
});

watch(online, (now, before) => {
    if (isAdmin.value && now && !before) {
        refresh();
    }
});

function loadTimeline() {
    timelineLoadedAt = Date.now();
    router.reload({ only: ['timeline'] });
}

function openTimeline() {
    timelineOpen.value = !timelineOpen.value;

    if (timelineOpen.value && props.timeline === undefined) {
        loadTimeline();
    }
}

// A full visit (such as starting a search) drops the optional history, so fetch it again.
watch(
    () => props.timeline,
    (events) => {
        if (events === undefined && timelineOpen.value) {
            loadTimeline();
        }
    },
);

function closeTimeline() {
    goLive();
    timelineOpen.value = false;
}

// The histogram follows the same filters and lens as the map.
const shownIds = computed(
    () => new Set(visible.value.map((place) => place.id)),
);
const timelineEvents = computed(() =>
    props.timeline?.events.filter((event) =>
        shownIds.value.has(event.target_id),
    ),
);

const updatedAt = ref(new Date().toISOString());
let previous = new Map(props.places.map((place) => [place.id, place]));

const runningSearches = (place: MapPlace) =>
    place.searches?.filter((search) => search.status === 'running').length ?? 0;

// Flash places where something changed since the last poll, and follow the newest change if asked.
watch(
    () => props.places,
    (places) => {
        updatedAt.value = new Date().toISOString();

        const changed: number[] = [];

        for (const place of places) {
            const before = previous.get(place.id);

            if (!before) {
                continue;
            }

            let color: string | null = null;

            if ((place.findings_count ?? 0) > (before.findings_count ?? 0)) {
                color = riskColor(place.max_risk_score ?? 0);
            } else if (
                (place.running_count ?? 0) > (before.running_count ?? 0) ||
                runningSearches(place) > runningSearches(before)
            ) {
                color = '#2563eb';
            } else if (
                (place.running_count ?? 0) < (before.running_count ?? 0)
            ) {
                color = '#10b981';
            }

            // A replay owns the map; live changes still update the counts, quietly.
            if (color && !replaying.value) {
                mapView.value?.ping(place.id, color);
                changed.push(place.id);
            }
        }

        previous = new Map(places.map((place) => [place.id, place]));

        if (follow.value && changed.length) {
            select(changed[0], true);
        }
    },
);
</script>

<template>
    <Head title="Map" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-2">
            <Heading
                title="Map"
                :description="
                    isAdmin
                        ? 'Where searches are running, what they are finding, and how places connect. Pins show open leads and are colored by their highest risk score.'
                        : 'Active targets that have a location. Numbers on pins are community votes, and the most wanted come first.'
                "
            />
            <MapLiveStatus
                v-if="isAdmin"
                :state="connection"
                :updated-at="updatedAt"
                :now="now"
                :busy="inProgress"
                @retry="refresh"
            />
        </div>

        <MapFilterCards v-if="isAdmin" v-model="filter" :options="filters" />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="min-w-0 space-y-3">
                <div
                    v-if="isAdmin && lenses.length"
                    class="flex flex-wrap items-center gap-x-3 gap-y-1.5"
                >
                    <label
                        for="map-lens"
                        class="text-sm font-medium text-muted-foreground"
                    >
                        Pattern
                    </label>
                    <NativeSelect
                        id="map-lens"
                        v-model="lens"
                        class="min-w-0 flex-1 sm:max-w-md"
                    >
                        <option :value="null">Every vector</option>
                        <option
                            v-for="entry in lenses"
                            :key="entry.id"
                            :value="entry.id"
                        >
                            {{ entry.title }} ({{
                                plural(entry.places, 'place')
                            }})
                        </option>
                    </NativeSelect>
                    <span
                        v-if="lens !== null"
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <span
                            class="size-2.5 rounded-full"
                            :style="{ backgroundColor: vectorColor(lens) }"
                        />
                        Showing where this vector is searched or has open leads
                    </span>
                </div>

                <Teleport to="body" :disabled="!expanded">
                    <div
                        :class="
                            cn(
                                'relative',
                                expanded
                                    ? 'fixed inset-0 z-50 bg-background p-2'
                                    : 'h-[60svh] min-h-80 lg:h-[68vh]',
                            )
                        "
                    >
                        <TargetMap
                            ref="mapView"
                            :places="visible"
                            :links="replaying ? [] : links"
                            :selected-id="selectedId"
                            :layers="layers"
                            :hovered-id="hoveredId"
                            :vector-id="lens"
                            :placing="placing !== null"
                            @select="select"
                            @hover="(id) => (hoveredId = id)"
                            @place="placeAt"
                        />

                        <MapControls
                            :layers="layers"
                            :follow="follow"
                            :timeline="timelineOpen"
                            :expanded="expanded"
                            :reviewer="isAdmin"
                            @layer="(key) => (layers[key] = !layers[key])"
                            @follow="follow = !follow"
                            @timeline="openTimeline"
                            @fit="mapView?.fit()"
                            @expand="expanded = !expanded"
                        />

                        <div
                            v-if="placing"
                            class="absolute top-4 left-14 z-10 flex max-w-[calc(100%-8rem)] items-center gap-2 rounded-lg bg-primary px-3 py-2 text-sm text-primary-foreground shadow-lg"
                            role="status"
                        >
                            <MapPin class="size-4 shrink-0" />
                            <span class="min-w-0">
                                Tap the map where
                                <span class="font-semibold">{{
                                    placing.name
                                }}</span>
                                is
                            </span>
                            <button
                                type="button"
                                class="shrink-0 rounded-md bg-primary-foreground/15 px-2 py-0.5 text-xs font-medium hover:bg-primary-foreground/25"
                                @click="placing = null"
                            >
                                Cancel
                            </button>
                        </div>

                        <p
                            v-else-if="replaying"
                            class="pointer-events-none absolute inset-x-0 top-4 z-10 mx-auto flex w-fit items-center gap-2 rounded-full bg-foreground px-3 py-1.5 text-xs font-medium text-background shadow"
                        >
                            <span
                                class="size-2 rounded-full bg-orange-500 motion-safe:animate-pulse"
                            />
                            Replay ·
                            {{
                                new Date(replayAt!).toLocaleString(undefined, {
                                    month: 'short',
                                    day: 'numeric',
                                    hour: 'numeric',
                                    minute: '2-digit',
                                })
                            }}
                        </p>

                        <p
                            v-else-if="
                                expanded && isAdmin && connection !== 'live'
                            "
                            role="status"
                            class="pointer-events-none absolute inset-x-0 top-4 z-10 mx-auto w-fit rounded-full bg-amber-500 px-3 py-1.5 text-xs font-medium text-white shadow"
                        >
                            {{
                                connection === 'offline'
                                    ? 'Offline · the map catches up when you reconnect'
                                    : 'Reconnecting · showing the last update'
                            }}
                        </p>

                        <p
                            v-else-if="!visible.length"
                            class="pointer-events-none absolute inset-x-0 top-4 z-10 mx-auto w-fit max-w-[70%] rounded-md bg-background/90 px-3 py-1.5 text-center text-sm shadow"
                        >
                            {{
                                query.trim()
                                    ? 'No places match your search.'
                                    : lens !== null
                                      ? 'Nothing on the map for this vector right now.'
                                      : filter === 'running'
                                        ? 'No searches are running right now.'
                                        : filter === 'leads'
                                          ? 'No open leads at targets on the map.'
                                          : filter === 'high'
                                            ? 'No high-risk leads at targets on the map.'
                                            : 'No targets have a location yet.'
                            }}
                        </p>

                        <!-- Bottom of the map: live ticker and replay bar in full screen, then the selection. -->
                        <div
                            class="pointer-events-none absolute inset-x-4 bottom-8 z-10 flex flex-col gap-2 sm:right-auto sm:w-96 [&>*]:pointer-events-auto"
                        >
                            <MapTicker
                                v-if="
                                    expanded &&
                                    isAdmin &&
                                    !selectedShown &&
                                    !replaying &&
                                    activity.length
                                "
                                :event="activity[0]"
                                :place-ids="placeIds"
                                :now="now"
                                @select="(id) => select(id, true)"
                            />
                            <MapReplayBar
                                v-if="expanded && isAdmin && timelineOpen"
                                :start="timelineRange.start"
                                :end="timelineRange.end"
                                :at="replayAt"
                                :playing="playing"
                                :disabled="!timelineEvents?.length"
                                @seek="seek"
                                @toggle="toggleReplay"
                                @live="goLive"
                            />
                            <div
                                v-if="selectedShown"
                                :class="
                                    cn(
                                        'flex items-center gap-3 rounded-lg border bg-background/95 p-3 shadow-lg backdrop-blur',
                                        !expanded && 'lg:hidden',
                                    )
                                "
                            >
                                <div class="min-w-0 flex-1">
                                    <Link
                                        :href="showTarget(selectedShown.id)"
                                        class="block truncate font-medium hover:underline"
                                    >
                                        {{ selectedShown.name }}
                                    </Link>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{
                                            placeSummary(selectedShown) ||
                                            selectedShown.type.label
                                        }}{{
                                            !placeSummary(selectedShown) &&
                                            selectedShown.region
                                                ? ` · ${selectedShown.region}`
                                                : ''
                                        }}
                                    </p>
                                </div>
                                <RiskScore
                                    v-if="
                                        hasLeads(selectedShown) &&
                                        selectedShown.max_risk_score !== null
                                    "
                                    :score="selectedShown.max_risk_score"
                                />
                                <button
                                    type="button"
                                    class="text-sm font-medium text-primary hover:underline"
                                    @click="showDetails"
                                >
                                    Details
                                </button>
                                <button
                                    type="button"
                                    class="-m-1 rounded-md p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                                    aria-label="Clear selection"
                                    @click="select(null)"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </Teleport>

                <MapTimeline
                    v-if="isAdmin && timelineOpen"
                    :events="timelineEvents"
                    :start="timelineRange.start"
                    :end="timelineRange.end"
                    :at="replayAt"
                    :playing="playing"
                    @seek="seek"
                    @toggle="toggleReplay"
                    @live="goLive"
                    @close="closeTimeline"
                />

                <MapLegend :reviewer="isAdmin" />
            </div>

            <aside class="min-w-0 space-y-3 self-start">
                <MapPlaceDetail
                    v-if="selected"
                    ref="detail"
                    :place="selected"
                    :places="places"
                    :links="links"
                    :vectors="vectors"
                    :now="now"
                    @select="(id) => select(id, true)"
                    @close="select(null)"
                />

                <div
                    v-if="isAdmin"
                    role="tablist"
                    aria-label="Side panel"
                    class="flex gap-1 rounded-lg bg-muted p-1 text-sm"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.value"
                        type="button"
                        role="tab"
                        :aria-selected="panel === tab.value"
                        :class="
                            cn(
                                'flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition-colors',
                                panel === tab.value
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )
                        "
                        @click="panel = tab.value"
                    >
                        {{ tab.label }}
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ tab.count }}</span
                        >
                    </button>
                </div>

                <MapActivityFeed
                    v-if="isAdmin && panel === 'activity'"
                    class="max-h-[60vh] overflow-y-auto"
                    :events="activity"
                    :place-ids="placeIds"
                    :now="now"
                    @select="(id) => select(id, true)"
                />

                <MapPlaceList
                    v-else
                    ref="placeList"
                    v-model:query="query"
                    :places="visible"
                    :selected-id="selectedId"
                    :hovered-id="hoveredId"
                    :reviewer="isAdmin"
                    :now="now"
                    @select="(id) => select(id, true)"
                    @hover="(id) => (hoveredId = id)"
                />
            </aside>
        </div>

        <section v-if="unplaced.length" class="space-y-3">
            <Heading
                variant="small"
                title="Not on the map"
                description="These targets have searches or open leads but no location. Place them on the map to see them there."
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
                            {{ placeSummary(place) }}
                        </div>
                    </div>
                    <button
                        type="button"
                        :aria-pressed="placing?.id === place.id"
                        class="flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                        @click="startPlacing(place)"
                    >
                        <MapPin class="size-3.5" /> Place on map
                    </button>
                    <Link
                        :href="editTarget(place.id)"
                        class="text-sm text-muted-foreground hover:text-foreground hover:underline"
                    >
                        Edit
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
