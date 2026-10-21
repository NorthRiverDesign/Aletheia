<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import { ChevronUp } from '@lucide/vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    href: NonNullable<InertiaLinkProps['href']>;
    count: number;
    voted: boolean;
    disabled?: boolean;
}>();
</script>

<template>
    <Link
        :href="props.href"
        as="button"
        preserve-scroll
        :disabled="props.disabled"
        :aria-pressed="props.voted"
        :aria-label="props.voted ? 'Remove your upvote' : 'Upvote'"
        :class="
            cn(
                'flex w-12 shrink-0 flex-col items-center self-start rounded-md border px-2 py-1 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                props.voted
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'hover:bg-accent',
            )
        "
    >
        <ChevronUp class="size-4" />
        <span>{{ props.count }}</span>
    </Link>
</template>
