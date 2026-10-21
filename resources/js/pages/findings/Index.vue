<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import FindingList from '@/components/FindingList.vue';
import Heading from '@/components/Heading.vue';
import LeadDisclaimer from '@/components/LeadDisclaimer.vue';
import Pagination from '@/components/Pagination.vue';
import TabLinks from '@/components/TabLinks.vue';
import { NativeSelect } from '@/components/ui/native-select';
import { index } from '@/routes/findings';
import type {
    Finding,
    FindingStatus,
    Option,
    Paginated,
    Verdict,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Review queue', href: index() }],
    },
});

const props = defineProps<{
    findings: Paginated<Finding>;
    filters: { status: FindingStatus; min_score: number; checked: string };
    statusCounts: Partial<Record<FindingStatus, number>>;
    statuses: Option<FindingStatus>[];
    verdicts: Option<Verdict>[];
}>();

const minScore = ref(String(props.filters.min_score));
const checked = ref(props.filters.checked);

const tabs = computed(() =>
    props.statuses.map((status) => ({
        label: status.label,
        href: index({
            query: {
                status: status.value,
                min_score: props.filters.min_score || undefined,
                checked: props.filters.checked || undefined,
            },
        }),
        active: status.value === props.filters.status,
        count: props.statusCounts[status.value] ?? 0,
    })),
);

function applyFilters(): void {
    router.get(
        index().url,
        {
            status: props.filters.status,
            min_score: Number(minScore.value) || undefined,
            checked: checked.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch([minScore, checked], applyFilters);
</script>

<template>
    <Head title="Review queue" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            title="Review queue"
            description="AI-generated leads, highest risk first. Escalate the ones the records support and dismiss the rest."
        />

        <LeadDisclaimer />

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <TabLinks :tabs="tabs" />
            <NativeSelect
                v-model="checked"
                aria-label="AI fact check"
                class="sm:ml-auto sm:max-w-48"
            >
                <option value="">Any fact check</option>
                <option value="unchecked">Not fact-checked</option>
                <option
                    v-for="verdict in verdicts"
                    :key="verdict.value"
                    :value="verdict.value"
                >
                    {{ verdict.label }}
                </option>
            </NativeSelect>
            <NativeSelect
                v-model="minScore"
                aria-label="Minimum risk score"
                class="sm:max-w-48"
            >
                <option value="0">Any score</option>
                <option value="25">25 and up</option>
                <option value="50">50 and up</option>
                <option value="75">75 and up</option>
            </NativeSelect>
        </div>

        <FindingList
            :findings="findings.data"
            empty="Nothing here. Run analyses to fill the queue."
        />

        <Pagination :paginator="findings" />
    </div>
</template>
