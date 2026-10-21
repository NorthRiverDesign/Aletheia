<script setup lang="ts">
import { computed } from 'vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { Option, Verification } from '@/types';

const props = defineProps<{
    verification: Verification | null;
}>();

const badge = computed<Option | null>(() => {
    const verification = props.verification;

    if (!verification) {
        return null;
    }

    if (verification.verdict) {
        return verification.verdict;
    }

    if (['queued', 'running'].includes(verification.status.value)) {
        return { value: 'running', label: 'Fact-checking' };
    }

    return verification.status.value === 'failed'
        ? { value: 'inconclusive', label: 'Fact check failed' }
        : null;
});
</script>

<template>
    <StatusBadge v-if="badge" :status="badge" title="AI fact check" />
</template>
