<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AnalysisList from '@/components/AnalysisList.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import RunAnalysisForm from '@/components/RunAnalysisForm.vue';
import { index } from '@/routes/analyses';
import type { Analysis, Paginated, PickerOption } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Analyses', href: index() }],
    },
});

const props = defineProps<{
    analyses: Paginated<Analysis>;
    vectors: PickerOption[];
    targets: PickerOption[];
    cooldown: string | null;
}>();

const inProgress = computed(() =>
    props.analyses.data.some((analysis) =>
        ['queued', 'running'].includes(analysis.status.value),
    ),
);

const { start, stop } = usePoll(
    5000,
    { only: ['analyses'] },
    { autoStart: false },
);

// Keep the list fresh while anything is still queued or running.
watch(inProgress, (running) => (running ? start() : stop()), {
    immediate: true,
});
</script>

<template>
    <Head title="Analyses" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            title="Analyses"
            description="Each analysis has an AI model cross-check one vector against one target and score what it finds for manual review."
        />

        <section class="space-y-3 rounded-xl border p-4">
            <Heading
                variant="small"
                title="Run an analysis"
                description="Vectors and targets are listed by community votes. Leave the vector empty to check every active vector."
            />
            <RunAnalysisForm :vectors="vectors" :targets="targets" />
            <p v-if="cooldown" class="text-sm text-muted-foreground">
                To save tokens, pairings analyzed in the last {{ cooldown }}
                are skipped.
            </p>
            <p
                v-if="!vectors.length || !targets.length"
                class="text-sm text-muted-foreground"
            >
                Approve at least one vector and one target to run analyses.
            </p>
        </section>

        <p v-if="inProgress" class="text-sm text-muted-foreground">
            Analyses run on the queue worker; this list refreshes until they
            finish.
        </p>

        <AnalysisList :analyses="analyses.data" />

        <Pagination :paginator="analyses" />
    </div>
</template>
