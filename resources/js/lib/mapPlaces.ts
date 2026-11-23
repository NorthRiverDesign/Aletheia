import type { MapPlace } from '@/types';

const DAY = 24 * 60 * 60 * 1000;

/** "1 search", "3 searches", "2 leads". */
export function plural(count: number, noun: string) {
    if (count === 1) {
        return `${count} ${noun}`;
    }

    return `${count} ${noun}${/(s|x|z|ch|sh)$/.test(noun) ? 'es' : 's'}`;
}

export const isRunning = (place: MapPlace) => (place.running_count ?? 0) > 0;
export const hasLeads = (place: MapPlace) => (place.findings_count ?? 0) > 0;

/** Whether a place's newest open lead arrived in the last day. */
export const isFresh = (place: MapPlace, now: Date) =>
    place.latest_lead_at !== null &&
    now.getTime() - Date.parse(place.latest_lead_at) < DAY;

/** "2 searches running · 3 open leads" for reviewers, "5 votes" for members. */
export function placeSummary(place: MapPlace) {
    if (place.findings_count === null) {
        return place.votes_count > 0 ? plural(place.votes_count, 'vote') : '';
    }

    return [
        isRunning(place)
            ? `${plural(place.running_count!, 'search')} running`
            : null,
        hasLeads(place) ? plural(place.findings_count, 'open lead') : null,
    ]
        .filter(Boolean)
        .join(' · ');
}
