<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    score: number;
    size?: 'sm' | 'lg';
}>();

const band = computed(() => {
    if (props.score >= 75) {
        return {
            label: 'High',
            tone: 'bg-red-600 text-white dark:bg-red-500',
        };
    }

    if (props.score >= 50) {
        return {
            label: 'Elevated',
            tone: 'bg-orange-500 text-white dark:bg-orange-500',
        };
    }

    if (props.score >= 25) {
        return {
            label: 'Moderate',
            tone: 'bg-amber-200 text-amber-950 dark:bg-amber-300',
        };
    }

    return { label: 'Low', tone: 'bg-muted text-muted-foreground' };
});
</script>

<template>
    <span
        :title="`Risk score ${props.score}/100 (${band.label})`"
        :class="
            cn(
                'inline-flex shrink-0 items-center justify-center rounded-md font-semibold tabular-nums',
                band.tone,
                props.size === 'lg'
                    ? 'h-12 min-w-12 px-2 text-xl'
                    : 'h-7 min-w-9 px-1.5 text-sm',
            )
        "
    >
        {{ props.score }}
    </span>
</template>
