<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Radar, Share2, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import RiskScore from '@/components/RiskScore.vue';
import RunAnalysisForm from '@/components/RunAnalysisForm.vue';
import { Button } from '@/components/ui/button';
import { timeAgo } from '@/lib/utils';
import { show as showAnalysis } from '@/routes/analyses';
import { show as showFinding } from '@/routes/findings';
import { show as showTarget } from '@/routes/targets';
import type { MapLink, MapPlace, MapSearch, PickerOption } from '@/types';

const props = defineProps<{
    place: MapPlace;
    places: MapPlace[];
    links: MapLink[];
    /** Vectors to start a search with; empty for members who can't run one. */
    vectors: PickerOption[];
    now: Date;
}>();

const emit = defineEmits<{
    select: [id: number];
    close: [];
}>();

const starting = ref(false);

/** Share this view (place and any pattern lens) with the phone's share sheet, or copy the link. */
async function share() {
    const url = new URL(window.location.href);
    url.searchParams.set('place', String(props.place.id));

    if (navigator.share) {
        try {
            await navigator.share({
                title: `${props.place.name} on the map`,
                url: url.href,
            });
        } catch {
            // Closing the share sheet rejects; nothing to do.
        }

        return;
    }

    try {
        await navigator.clipboard.writeText(url.href);
        toast.success('Link copied.');
    } catch {
        // The clipboard API needs https or localhost; on plain http fall back to the old copy command,
        // and failing that, offer the link to copy by hand.
        const field = document.createElement('textarea');
        field.value = url.href;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.append(field);
        field.select();

        const copied = document.execCommand('copy');
        field.remove();

        if (copied) {
            toast.success('Link copied.');
        } else {
            window.prompt('Copy this link', url.href);
        }
    }
}

watch(
    () => props.place.id,
    () => (starting.value = false),
);

const byId = computed(
    () => new Map(props.places.map((place) => [place.id, place])),
);

// Places whose open leads name the same people or organizations as this one.
const connections = computed(() =>
    props.links
        .filter(
            (link) =>
                link.source === props.place.id ||
                link.target === props.place.id,
        )
        .map((link) => ({
            ...link,
            other: byId.value.get(
                link.source === props.place.id ? link.target : link.source,
            ),
        }))
        .filter((link) => link.other !== undefined),
);

// For each vector with open leads here, the other places with open leads for it.
const patterns = computed(() =>
    (props.place.patterns ?? [])
        .map((vector) => ({
            ...vector,
            places: props.places.filter(
                (place) =>
                    place.id !== props.place.id &&
                    place.patterns?.some((pattern) => pattern.id === vector.id),
            ),
        }))
        .filter((pattern) => pattern.places.length),
);

/** Patterns listed before "Show all", so a busy place doesn't push the rest of the panel off a phone screen. */
const PATTERNS_SHOWN = 3;
const allPatterns = ref(false);

watch(
    () => props.place.id,
    () => (allPatterns.value = false),
);

const moreSearches = computed(
    () =>
        (props.place.running_count ?? 0) - (props.place.searches?.length ?? 0),
);

function since(search: MapSearch) {
    const at =
        search.status === 'running' ? search.started_at : search.created_at;
    const label = search.status === 'running' ? 'Running' : 'Queued';

    return at ? `${label} ${timeAgo(at, props.now)}` : label;
}
</script>

