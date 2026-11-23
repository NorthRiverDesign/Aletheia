<script setup lang="ts">
import { Pause, Play, Radio, X } from '@lucide/vue';
import { useElementSize } from '@vueuse/core';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { MapTimelineEvent } from '@/types';

const props = defineProps<{
    /** Undefined while the history is loading. */
    events: MapTimelineEvent[] | undefined;
    start: number;
    end: number;
    /** The replay cursor, or null when the map shows the present. */
    at: number | null;
    playing: boolean;
}>();

const emit = defineEmits<{
    seek: [at: number];
    toggle: [];
    live: [];
    close: [];
}>();

const BUCKETS = 30;
const HEIGHT = 96;
const TOP = 14;

const frame = ref<HTMLElement>();
const { width } = useElementSize(frame);

const span = computed(() => Math.max(props.end - props.start, 1));

const buckets = computed(() => {
    const size = span.value / BUCKETS;
    const list = Array.from({ length: BUCKETS }, (_, index) => ({
        from: props.start + index * size,
        to: props.start + (index + 1) * size,
        searches: 0,
        leads: 0,
    }));

    for (const event of props.events ?? []) {
        const index = Math.floor((Date.parse(event.at) - props.start) / size);
        const bucket = list[Math.min(BUCKETS - 1, Math.max(0, index))];

        if (event.kind === 'lead') {
            bucket.leads++;
        } else {
            bucket.searches++;
        }
    }

    return list;
});

// A clean top for the scale: 1, 2 or 5 times a power of ten.
const scaleMax = computed(() => {
    const most = Math.max(1, ...buckets.value.map((b) => b.searches + b.leads));
    const power = 10 ** Math.floor(Math.log10(most));
    const step = most / power;

    return (step <= 1 ? 1 : step <= 2 ? 2 : step <= 5 ? 5 : 10) * power;
});

const slot = computed(() => width.value / BUCKETS);
const barWidth = computed(() => Math.max(2, Math.min(24, slot.value - 2)));
const plot = HEIGHT - TOP;

/** A column segment; only the top one gets the 4px rounded end, the baseline stays square. */
function segment(x: number, y: number, w: number, h: number, round: boolean) {
    if (!round) {
        return `M${x},${y}h${w}v${h}h${-w}Z`;
    }

    const r = Math.min(4, w / 2, h);

    return `M${x},${y + h}V${y + r}a${r},${r} 0 0 1 ${r},${-r}H${x + w - r}a${r},${r} 0 0 1 ${r},${r}V${y + h}Z`;
}

const columns = computed(() =>
    buckets.value.map((bucket, index) => {
        const x = index * slot.value + (slot.value - barWidth.value) / 2;
        const height = (count: number) =>
            count ? Math.max(2, (count / scaleMax.value) * plot) : 0;
        const searches = height(bucket.searches);
        const leads = height(bucket.leads);
        const gap = searches && leads ? 2 : 0;

        return {
            ...bucket,
            searchPath: searches
                ? segment(
                      x,
                      HEIGHT - searches,
                      barWidth.value,
                      searches,
                      !leads,
                  )
                : null,
            leadPath: leads
                ? segment(
                      x,
                      HEIGHT - searches - gap - leads,
                      barWidth.value,
                      leads,
                      true,
                  )
                : null,
            later: props.at !== null && bucket.from > props.at,
        };
    }),
);

const cursor = computed(() =>
    props.at === null
        ? null
        : ((props.at - props.start) / span.value) * width.value,
);

function format(time: number) {
    return new Date(time).toLocaleString(
        undefined,
        span.value > 3 * 24 * 60 * 60 * 1000
            ? { month: 'short', day: 'numeric' }
            : {
                  month: 'short',
                  day: 'numeric',
                  hour: 'numeric',
                  minute: '2-digit',
              },
    );
}

