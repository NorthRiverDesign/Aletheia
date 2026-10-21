<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ExternalLink, Pencil, Trash2 } from '@lucide/vue';
import AnalysisList from '@/components/AnalysisList.vue';
import FindingList from '@/components/FindingList.vue';
import Heading from '@/components/Heading.vue';
import RunAnalysisForm from '@/components/RunAnalysisForm.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import VoteButton from '@/components/VoteButton.vue';
import { destroy, edit, index, status, vote } from '@/routes/targets';
import type {
    Analysis,
    Finding,
    Option,
    PickerOption,
    ProposalAbilities,
    ProposalStatus,
    Target,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Targets', href: index() }],
    },
});

const props = defineProps<{
    target: Target;
    can: ProposalAbilities;
    statuses: Option<ProposalStatus>[];
    findings: Finding[];
    analyses: Analysis[];
    vectors: PickerOption[];
}>();

function setStatus(value: ProposalStatus): void {
    router.patch(
        status(props.target.id).url,
        { status: value },
        { preserveScroll: true },
    );
}

function remove(): void {
    if (
        confirm(
            'Delete this target? Its analyses and findings will be deleted too.',
        )
    ) {
        router.delete(destroy(props.target.id).url);
    }
}
</script>

<template>
    <Head :title="target.name" />

    <div class="flex flex-1 flex-col gap-8 p-4 lg:max-w-4xl">
        <div class="flex gap-4">
            <VoteButton
                :href="vote(target.id)"
                :count="target.votes_count"
                :voted="target.has_voted"
                :disabled="!can.vote"
            />
            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="secondary">{{ target.type.label }}</Badge>
                    <StatusBadge :status="target.status" />
                </div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ target.name }}
                </h1>
                <p v-if="target.region" class="text-muted-foreground">
                    {{ target.region }}
                </p>
                <a
                    v-if="target.website"
                    :href="target.website"
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    class="inline-flex items-center gap-1 text-sm underline underline-offset-4"
                >
                    {{ target.website }} <ExternalLink class="size-3" />
                </a>
                <p v-if="target.creator" class="text-xs text-muted-foreground">
                    Suggested by {{ target.creator }}
                </p>
            </div>
        </div>

        <div
            v-if="can.update || can.moderate || can.delete"
            class="flex flex-wrap gap-2"
        >
            <Button v-if="can.update" variant="outline" size="sm" as-child>
                <Link :href="edit(target.id)"><Pencil /> Edit</Link>
            </Button>
            <template v-if="can.moderate">
                <Button
                    v-for="option in statuses.filter(
                        (option) => option.value !== target.status.value,
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

        <section v-if="target.description" class="space-y-2">
            <Heading variant="small" title="Why look here" />
            <p class="whitespace-pre-line">{{ target.description }}</p>
        </section>

        <template v-if="can.analyze">
            <section
                v-if="target.status.value === 'active'"
                class="space-y-3 rounded-xl border p-4"
            >
                <Heading
                    variant="small"
                    title="Cross-check vectors"
                    description="Queue an AI analysis of this target against one vector, or every active vector."
                />
                <RunAnalysisForm :target-id="target.id" :vectors="vectors" />
            </section>

            <section class="space-y-3">
                <Heading variant="small" title="Top findings" />
                <FindingList
                    :findings="findings"
                    empty="No findings for this target yet."
                />
            </section>

            <section class="space-y-3">
                <Heading variant="small" title="Recent analyses" />
                <AnalysisList
                    :analyses="analyses"
                    empty="This target has not been analyzed yet."
                />
            </section>
        </template>
    </div>
</template>