<template>
    <section
        class="space-y-4 rounded-xl border bg-card p-4 text-card-foreground"
    >
        <header class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <Link
                    :href="showTarget(place.id)"
                    class="block font-semibold break-words hover:underline"
                >
                    {{ place.name }}
                </Link>
                <p class="text-sm text-muted-foreground">
                    {{ place.type.label
                    }}{{ place.region ? ` · ${place.region}` : '' }}
                </p>
                <p
                    v-if="place.latest_lead_at"
                    class="text-xs text-muted-foreground"
                >
                    Newest lead {{ timeAgo(place.latest_lead_at, now) }}
                </p>
            </div>
            <RiskScore
                v-if="
                    (place.findings_count ?? 0) > 0 &&
                    place.max_risk_score !== null
                "
                :score="place.max_risk_score"
            />
            <button
                type="button"
                class="-m-1 rounded-md p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                aria-label="Share a link to this place"
                title="Share a link to this place"
                @click="share"
            >
                <Share2 class="size-4" />
            </button>
            <button
                type="button"
                class="-m-1 rounded-md p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                aria-label="Close details"
                @click="emit('close')"
            >
                <X class="size-4" />
            </button>
        </header>

        <div v-if="place.searches?.length" class="space-y-2">
            <h3
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                Searching now
            </h3>
            <ul class="space-y-1.5">
                <li
                    v-for="search in place.searches"
                    :key="search.id"
                    class="flex items-start gap-2 text-sm"
                >
                    <span class="relative mt-1.5 flex size-2 shrink-0">
                        <span
                            v-if="search.status === 'running'"
                            class="absolute inline-flex size-full rounded-full bg-blue-400 opacity-75 motion-safe:animate-ping"
                        />
                        <span
                            :class="[
                                'relative inline-flex size-2 rounded-full',
                                search.status === 'running'
                                    ? 'bg-blue-600'
                                    : 'border border-blue-600',
                            ]"
                        />
                    </span>
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="showAnalysis(search.id)"
                            class="block break-words hover:underline"
                        >
                            {{ search.vector?.title ?? 'Deleted vector' }}
                        </Link>
                        <span class="text-xs text-muted-foreground">{{
                            since(search)
                        }}</span>
                    </div>
                </li>
            </ul>
            <p v-if="moreSearches > 0" class="text-xs text-muted-foreground">
                And {{ moreSearches }} more.
            </p>
        </div>

        <div v-if="place.leads?.length" class="space-y-2">
            <h3
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                Top open leads
            </h3>
            <ul class="space-y-1.5">
                <li v-for="lead in place.leads" :key="lead.id">
                    <Link
                        :href="showFinding(lead.id)"
                        class="flex items-start gap-2 rounded-md text-sm hover:underline"
                    >
                        <RiskScore :score="lead.risk_score" />
                        <span class="min-w-0 flex-1 pt-1 break-words">{{
                            lead.title
                        }}</span>
                    </Link>
                </li>
            </ul>
            <Link
                v-if="(place.findings_count ?? 0) > place.leads.length"
                :href="showTarget(place.id)"
                class="text-xs font-medium text-primary hover:underline"
            >
                All {{ place.findings_count }} open leads
            </Link>
        </div>

        <div v-if="connections.length" class="space-y-2">
            <h3
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                Shared names
            </h3>
            <ul class="space-y-1.5">
                <li
                    v-for="link in connections"
                    :key="`${link.source}-${link.target}`"
                >
                    <button
                        type="button"
                        class="w-full rounded-md text-left text-sm hover:underline"
                        @click="emit('select', link.other!.id)"
                    >
                        <span class="flex items-center gap-2">
                            <span
                                class="h-0.5 w-4 shrink-0 rounded-full bg-purple-500"
                            />
                            <span class="min-w-0 flex-1 truncate font-medium">{{
                                link.other!.name
                            }}</span>
                        </span>
                        <span
                            class="block pl-6 text-xs break-words text-muted-foreground"
                        >
                            {{ link.names.join(', ')
                            }}{{ link.count > link.names.length ? ', …' : '' }}
                        </span>
                    </button>
                </li>
            </ul>
        </div>

        <div v-if="patterns.length" class="space-y-2">
            <h3
                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
            >
                Same pattern elsewhere
            </h3>
            <ul class="space-y-2">
                <template
                    v-for="pattern in allPatterns
                        ? patterns
                        : patterns.slice(0, PATTERNS_SHOWN)"
                    :key="pattern.id"
                >
                    <li class="text-sm">
                        <p class="break-words">{{ pattern.title }}</p>
                        <p class="flex flex-wrap gap-x-2 text-xs">
                            <button
                                v-for="other in pattern.places"
                                :key="other.id"
                                type="button"
                                class="text-primary hover:underline"
                                @click="emit('select', other.id)"
                            >
                                {{ other.name }}
                            </button>
                        </p>
                    </li>
                </template>
            </ul>
            <button
                v-if="!allPatterns && patterns.length > PATTERNS_SHOWN"
                type="button"
                class="text-xs font-medium text-primary hover:underline"
                @click="allPatterns = true"
            >
                Show all {{ patterns.length }} patterns
            </button>
        </div>

        <p
            v-if="place.findings_count === 0 && !place.searches?.length"
            class="text-sm text-muted-foreground"
        >
            No searches running and no open leads here.
        </p>

        <div v-if="vectors.length" class="border-t pt-4">
            <RunAnalysisForm
                v-if="starting"
                :target-id="place.id"
                :vectors="vectors"
                stay
                compact
                @success="starting = false"
            />
            <Button
                v-else
                variant="outline"
                size="sm"
                class="w-full"
                @click="starting = true"
            >
                <Radar /> Search here
            </Button>
        </div>
    </section>
</template>
