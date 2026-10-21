<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

export type TabLink = {
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    active: boolean;
    count?: number;
};

defineProps<{
    tabs: TabLink[];
}>();
</script>

<template>
    <nav class="flex flex-wrap gap-1 rounded-lg bg-muted p-1 text-sm">
        <Link
            v-for="tab in tabs"
            :key="tab.label"
            :href="tab.href"
            preserve-state
            :class="
                cn(
                    'flex items-center gap-1.5 rounded-md px-3 py-1.5 font-medium transition-colors',
                    tab.active
                        ? 'bg-background text-foreground shadow-xs'
                        : 'text-muted-foreground hover:text-foreground',
                )
            "
        >
            {{ tab.label }}
            <span
                v-if="tab.count !== undefined"
                class="text-xs text-muted-foreground tabular-nums"
                >{{ tab.count }}</span
            >
        </Link>
    </nav>
</template>
