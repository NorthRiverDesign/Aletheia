<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    CircleAlert,
    CircleCheck,
    CircleQuestionMark,
    CircleX,
    ExternalLink,
    LoaderCircle,
    ShieldCheck,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import VerificationController from '@/actions/App/Http/Controllers/VerificationController';
import Heading from '@/components/Heading.vue';
import RiskScore from '@/components/RiskScore.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ClaimStatus, Verification } from '@/types';

const props = defineProps<{
    findingId: number;
    verification: Verification | null;
}>();

const checking = computed(() =>
    ['queued', 'running'].includes(props.verification?.status.value ?? ''),
);

const claimStatuses: Record<
    ClaimStatus,
    { label: string; icon: Component; tone: string }
> = {
    confirmed: {
        label: 'Confirmed',
        icon: CircleCheck,
        tone: 'text-emerald-600 dark:text-emerald-400',
    },
    unconfirmed: {
        label: 'Unconfirmed',
        icon: CircleQuestionMark,
        tone: 'text-amber-600 dark:text-amber-400',
    },
    incorrect: {
        label: 'Incorrect',
        icon: CircleX,
        tone: 'text-red-600 dark:text-red-400',
    },
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '';
}
</script>

<template>
    <section class="space-y-4 rounded-xl border p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="AI fact check"
                description="A second AI pass opens the cited sources, checks each claim, and looks for errors in this lead."
            />
            <Form
                v-if="!checking"
                v-bind="VerificationController.store.form(findingId)"
                v-slot="{ processing }"
            >
                <Button
                    variant="outline"
                    size="sm"
                    class="shrink-0"
                    :disabled="processing"
                >
                    <ShieldCheck />
                    {{ verification ? 'Check again' : 'Fact-check this lead' }}
                </Button>
            </Form>
        </div>

        <Alert v-if="checking">
            <LoaderCircle class="animate-spin" />
            <AlertTitle>{{
                verification?.status.value === 'queued'
                    ? 'Waiting for a queue worker'
                    : 'Fact-checking'
            }}</AlertTitle>
            <AlertDescription>
                This page refreshes automatically. Checking sources on the web
                can take a few minutes.
            </AlertDescription>
        </Alert>

        <Alert v-else-if="verification?.error" variant="destructive">
            <CircleAlert />
            <AlertTitle>The fact check failed</AlertTitle>
            <AlertDescription>{{ verification.error }}</AlertDescription>
        </Alert>

        <template v-else-if="verification?.verdict">
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge :status="verification.verdict" />
                <span
                    v-if="verification.risk_score !== null"
                    class="inline-flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    Re-scored
                    <RiskScore :score="verification.risk_score" />
                </span>
                <Badge v-if="verification.confidence" variant="outline"
                    >{{ verification.confidence.label }} confidence</Badge
                >
            </div>

            <p v-if="verification.summary" class="whitespace-pre-line">
                {{ verification.summary }}
            </p>

            <div v-if="verification.issues.length" class="space-y-2">
                <h3 class="text-sm font-medium">Problems found</h3>
                <ul class="list-disc space-y-1 pl-5 text-sm">
                    <li v-for="(issue, i) in verification.issues" :key="i">
                        {{ issue }}
                    </li>
                </ul>
            </div>

            <div v-if="verification.claims.length" class="space-y-2">
                <h3 class="text-sm font-medium">Claims checked</h3>
                <ul class="space-y-2">
                    <li
                        v-for="(claim, i) in verification.claims"
                        :key="i"
                        class="flex gap-2 rounded-lg border p-3 text-sm"
                    >
                        <component
                            :is="claimStatuses[claim.status].icon"
                            :class="[
                                'mt-0.5 size-4 shrink-0',
                                claimStatuses[claim.status].tone,
                            ]"
                        />
                        <div class="min-w-0 space-y-1">
                            <p>
                                <span class="font-medium"
                                    >{{
                                        claimStatuses[claim.status].label
                                    }}:</span
                                >
                                {{ claim.claim }}
                            </p>
                            <p v-if="claim.note" class="text-muted-foreground">
                                {{ claim.note }}
                            </p>
                            <a
                                v-if="claim.url"
                                :href="claim.url"
                                target="_blank"
                                rel="noopener noreferrer nofollow"
                                class="inline-flex items-center gap-1 break-all text-muted-foreground underline underline-offset-4"
                            >
                                {{ claim.url }}
                                <ExternalLink class="size-3 shrink-0" />
                            </a>
                        </div>
                    </li>
                </ul>
            </div>

            <p class="text-xs text-muted-foreground">
                Checked {{ formatDate(verification.completed_at) }}. The fact
                check is AI-generated too: confirm what matters against the
                records yourself.
            </p>
        </template>
    </section>
</template>
