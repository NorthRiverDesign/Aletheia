<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import AnalysisList from '@/components/AnalysisList.vue';
import FindingList from '@/components/FindingList.vue';
import Heading from '@/components/Heading.vue';
import RunAnalysisForm from '@/components/RunAnalysisForm.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import VoteButton from '@/components/VoteButton.vue';
import { destroy, edit, index, status, vote } from '@/routes/vectors';
import type {
    Analysis,
    Finding,
    Option,
    PickerOption,
    ProposalAbilities,
    ProposalStatus,
    Vector,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vectors', href: index() }],
    },
});

const props = defineProps<{
    vector: Vector;
    can: ProposalAbilities;
    statuses: Option<ProposalStatus>[];
    findings: Finding[];
    analyses: Analysis[];
    targets: PickerOption[];
}>();

function setStatus(value: ProposalStatus): void {
    router.patch(
        status(props.vector.id).url,
        { status: value },
        { preserveScroll: true },
    );
}

function remove(): void {
    if (
        confirm(
            'Delete this vector? Its analyses and findings will be deleted too.',
        )
    ) {
        router.delete(destroy(props.vector.id).url);
    }
}
</script>

<template>
    <Head :title="vector.title" />

    <div class="flex flex-1 flex-col gap-8 p-4 lg:max-w-4xl">
        <div class="flex gap-4">
            <VoteButton
                :href="vote(vector.id)"
                :count="vector.votes_count"
                :voted="vector.has_voted"
                :disabled="!can.vote"
            />
            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">{{
                        vector.category.label
                    }}</Badge>
                    <StatusBadge :status="vector.status" />
                </div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ vector.title }}
                </h1>
                <p class="text-muted-foreground">{{ vector.summary }}</p>
                <p v-if="vector.creator" class="text-xs text-muted-foreground">
                    Suggested by {{ vector.creator }}
                </p>
            </div>
        </div>

        <div
            v-if="can.update || can.moderate || can.delete"
            class="flex flex-wrap gap-2"
        >
            <Button v-if="can.update" variant="outline" size="sm" as-child>
                <Link :href="edit(vector.id)"><Pencil /> Edit</Link>
            </Button>
            <template v-if="can.moderate">
                <Button
                    v-for="option in statuses.filter(
                        (option) => option.value !== vector.status.value,
                    )"
                    :key="option.value"
                    variant="outline"
                    size="sm"
                    @click="setStatus(option.value)"
                >
                    Mark {{ option.label.toLowerCase() }}
                </Button>
            </template>
            <Button
                v-if="can.delete"
                variant="destructive"
                size="sm"
                @click="remove"
            >
                <Trash2 /> Delete
            </Button>
        </div>

        <section class="space-y-2">
            <Heading variant="small" title="How it works" />
            <p class="whitespace-pre-line">{{ vector.description }}</p>
        </section>

        <section class="space-y-2">
            <Heading variant="small" title="Red-flag indicators" />
            <ul class="list-disc space-y-1 pl-5">
                <li v-for="(item, i) in vector.indicators" :key="i">
                    {{ item }}
                </li>
            </ul>
        </section>

        <section v-if="vector.data_sources.length" class="space-y-2">
            <Heading variant="small" title="Where to look" />
            <ul class="list-disc space-y-1 pl-5">
                <li v-for="(item, i) in vector.data_sources" :key="i">
                    {{ item }}
                </li>
            </ul>
        </section>

        <template v-if="can.analyze">
            <section
                v-if="vector.status.value === 'active'"
                class="space-y-3 rounded-xl border p-4"
            >
                <Heading
                    variant="small"
                    title="Cross-check against a target"
                    description="Queue an AI analysis of this vector against one of the active targets."
                />
                <RunAnalysisForm :vector-id="vector.id" :targets="targets" />
            </section>

            <section class="space-y-3">
                <Heading variant="small" title="Top findings" />
                <FindingList
                    :findings="findings"
                    empty="No findings for this vector yet."
                />
            </section>

            <section class="space-y-3">
                <Heading variant="small" title="Recent analyses" />
                <AnalysisList
                    :analyses="analyses"
                    empty="This vector has not been analyzed yet."
                />
            </section>
        </template>
    </div>
</template>
