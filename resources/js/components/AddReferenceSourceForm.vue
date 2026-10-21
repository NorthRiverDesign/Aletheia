<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import ReferenceSourceController from '@/actions/App/Http/Controllers/ReferenceSourceController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Without a targetId the link joins the national catalog used by every analysis.
 */
const props = defineProps<{
    targetId?: number;
}>();
</script>

<template>
    <Form
        v-bind="ReferenceSourceController.store.form()"
        :options="{ preserveScroll: true }"
        reset-on-success
        class="grid gap-3 sm:grid-cols-2"
        v-slot="{ errors, processing }"
    >
        <input
            v-if="props.targetId"
            type="hidden"
            name="target_id"
            :value="props.targetId"
        />
        <div class="grid gap-2">
            <Label for="reference-title">Name</Label>
            <Input
                id="reference-title"
                name="title"
                required
                placeholder="e.g. City checkbook"
            />
            <InputError :message="errors.title" />
        </div>
        <div class="grid gap-2">
            <Label for="reference-url">Link</Label>
            <Input
                id="reference-url"
                name="url"
                type="url"
                required
                placeholder="https://"
            />
            <InputError :message="errors.url" />
        </div>
        <div class="grid gap-2 sm:col-span-2">
            <Label for="reference-description">What it holds</Label>
            <Input
                id="reference-description"
                name="description"
                placeholder="Optional, e.g. vendor payments since 2015"
            />
            <InputError :message="errors.description" />
        </div>
        <div class="sm:col-span-2">
            <Button :disabled="processing" variant="outline">
                <Plus /> Add reference
            </Button>
        </div>
    </Form>
</template>
