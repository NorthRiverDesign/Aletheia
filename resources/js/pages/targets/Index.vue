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
import { create, index, show, vote } from '@/routes/targets';
import type { Option, Paginated, ProposalStatus, Target } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Targets', href: index() }],
    },
});

const props = defineProps<{
    targets: Paginated<Target>;
    filters: {
        status: ProposalStatus;
        type: string | null;
        search: string;
        sort: 'votes' | 'newest';
    };
    statusCounts: Partial<Record<ProposalStatus, number>>;
    types: Option[];
    statuses: Option<ProposalStatus>[];
}>();

const search = ref(props.filters.search);
const type = ref(props.filters.type ?? '');
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
            type: type.value || undefined,
            search: search.value || undefined,
            sort: sort.value === 'votes' ? undefined : sort.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch([type, sort], applyFilters);
</script>

<template>
    <Head title="Targets" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Targets"
                description="Cities, counties, agencies, and programs to examine. Upvote where you want the platform to look next."
            />
            <Button as-child>
                <Link :href="create()"><Plus /> Suggest a target</Link>
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
                placeholder="Search by name or region…"
                aria-label="Search targets"
                class="sm:max-w-xs"
            />
            <NativeSelect v-model="type" aria-label="Type" class="sm:max-w-52">
                <option value="">All types</option>
                <option
                    v-for="option in types"
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

        <ul v-if="targets.data.length" class="space-y-3">
            <li
                v-for="target in targets.data"
                :key="target.id"
                class="flex gap-4 rounded-xl border p-4"
            >
                <VoteButton
                    :href="vote(target.id)"
                    :count="target.votes_count"
                    :voted="target.has_voted"
                    :disabled="
                        target.status.value === 'rejected' ||
                        target.status.value === 'archived'
                    "
                />
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="show(target.id)"
                            class="font-medium hover:underline"
                        >
                            {{ target.name }}
                        </Link>
                        <Badge variant="secondary">{{
                            target.type.label
                        }}</Badge>
                        <StatusBadge
                            v-if="target.status.value !== 'active'"
                            :status="target.status"
                        />
                    </div>
                    <p
                        v-if="target.region"
                        class="text-sm text-muted-foreground"
                    >
                        {{ target.region }}
                    </p>
                    <p
                        v-if="target.description"
                        class="line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ target.description }}
                    </p>
                </div>
            </li>
        </ul>
        <p
            v-else
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            No targets match. Somewhere that deserves a closer look?
            <Link :href="create()" class="underline">Suggest it</Link>.
        </p>

        <Pagination :paginator="targets" />
    </div>
</template>
