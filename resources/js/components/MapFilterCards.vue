<script setup lang="ts" generic="T extends string">
import { cn } from '@/lib/utils';

/** Stat cards that double as the map's filter; pressing the active card again shows everything. */
const props = defineProps<{
    options: {
        value: T;
        label: string;
        count: number;
        detail: string;
        tone: string;
    }[];
}>();

const filter = defineModel<T>({ required: true });

function toggle(value: T) {
    filter.value = filter.value === value ? props.options[0].value : value;
}
</script>

<template>
    <div
        role="group"
        aria-label="Show on map"
        class="grid grid-cols-2 gap-2 sm:grid-cols-4"
    >
        <button
            v-for="option in props.options"
            :key="option.value"
            type="button"
            :aria-pressed="filter === option.value"
            :class="
                cn(
                    'rounded-xl border p-3 text-left transition-colors',
                    filter === option.value
                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                        : 'hover:bg-accent/50',
                )
            "
            @click="toggle(option.value)"
        >
            <span
                class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
            >
                <span :class="cn('size-2 rounded-full', option.tone)" />
                {{ option.label }}
            </span>
            <span
                class="mt-1 block text-2xl font-semibold tracking-tight tabular-nums"
                >{{ option.count }}</span
            >
            <span class="block truncate text-xs text-muted-foreground">{{
                option.detail
            }}</span>
        </button>
    </div>
</template>
