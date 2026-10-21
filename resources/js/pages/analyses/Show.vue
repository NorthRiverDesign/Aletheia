<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { CircleAlert, LoaderCircle } from '@lucide/vue';
import { computed, watch } from 'vue';
import FindingList from '@/components/FindingList.vue';
import Heading from '@/components/Heading.vue';
import LeadDisclaimer from '@/components/LeadDisclaimer.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { index } from '@/routes/analyses';
import { show as showTarget } from '@/routes/targets';
import { show as showVector } from '@/routes/vectors';
import type { Analysis, Finding } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Analyses', href: index() }],
    },
});

const props = defineProps<{
    analysis: Analysis;
    findings: Finding[];
}>();

const inProgress = computed(() =>
    ['queued', 'running'].includes(props.analysis.status.value),
);

const { start, stop } = usePoll(
    4000,
    { only: ['analysis', 'findings'] },
    { autoStart: false },
);

watch(inProgress, (running) => (running ? start() : stop()), {
    immediate: true,
});

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '—';
}

const usage = computed(() => props.analysis.usage ?? {});
</script>

<template>
    <Head title="Analysis" />

    <div class="flex flex-1 flex-col gap-8 p-4 lg:max-w-4xl">
        <div class="space-y-2">
            <StatusBadge :status="analysis.status" />
            <h1 class="text-2xl font-semibold tracking-tight">
                <Link
                    v-if="analysis.vector"
                    :href="showVector(analysis.vector.id)"
                    class="hover:underline"
                    >{{ analysis.vector.title }}</Link
                >
                <span class="text-muted-foreground"> × </span>
                <Link
                    v-if="analysis.target"
                    :href="showTarget(analysis.target.id)"
                    class="hover:underline"
                    >{{ analysis.target.name }}</Link
                >
            </h1>
            <dl
                class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm text-muted-foreground sm:grid-cols-4"
            >
                <div>
                    <dt class="text-xs">Requested</dt>
                    <dd>{{ formatDate(analysis.created_at) }}</dd>
                </div>
                <div>
                    <dt class="text-xs">Finished</dt>
                    <dd>{{ formatDate(analysis.completed_at) }}</dd>
                </div>
                <div>
                    <dt class="text-xs">Model</dt>
                    <dd>{{ analysis.model ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs">Tokens / searches</dt>
                    <dd>
                        {{
                            (
                                (usage.input_tokens ?? 0) +
                                (usage.output_tokens ?? 0)
                            ).toLocaleString()
                        }}
                        / {{ usage.web_searches ?? 0 }}
                    </dd>
                </div>
                <div v-if="usage.cached_input_tokens">
                    <dt class="text-xs">Cached input</dt>
                    <dd>{{ usage.cached_input_tokens.toLocaleString() }}</dd>
                </div>
                <div v-if="usage.cost_usd">
                    <dt class="text-xs">Cost</dt>
                    <dd>${{ usage.cost_usd.toFixed(4) }}</dd>
                </div>
                <div v-if="analysis.cooldown_until">
                    <dt class="text-xs">Can re-run</dt>
                    <dd>{{ formatDate(analysis.cooldown_until) }}</dd>
                </div>
            </dl>
        </div>

        <Alert v-if="inProgress">
            <LoaderCircle class="animate-spin" />
            <AlertTitle>{{
                analysis.status.value === 'queued'
                    ? 'Waiting for a queue worker'
                    : 'Analyzing'
            }}</AlertTitle>
            <AlertDescription>
                This page refreshes automatically. Analyses with web search can
                take a few minutes.
            </AlertDescription>
        </Alert>

        <Alert v-if="analysis.error" variant="destructive">
            <CircleAlert />
            <AlertTitle>The analysis failed</AlertTitle>
            <AlertDescription>{{ analysis.error }}</AlertDescription>
        </Alert>

        <section v-if="analysis.summary" class="space-y-2">
            <Heading variant="small" title="Assessment" />
            <p class="whitespace-pre-line">{{ analysis.summary }}</p>
        </section>

        <section v-if="analysis.status.value === 'completed'" class="space-y-3">
            <Heading variant="small" title="Findings" />
            <LeadDisclaimer v-if="findings.length" />
            <FindingList
                :findings="findings"
                empty="Nothing checked out for this pairing."
            />
        </section>
    </div>
</template>
