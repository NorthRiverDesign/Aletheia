import { useIntervalFn } from '@vueuse/core';
import type { Ref } from 'vue';
import { computed, ref, watch } from 'vue';
import type { MapHistory, MapPlace, MapTimelineEvent } from '@/types';

const HOUR = 60 * 60 * 1000;
const DAY = 24 * HOUR;

/** Ticks per replay: at 100ms a tick, the whole range plays in about 20 seconds. */
const STEPS = 200;

/** Events flashed on the map per tick, so dense history doesn't flood it. */
const FLASHES_PER_TICK = 4;

/**
 * Replays the map's history: a cursor that plays from the first event to now, and the places as
 * they stood at the cursor. `history` is the timeline for every mapped place (undefined until
 * loaded); `flash` is called for each event the cursor passes while playing.
 */
export function useMapReplay(options: {
    history: () => MapHistory | undefined;
    now: Ref<Date>;
    flash: (event: MapTimelineEvent) => void;
}) {
    const at = ref<number | null>(null);
    const playing = ref(false);

    const active = computed(
        () => at.value !== null && options.history() !== undefined,
    );

    // From the first event (with a little lead-in) to now, at least an hour, and never before the
    // history's `from`, which moves up when the server had to cap a busy month.
    const range = computed(() => {
        const end = options.now.value.getTime();
        const history = options.history();
        const first = history?.events.length
            ? Date.parse(history.events[0].at)
            : end - DAY;
        const span = Math.min(Math.max((end - first) * 1.05, HOUR), 30 * DAY);
        const from = history ? Date.parse(history.from) : end - span;

        return { start: Math.max(end - span, Math.min(from, first)), end };
    });

    function toggle() {
        if (playing.value) {
            playing.value = false;

            return;
        }

        const { start, end } = range.value;

        if (at.value === null || at.value >= end) {
            at.value = start;
        }

        playing.value = true;
    }

    function live() {
        playing.value = false;
        at.value = null;
    }

    function seek(time: number) {
        at.value = time;
    }

    const player = useIntervalFn(
        () => {
            const { start, end } = range.value;
            const next = (at.value ?? start) + (end - start) / STEPS;

            at.value = Math.min(next, end);

            if (next >= end) {
                playing.value = false;
            }
        },
        100,
        { immediate: false },
    );

    watch(playing, (value) => (value ? player.resume() : player.pause()));

    watch(at, (time, before) => {
        if (
            !playing.value ||
            time === null ||
            before === null ||
            time <= before
        ) {
            return;
        }

        options
            .history()
            ?.events.filter((event) => {
                const when = Date.parse(event.at);

                return when > before && when <= time;
            })
            .slice(0, FLASHES_PER_TICK)
            .forEach(options.flash);
    });

    /** Places as they stood at the cursor: leads found by then, and searches started shortly before. */
    function asOf(places: MapPlace[]): MapPlace[] {
        const history = options.history();

        if (at.value === null || history === undefined) {
            return places;
        }

        const time = at.value;
        const recent = (range.value.end - range.value.start) / 40;
        // Start from the leads each place already had before the history begins.
        const tally = new Map(
            history.before.map((place) => [
                place.target_id,
                { leads: place.leads, top: place.top, searches: 0 },
            ]),
        );

        for (const event of history.events) {
            const when = Date.parse(event.at);

            if (when > time) {
                break;
            }

            const entry = tally.get(event.target_id) ?? {
                leads: 0,
                top: 0,
                searches: 0,
            };

            if (event.kind === 'lead') {
                entry.leads++;
                entry.top = Math.max(entry.top, event.risk_score ?? 0);
            } else if (when > time - recent) {
                entry.searches++;
            }

            tally.set(event.target_id, entry);
        }

        return places.map((place): MapPlace => {
            const entry = tally.get(place.id);

            return {
                ...place,
                findings_count: entry?.leads ?? 0,
                max_risk_score: entry?.leads ? entry.top : null,
                running_count: entry?.searches ?? 0,
                latest_lead_at: null,
                searches: entry?.searches
                    ? [
                          {
                              id: 0,
                              status: 'running',
                              vector: null,
                              created_at: null,
                              started_at: null,
                          },
                      ]
                    : [],
            };
        });
    }

    return { at, playing, active, range, toggle, live, seek, asOf };
}
