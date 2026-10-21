<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ExternalLink } from '@lucide/vue';
import FindingController from '@/actions/App/Http/Controllers/FindingController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LeadDisclaimer from '@/components/LeadDisclaimer.vue';
import RiskScore from '@/components/RiskScore.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { show as showAnalysis } from '@/routes/analyses';
import { index } from '@/routes/findings';
import { show as showTarget } from '@/routes/targets';
import { show as showVector } from '@/routes/vectors';
import type { Finding, FindingStatus, Option } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Review queue', href: index() }],
    },
});

defineProps<{
    finding: Finding;
    statuses: Option<FindingStatus>[];
}>();

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '';
}
</script>

<template>
    <Head :title="finding.title" />

    <div class="flex flex-1 flex-col gap-8 p-4 lg:max-w-4xl">
        <div class="flex gap-4">
            <RiskScore :score="finding.risk_score" size="lg" />
            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge :status="finding.status" />
                    <Badge variant="outline"
                        >{{ finding.confidence.label }} confidence</Badge
                    >
                </div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ finding.title }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    <Link
                        v-if="finding.vector"
                        :href="showVector(finding.vector.id)"
                        class="underline-offset-4 hover:underline"
                        >{{ finding.vector.title }}</Link
                    >
                    ·
                    <Link
                        v-if="finding.target"
                        :href="showTarget(finding.target.id)"
                        class="underline-offset-4 hover:underline"
                        >{{ finding.target.name }}</Link
                    >
                    ·
                    <Link
                        :href="showAnalysis(finding.analysis_id)"
                        class="underline-offset-4 hover:underline"
                        >Analysis #{{ finding.analysis_id }}</Link
                    >
                </p>
            </div>
        </div>

        <LeadDisclaimer />

        <section class="space-y-2">
            <Heading variant="small" title="What was observed" />
            <p class="whitespace-pre-line">{{ finding.summary }}</p>
        </section>

        <section v-if="finding.rationale" class="space-y-2">
            <Heading variant="small" title="Why it fits the pattern" />
            <p class="whitespace-pre-line">{{ finding.rationale }}</p>
        </section>

        <section v-if="finding.entities.length" class="space-y-2">
            <Heading variant="small" title="Entities involved" />
            <div class="flex flex-wrap gap-2">
                <Badge
                    v-for="(entity, i) in finding.entities"
                    :key="i"
                    variant="secondary"
                    >{{ entity }}</Badge
                >
            </div>
        </section>

        <section class="space-y-2">
            <Heading variant="small" title="Evidence" />
            <ul v-if="finding.evidence.length" class="space-y-2">
                <li
                    v-for="(item, i) in finding.evidence"
                    :key="i"
                    class="rounded-lg border p-3 text-sm"
                >
                    <p>{{ item.description }}</p>
                    <a
                        v-if="item.url"
                        :href="item.url"
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        class="mt-1 inline-flex items-center gap-1 break-all text-muted-foreground underline underline-offset-4"
                    >
                        {{ item.url }} <ExternalLink class="size-3 shrink-0" />
                    </a>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                No sources were cited. Treat this lead with extra caution.
            </p>
        </section>

        <section v-if="finding.next_steps.length" class="space-y-2">
            <Heading variant="small" title="How to verify" />
            <ul class="list-disc space-y-1 pl-5">
                <li v-for="(step, i) in finding.next_steps" :key="i">
                    {{ step }}
                </li>
            </ul>
        </section>

        <section class="space-y-4 rounded-xl border p-4">
            <Heading
                variant="small"
                title="Review"
                :description="
                    finding.reviewer
                        ? `Last reviewed by ${finding.reviewer} on ${formatDate(finding.reviewed_at)}.`
                        : 'Record whether the records support this lead.'
                "
            />
            <Form
                v-bind="FindingController.update.form(finding.id)"
                class="space-y-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="status">Decision</Label>
                    <NativeSelect
                        id="status"
                        name="status"
                        :default-value="finding.status.value"
                        class="sm:max-w-60"
                    >
                        <option
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </NativeSelect>
                    <InputError :message="errors.status" />
                </div>
                <div class="grid gap-2">
                    <Label for="review_notes">Notes</Label>
                    <Textarea
                        id="review_notes"
                        name="review_notes"
                        :default-value="finding.review_notes ?? ''"
                        rows="4"
                        placeholder="What you checked and what it showed."
                    />
                    <InputError :message="errors.review_notes" />
                </div>
                <Button :disabled="processing">Save review</Button>
            </Form>
        </section>
    </div>
</template>
