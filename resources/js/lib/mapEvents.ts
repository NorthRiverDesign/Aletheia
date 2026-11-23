import { CircleCheck, CircleX, Clock, Radar, Siren } from '@lucide/vue';
import type { MapEvent } from '@/types';

/** How each kind of live map event is shown: icon, label and icon color. */
export const eventKinds = {
    queued: {
        icon: Clock,
        label: 'Search queued',
        tone: 'text-muted-foreground',
    },
    running: {
        icon: Radar,
        label: 'Searching',
        tone: 'text-blue-600 dark:text-blue-400',
    },
    completed: {
        icon: CircleCheck,
        label: 'Search finished',
        tone: 'text-emerald-600 dark:text-emerald-400',
    },
    failed: { icon: CircleX, label: 'Search failed', tone: 'text-destructive' },
    lead: {
        icon: Siren,
        label: 'New lead',
        tone: 'text-red-600 dark:text-red-400',
    },
} as const;

/** "3 open leads" for a finished search, null for everything else. */
export function eventOutcome(event: MapEvent) {
    if (event.kind !== 'completed' || event.findings_count === null) {
        return null;
    }

    return event.findings_count === 0
        ? 'no open leads'
        : `${event.findings_count} open lead${event.findings_count === 1 ? '' : 's'}`;
}
