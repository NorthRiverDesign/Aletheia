<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { BadgeCheck, ExternalLink, Trash2 } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as showAnalysis } from '@/routes/analyses';
import { destroy, verify } from '@/routes/reference-sources';
import type { ReferenceSource } from '@/types';

defineProps<{
    sources: ReferenceSource[];
    canManage: boolean;
    empty?: string;
}>();

function remove(source: ReferenceSource): void {
    if (
        confirm(
            `Remove "${source.title}"? Analyses will stop starting from it.`,
        )
    ) {
        router.delete(destroy(source.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <ul v-if="sources.length" class="divide-y rounded-xl border">
        <li
            v-for="source in sources"
            :key="source.id"
            class="flex flex-col gap-2 p-3 sm:flex-row sm:items-start"
        >
            <div class="min-w-0 flex-1 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        :href="source.url"
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        class="inline-flex items-center gap-1 font-medium underline-offset-4 hover:underline"
                    >
                        {{ source.title }}
                        <ExternalLink class="size-3 shrink-0" />
                    </a>
                    <Badge v-if="!source.verified" variant="outline">
                        Unverified
                    </Badge>
                </div>
                <p
                    v-if="source.description"
                    class="text-sm text-muted-foreground"
                >
                    {{ source.description }}
                </p>
                <p class="text-xs break-all text-muted-foreground">
                    {{ source.url }}
                </p>
                <p
                    v-if="canManage && source.origin.value === 'analysis'"
                    class="text-xs text-muted-foreground"
                >
                    {{ source.origin.label }}
                    <Link
                        v-if="source.analysis_id"
                        :href="showAnalysis(source.analysis_id)"
                        class="underline underline-offset-4"
                        >#{{ source.analysis_id }}</Link
                    >
                </p>
            </div>
            <div v-if="canManage" class="flex shrink-0 gap-2">
                <Button
                    v-if="!source.verified"
                    variant="outline"
                    size="sm"
                    @click="
                        router.patch(
                            verify(source.id).url,
                            {},
                            { preserveScroll: true },
                        )
                    "
                >
                    <BadgeCheck /> Verify
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    :aria-label="`Remove ${source.title}`"
                    @click="remove(source)"
                >
                    <Trash2 />
                </Button>
            </div>
        </li>
    </ul>
    <p v-else class="text-sm text-muted-foreground">
        {{ empty ?? 'No reference data yet.' }}
    </p>
</template>
