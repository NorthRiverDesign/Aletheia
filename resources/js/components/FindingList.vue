<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import RiskScore from '@/components/RiskScore.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { show } from '@/routes/findings';
import type { Finding } from '@/types';

defineProps<{
    findings: Finding[];
    empty?: string;
}>();
</script>

<template>
    <ul v-if="findings.length" class="divide-y rounded-xl border">
        <li v-for="finding in findings" :key="finding.id">
            <Link
                :href="show(finding.id)"
                class="flex items-start gap-3 p-4 transition-colors hover:bg-accent/50"
            >
                <RiskScore :score="finding.risk_score" />
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ finding.title }}</span>
                        <StatusBadge :status="finding.status" />
                    </div>
                    <p class="line-clamp-2 text-sm text-muted-foreground">
                        {{ finding.summary }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="finding.vector">{{
                            finding.vector.title
                        }}</template>
                        <template v-if="finding.vector && finding.target">
                            ·
                        </template>
                        <template v-if="finding.target">{{
                            finding.target.name
                        }}</template>
                        · {{ finding.confidence.label }} confidence
                    </p>
                </div>
            </Link>
        </li>
    </ul>
    <p v-else class="text-sm text-muted-foreground">
        {{ empty ?? 'No findings yet.' }}
    </p>
</template>
