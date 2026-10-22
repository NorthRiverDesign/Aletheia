<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Map as MapIcon } from '@lucide/vue';
import { computed } from 'vue';
import AnalysisList from '@/components/AnalysisList.vue';
import FindingList from '@/components/FindingList.vue';
import Heading from '@/components/Heading.vue';
import VoteButton from '@/components/VoteButton.vue';
import { dashboard, map as mapPage } from '@/routes';
import { index as analysesIndex } from '@/routes/analyses';
import { index as findingsIndex } from '@/routes/findings';
import {
    index as targetsIndex,
    show as showTarget,
    vote as voteTarget,
} from '@/routes/targets';
import {
    index as vectorsIndex,
    show as showVector,
    vote as voteVector,
} from '@/routes/vectors';
import { plural } from '@/lib/mapPlaces';
import type { Analysis, Finding, Target, Vector } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<{
    stats: {
        vectors: number;
        targets: number;
        proposals: number;
        pending_findings: number | null;
        high_risk_findings: number | null;
    };
    /** Search and lead counts are null for members. */
    map: {
        places: number;
        searches: number | null;
        searched_places: number | null;
        new_leads: number | null;
    };
    proposedVectors: Vector[];
    proposedTargets: Target[];
    topFindings: Finding[];
    recentAnalyses: Analysis[];
}>();

const isAdmin = computed(() => usePage().props.auth.user.is_admin);

const live = computed(() =>
    props.map.searches === null
        ? [`${plural(props.map.places, 'target')} on the map`]
        : [
              props.map.searches
                  ? `${plural(props.map.searches, 'search')} running at ${plural(props.map.searched_places ?? 0, 'place')}`
                  : 'No searches running',
              `${plural(props.map.new_leads ?? 0, 'new lead')} in the last 24 hours`,
          ],
);

const tiles = computed(() => [
    {
        label: 'Active vectors',
        value: props.stats.vectors,
        href: vectorsIndex(),
    },
    {
        label: 'Active targets',
        value: props.stats.targets,
        href: targetsIndex(),
    },
    {
        label: 'Suggestions awaiting review',
        value: props.stats.proposals,
        href: vectorsIndex({ query: { status: 'proposed' } }),
    },
    ...(props.stats.pending_findings === null
        ? []
        : [
              {
                  label: `Leads to review (${props.stats.high_risk_findings} high risk)`,
                  value: props.stats.pending_findings,
                  href: findingsIndex(),
              },
          ]),
]);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-8 p-4">
        <Heading
            title="Aletheia"
            description="Find fraud in public spending together. Members suggest fraud patterns (vectors) and places to look (targets), upvote what matters most, and AI cross-checks them into scored leads for human review."
        />

        <Link
            :href="mapPage()"
            class="-mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl border bg-card p-4 transition-colors hover:bg-accent/50"
        >
            <span class="flex items-center gap-2 font-medium">
                <span
                    v-if="map.searches !== null"
                    class="relative flex size-2.5"
                    aria-hidden="true"
                >
                    <span
                        v-if="map.searches"
                        class="absolute inline-flex size-full rounded-full bg-emerald-400 opacity-75 motion-safe:animate-ping"
                    />
                    <span
                        :class="[
                            'relative inline-flex size-2.5 rounded-full',
                            map.searches
                                ? 'bg-emerald-500'
                                : 'bg-muted-foreground/50',
                        ]"
                    />
                </span>
                <MapIcon v-else class="size-4 text-muted-foreground" />
                {{ map.searches === null ? 'Map' : 'Live now' }}
            </span>
            <span
                v-for="line in live"
                :key="line"
                class="text-sm text-muted-foreground"
            >
                {{ line }}
            </span>
            <span
                class="ml-auto flex items-center gap-1 text-sm font-medium text-primary"
            >
                Open the map <ArrowRight class="size-4" />
            </span>
        </Link>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Link
                v-for="tile in tiles"
                :key="tile.label"
                :href="tile.href"
                class="rounded-xl border p-4 transition-colors hover:bg-accent/50"
            >
                <div class="text-3xl font-semibold tabular-nums">
                    {{ tile.value }}
                </div>
                <div class="text-sm text-muted-foreground">
                    {{ tile.label }}
                </div>
            </Link>
        </div>

        <div class="grid gap-8 lg:grid-cols-2">
            <section class="space-y-3">
                <Heading
                    variant="small"
                    title="Proposed vectors"
                    description="Upvote the patterns you want checked first."
                />
                <ul
                    v-if="proposedVectors.length"
                    class="divide-y rounded-xl border"
                >
                    <li
                        v-for="vector in proposedVectors"
                        :key="vector.id"
                        class="flex items-center gap-3 p-3"
                    >
                        <VoteButton
                            :href="voteVector(vector.id)"
                            :count="vector.votes_count"
                            :voted="vector.has_voted"
                        />
                        <Link
                            :href="showVector(vector.id)"
                            class="min-w-0 flex-1 hover:underline"
                        >
                            <div class="font-medium">{{ vector.title }}</div>
                            <div
                                class="line-clamp-1 text-sm text-muted-foreground"
                            >
                                {{ vector.summary }}
                            </div>
                        </Link>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    No open suggestions.
                    <Link :href="vectorsIndex()" class="underline"
                        >Browse vectors</Link
                    >.
                </p>
            </section>

            <section class="space-y-3">
                <Heading
                    variant="small"
                    title="Proposed targets"
                    description="Upvote where the platform should look next."
                />
                <ul
                    v-if="proposedTargets.length"
                    class="divide-y rounded-xl border"
                >
                    <li
                        v-for="target in proposedTargets"
                        :key="target.id"
                        class="flex items-center gap-3 p-3"
                    >
                        <VoteButton
                            :href="voteTarget(target.id)"
                            :count="target.votes_count"
                            :voted="target.has_voted"
                        />
                        <Link
                            :href="showTarget(target.id)"
                            class="min-w-0 flex-1 hover:underline"
                        >
                            <div class="font-medium">{{ target.name }}</div>
                            <div class="text-sm text-muted-foreground">
                                {{ target.type.label
                                }}<template v-if="target.region">
                                    · {{ target.region }}</template
                                >
                            </div>
                        </Link>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    No open suggestions.
                    <Link :href="targetsIndex()" class="underline"
                        >Browse targets</Link
                    >.
                </p>
            </section>
        </div>

        <div v-if="isAdmin" class="grid gap-8 lg:grid-cols-2">
            <section class="space-y-3">
                <Heading
                    variant="small"
                    title="Highest-risk leads"
                    description="Pending review, highest score first."
                />
                <FindingList
                    :findings="topFindings"
                    empty="No leads waiting for review."
                />
                <Link
                    :href="findingsIndex()"
                    class="text-sm underline underline-offset-4"
                    >Open the review queue</Link
                >
            </section>

            <section class="space-y-3">
                <Heading variant="small" title="Recent analyses" />
                <AnalysisList :analyses="recentAnalyses" />
                <Link
                    :href="analysesIndex()"
                    class="text-sm underline underline-offset-4"
                    >Run an analysis</Link
                >
            </section>
        </div>
    </div>
</template>
