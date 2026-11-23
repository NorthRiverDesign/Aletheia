<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import RiskScore from '@/components/RiskScore.vue';
import { eventKinds, eventOutcome } from '@/lib/mapEvents';
import { cn, timeAgo } from '@/lib/utils';
import { show as showAnalysis } from '@/routes/analyses';
import { show as showFinding } from '@/routes/findings';
import type { MapEvent } from '@/types';

const props = defineProps<{
    events: MapEvent[];
    /** Places on the map, which events can jump to. */
    placeIds: Set<number>;
    now: Date;
}>();

const emit = defineEmits<{
    select: [id: number];
}>();

// Highlight events that arrive while the page is open.
const fresh = ref(new Set<string>());
let timer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => props.events,
    (events, previous) => {
        const known = new Set(previous.map((event) => event.key));
        const arrived = events
            .filter((event) => !known.has(event.key))
            .map((event) => event.key);

        if (!arrived.length) {
            return;
        }

        fresh.value = new Set(arrived);
        clearTimeout(timer);
        timer = setTimeout(() => (fresh.value = new Set()), 6000);
    },
);

onBeforeUnmount(() => clearTimeout(timer));

/** Rows listed per group before "and N more". */
const SHOWN = 3;

// Back-to-back events of one kind at one place (such as queueing every vector there) share a row.
const groups = computed(() => {
    const list: { key: string; kind: MapEvent['kind']; events: MapEvent[] }[] =
        [];

    for (const event of props.events) {
        const last = list.at(-1);

        if (
            last &&
            last.kind === event.kind &&
            last.events[0].target?.id === event.target?.id
        ) {
            last.events.push(event);
        } else {
            list.push({ key: event.key, kind: event.kind, events: [event] });
        }
    }

    return list;
});

function outcome(events: MapEvent[]) {
    if (events.length === 1) {
        return eventOutcome(events[0]);
    }

    if (events[0].kind !== 'completed') {
        return null;
    }

    const leads = events.reduce(
        (total, event) => total + (event.findings_count ?? 0),
        0,
    );

    return leads === 0
        ? 'no open leads'
        : `${leads} open lead${leads === 1 ? '' : 's'}`;
}
</script>

<template>
    <ol
        v-if="events.length"
        class="divide-y rounded-xl border"
        aria-live="polite"
    >
        <li
            v-for="group in groups"
            :key="group.key"
            :class="
                cn(
                    'flex gap-3 p-3 transition-colors duration-1000',
                    group.events.some((event) => fresh.has(event.key)) &&
                        'animate-in bg-primary/10 fade-in slide-in-from-top-1',
                )
            "
        >
            <component
                :is="eventKinds[group.kind].icon"
                :class="
                    cn('mt-0.5 size-4 shrink-0', eventKinds[group.kind].tone)
                "
            />
            <div class="min-w-0 flex-1 space-y-0.5 text-sm">
                <div class="flex flex-wrap items-baseline gap-x-2">
                    <span class="font-medium">{{
                        eventKinds[group.kind].label
                    }}</span>
                    <span
                        v-if="group.events.length > 1"
                        class="rounded-full bg-muted px-1.5 text-xs font-medium tabular-nums"
                        >×{{ group.events.length }}</span
                    >
                    <span
                        v-if="outcome(group.events)"
                        class="text-muted-foreground"
                    >
                        · {{ outcome(group.events) }}
                    </span>
                </div>
                <template
                    v-for="event in group.events.slice(0, SHOWN)"
                    :key="event.key"
                >
                    <Link
                        v-if="event.finding"
                        :href="showFinding(event.finding.id)"
                        class="flex items-start gap-2 hover:underline"
                    >
                        <RiskScore :score="event.finding.risk_score" />
                        <span class="min-w-0 flex-1 pt-1 break-words">{{
                            event.finding.title
                        }}</span>
                    </Link>
                    <Link
                        v-else
                        :href="showAnalysis(event.analysis_id)"
                        class="block break-words hover:underline"
                    >
                        {{ event.vector ?? 'Deleted vector' }}
                    </Link>
                </template>
                <p
                    v-if="group.events.length > SHOWN"
                    class="text-xs text-muted-foreground"
                >
                    And {{ group.events.length - SHOWN }} more
                </p>
                <p
                    class="flex flex-wrap gap-x-1.5 text-xs text-muted-foreground"
                >
                    <button
                        v-if="
                            group.events[0].target &&
                            placeIds.has(group.events[0].target.id)
                        "
                        type="button"
                        class="font-medium text-primary hover:underline"
                        @click="emit('select', group.events[0].target.id)"
                    >
                        {{ group.events[0].target.name }}
                    </button>
                    <span v-else-if="group.events[0].target">{{
                        group.events[0].target.name
                    }}</span>
                    <span v-if="group.events[0].at"
                        >· {{ timeAgo(group.events[0].at, now) }}</span
                    >
                </p>
            </div>
        </li>
    </ol>
    <p v-else class="rounded-xl border p-4 text-sm text-muted-foreground">
        Nothing has happened yet. Searches and new leads show up here as they
        happen.
    </p>
</template>
