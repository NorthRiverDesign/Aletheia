<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AddReferenceSourceForm from '@/components/AddReferenceSourceForm.vue';
import Heading from '@/components/Heading.vue';
import ReferenceSourceList from '@/components/ReferenceSourceList.vue';
import { index } from '@/routes/reference-sources';
import type { ReferenceSource } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reference data', href: index() }],
    },
});

defineProps<{
    sources: ReferenceSource[];
    can: { manage: boolean };
}>();
</script>

<template>
    <Head title="Reference data" />

    <div class="flex flex-1 flex-col gap-6 p-4 lg:max-w-4xl">
        <Heading
            title="Reference data"
            description="Public datasets every analysis starts from, so the AI uses them directly instead of spending searches finding them. Datasets for a single place live on its target page."
        />

        <section v-if="can.manage" class="space-y-3 rounded-xl border p-4">
            <Heading
                variant="small"
                title="Add a national dataset"
                description="Useful for almost any target. It goes into every analysis prompt."
            />
            <AddReferenceSourceForm />
        </section>

        <ReferenceSourceList
            :sources="sources"
            :can-manage="can.manage"
            empty="No national datasets yet."
        />
    </div>
</template>
