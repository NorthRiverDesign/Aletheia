<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TabLinks from '@/components/TabLinks.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import VoteButton from '@/components/VoteButton.vue';
import { create, index, show, vote } from '@/routes/vectors';
import type { Option, Paginated, ProposalStatus, Vector } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vectors', href: index() }],
    },
});

const props = defineProps<{
    vectors: Paginated<Vector>;
    filters: {
        status: ProposalStatus;
        category: string | null;
        search: string;
        sort: 'votes' | 'newest';
    };
    statusCounts: Partial<Record<ProposalStatus, number>>;
    categories: Option[];
    statuses: Option<ProposalStatus>[];
}>();

const search = ref(props.filters.search);
const category = ref(props.filters.category ?? '');
const sort = ref(props.filters.sort);

const tabs = computed(() =>
    props.statuses.map((status) => ({
        label: status.label,
        href: index({ query: { status: status.value } }),
        active: status.value === props.filters.status,
        count: props.statusCounts[status.value] ?? 0,
    })),
);

function applyFilters(): void {
    router.get(
        index().url,
        {
            status: props.filters.status,
            category: category.value || undefined,
            search: search.value || undefined,
            sort: sort.value === 'votes' ? undefined : sort.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch([category, sort], applyFilters);
</script>

<template>
    <Head title="Vectors" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Fraud vectors"
                description="Known patterns of fraud to look for. Upvote the ones you want checked first, or suggest a new one."
            />
            <Button as-child>
                <Link :href="create()"><Plus /> Suggest a vector</Link>
            </Button>
        </div>

        <TabLinks :tabs="tabs" />

        <form
            class="flex flex-col gap-2 sm:flex-row"
            @submit.prevent="applyFilters"
        >
            <Input
                v-model="search"
                type="search"
                placeholder="Search vectors…"
                aria-label="Search vectors"
                class="sm:max-w-xs"
            />
            <NativeSelect
                v-model="category"
                aria-label="Category"
                class="sm:max-w-60"
            >
                <option value="">All categories</option>
                <option
                    v-for="option in categories"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </NativeSelect>
            <NativeSelect v-model="sort" aria-label="Sort" class="sm:max-w-40">
                <option value="votes">Most votes</option>
                <option value="newest">Newest</option>
            </NativeSelect>
        </form>

        <ul v-if="vectors.data.length" class="space-y-3">
            <li
                v-for="vector in vectors.data"
                :key="vector.id"
                class="flex gap-4 rounded-xl border p-4"
            >
                <VoteButton
                    :href="vote(vector.id)"
                    :count="vector.votes_count"
                    :voted="vector.has_voted"
                    :disabled="
                        vector.status.value === 'rejected' ||
                        vector.status.value === 'archived'
                    "
                />
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="show(vector.id)"
                            class="font-medium hover:underline"
                        >
                            {{ vector.title }}
                        </Link>
                        <Badge variant="secondary">{{
                            vector.category.label
                        }}</Badge>
                        <StatusBadge
                            v-if="vector.status.value !== 'active'"
                            :status="vector.status"
                        />
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ vector.summary }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ vector.indicators.length }} red-flag
                        {{
                            vector.indicators.length === 1
                                ? 'indicator'
                                : 'indicators'
                        }}
                    </p>
                </div>
            </li>
        </ul>
        <p
            v-else
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            No vectors match. Know a pattern worth checking?
            <Link :href="create()" class="underline">Suggest it</Link>.
        </p>

        <Pagination :paginator="vectors" />
    </div>
</template>
