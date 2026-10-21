<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

const props = defineProps<{
    paginator: Paginated<unknown>;
}>();
</script>

<template>
    <nav
        v-if="props.paginator.last_page > 1"
        class="flex items-center justify-between gap-4 text-sm text-muted-foreground"
        aria-label="Pagination"
    >
        <span>
            {{ props.paginator.from }}–{{ props.paginator.to }} of
            {{ props.paginator.total }}
        </span>
        <div class="flex gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!props.paginator.prev_page_url"
                :as-child="!!props.paginator.prev_page_url"
            >
                <Link
                    v-if="props.paginator.prev_page_url"
                    :href="props.paginator.prev_page_url"
                    preserve-scroll
                    >Previous</Link
                >
                <template v-else>Previous</template>
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="!props.paginator.next_page_url"
                :as-child="!!props.paginator.next_page_url"
            >
                <Link
                    v-if="props.paginator.next_page_url"
                    :href="props.paginator.next_page_url"
                    preserve-scroll
                    >Next</Link
                >
                <template v-else>Next</template>
            </Button>
        </div>
    </nav>
</template>
