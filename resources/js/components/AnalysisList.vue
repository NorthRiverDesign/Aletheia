<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import RiskScore from '@/components/RiskScore.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { show } from '@/routes/analyses';
import type { Analysis } from '@/types';

defineProps<{
    analyses: Analysis[];
    empty?: string;
}>();

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : '';
}
</script>

<template>
    <ul v-if="analyses.length" class="divide-y rounded-xl border">
        <li v-for="analysis in analyses" :key="analysis.id">
            <Link
                :href="show(analysis.id)"
                class="flex items-center gap-3 p-4 transition-colors hover:bg-accent/50"
            >
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">
                            {{ analysis.vector?.title ?? 'Deleted vector' }}
                        </span>
                        <span class="text-muted-foreground">×</span>
                        <span class="font-medium">
                            {{ analysis.target?.name ?? 'Deleted target' }}
                        </span>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDate(analysis.created_at) }}
                        <template v-if="analysis.requester">
                            · by {{ analysis.requester }}
                        </template>
                        <template v-if="analysis.findings_count !== null">
                            · {{ analysis.findings_count }}
                            {{
                                analysis.findings_count === 1
                                    ? 'finding'
                                    : 'findings'
                            }}
                        </template>
                    </p>
                </div>
                <RiskScore
                    v-if="analysis.max_risk_score !== null"
                    :score="analysis.max_risk_score"
                />
                <StatusBadge :status="analysis.status" />
            </Link>
        </li>
    </ul>
    <p v-else class="text-sm text-muted-foreground">
        {{ empty ?? 'No analyses yet.' }}
    </p>
</template>