function formatExact(time: number) {
    return new Date(time).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

const hovered = ref<number | null>(null);
const tip = computed(() => {
    if (hovered.value === null) {
        return null;
    }

    const column = columns.value[hovered.value];
    const left = Math.min(
        Math.max(slot.value * (hovered.value + 0.5), 72),
        width.value - 72,
    );

    return { ...column, left };
});

const totals = computed(() =>
    buckets.value.reduce(
        (sum, bucket) => ({
            searches: sum.searches + bucket.searches,
            leads: sum.leads + bucket.leads,
        }),
        { searches: 0, leads: 0 },
    ),
);

const leadsBy = (time: number) =>
    (props.events ?? []).filter(
        (event) => event.kind === 'lead' && Date.parse(event.at) <= time,
    ).length;

const cursorLabel = computed(() => {
    const time = props.at ?? props.end;

    return `${formatExact(time)}, ${leadsBy(time)} leads found by then`;
});
</script>

<template>
    <section
        class="space-y-3 rounded-xl border bg-card p-4 text-card-foreground"
    >
        <header class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <div class="min-w-0 flex-1">
                <h3 class="text-sm font-medium">Timeline</h3>
                <p class="text-xs text-muted-foreground">
                    <template v-if="at === null">
                        {{ totals.searches }} searches started and
                        {{ totals.leads }} open leads found since
                        {{ format(start) }}
                    </template>
                    <template v-else>
                        Replaying ·
                        <span class="font-medium text-foreground">{{
                            formatExact(at)
                        }}</span>
                    </template>
                </p>
            </div>
            <ul
                class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
            >
                <li class="flex items-center gap-1.5">
                    <span
                        class="size-2.5 rounded-sm bg-blue-600 dark:bg-blue-500"
                    />
                    Searches started
                </li>
                <li class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-orange-600" />
                    Leads found
                </li>
            </ul>
            <div class="flex items-center gap-1.5">
                <Button
                    size="sm"
                    :disabled="!events?.length"
                    @click="emit('toggle')"
                >
                    <component :is="playing ? Pause : Play" />
                    {{ playing ? 'Pause' : at === null ? 'Replay' : 'Play' }}
                </Button>
                <Button
                    v-if="at !== null"
                    variant="outline"
                    size="sm"
                    @click="emit('live')"
                >
                    <Radio /> Live
                </Button>
                <button
                    type="button"
                    class="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground"
                    aria-label="Close timeline"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
            </div>
        </header>

        <div ref="frame" :class="cn('relative', !events && 'opacity-50')">
            <svg
                v-if="width"
                :width="width"
                :height="HEIGHT"
                class="block overflow-visible"
                aria-hidden="true"
            >
                <text :x="0" :y="9" class="fill-muted-foreground text-[10px]">
                    {{ scaleMax }}
                </text>
                <line
                    :x1="0"
                    :x2="width"
                    :y1="TOP"
                    :y2="TOP"
                    class="stroke-border"
                    stroke-width="1"
                />
                <line
                    :x1="0"
                    :x2="width"
                    :y1="HEIGHT - 0.5"
                    :y2="HEIGHT - 0.5"
                    class="stroke-border"
                    stroke-width="1"
                />
                <g
                    v-for="(column, index) in columns"
                    :key="index"
                    :class="
                        cn('transition-opacity', column.later && 'opacity-25')
                    "
                >
                    <rect
                        v-if="hovered === index"
                        :x="index * slot"
                        :y="TOP"
                        :width="slot"
                        :height="HEIGHT - TOP"
                        class="fill-muted"
                    />
                    <path
                        v-if="column.searchPath"
                        :d="column.searchPath"
                        class="fill-blue-600 dark:fill-blue-500"
                    />
                    <path
                        v-if="column.leadPath"
                        :d="column.leadPath"
                        class="fill-orange-600"
                    />
                    <rect
                        :x="index * slot"
                        :y="0"
                        :width="slot"
                        :height="HEIGHT"
                        fill="transparent"
                        class="cursor-pointer"
                        @pointerenter="hovered = index"
                        @pointerleave="hovered = null"
                        @click="emit('seek', column.to)"
                    />
                </g>
                <line
                    v-if="cursor !== null"
                    :x1="cursor"
                    :x2="cursor"
                    :y1="TOP - 4"
                    :y2="HEIGHT"
                    class="pointer-events-none stroke-foreground"
                    stroke-width="1"
                />
            </svg>

            <div
                v-if="tip"
                class="pointer-events-none absolute top-4 z-10 w-max -translate-x-1/2 rounded-md border bg-popover px-2.5 py-1.5 text-xs text-popover-foreground shadow-md"
                :style="{ left: `${tip.left}px` }"
            >
                <p class="text-muted-foreground">
                    {{ format(tip.from) }} – {{ format(tip.to) }}
                </p>
                <p class="flex items-center gap-1.5">
                    <span
                        class="h-0.5 w-3 rounded-full bg-blue-600 dark:bg-blue-500"
                    />
                    <span class="font-semibold tabular-nums">{{
                        tip.searches
                    }}</span>
                    searches started
                </p>
                <p class="flex items-center gap-1.5">
                    <span class="h-0.5 w-3 rounded-full bg-orange-600" />
                    <span class="font-semibold tabular-nums">{{
                        tip.leads
                    }}</span>
                    leads found
                </p>
            </div>

            <p
                v-if="!events"
                class="absolute inset-0 grid place-items-center text-sm text-muted-foreground"
            >
                Loading the last 30 days…
            </p>

            <div
                class="mt-1 flex justify-between text-[10px] text-muted-foreground"
            >
                <span>{{ format(start) }}</span>
                <span>Now</span>
            </div>
        </div>

        <input
            type="range"
            class="w-full accent-primary"
            :min="start"
            :max="end"
            :step="span / 500"
            :value="at ?? end"
            :disabled="!events?.length"
            aria-label="Replay time"
            :aria-valuetext="cursorLabel"
            @input="
                emit('seek', Number(($event.target as HTMLInputElement).value))
            "
        />

        <!-- Tables ignore sr-only sizing, so the wrapper hides it. -->
        <div class="sr-only">
            <table>
                <caption>
                    Searches started and open leads found per period
                </caption>
                <thead>
                    <tr>
                        <th scope="col">Period</th>
                        <th scope="col">Searches started</th>
                        <th scope="col">Leads found</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="bucket in buckets" :key="bucket.from">
                        <th scope="row">
                            {{ format(bucket.from) }} – {{ format(bucket.to) }}
                        </th>
                        <td>{{ bucket.searches }}</td>
                        <td>{{ bucket.leads }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
