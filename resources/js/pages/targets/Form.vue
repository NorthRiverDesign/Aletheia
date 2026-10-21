<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TargetController from '@/actions/App/Http/Controllers/TargetController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LocationPicker from '@/components/LocationPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/targets';
import type { Option, Target } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Targets', href: index() }],
    },
});

const props = defineProps<{
    target: Target | null;
    types: Option[];
}>();

const isAdmin = computed(() => usePage().props.auth.user.is_admin);

const action = computed(() =>
    props.target
        ? TargetController.update.form(props.target.id)
        : TargetController.store.form(),
);
</script>

<template>
    <Head :title="target ? 'Edit target' : 'Suggest a target'" />

    <div class="flex flex-1 flex-col gap-6 p-4 lg:max-w-3xl">
        <Heading
            :title="target ? 'Edit target' : 'Suggest a target'"
            :description="
                target
                    ? 'Keep the details accurate so analyses look in the right place.'
                    : isAdmin
                      ? 'Add a place or program to examine. It goes live immediately.'
                      : 'Suggest a place or program to examine. Suggestions are reviewed before they are analyzed.'
            "
        />

        <Form v-bind="action" class="space-y-6" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="target?.name"
                    required
                    placeholder="e.g. City of Minneapolis"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="type">Type</Label>
                <NativeSelect
                    id="type"
                    name="type"
                    :default-value="target?.type.value ?? ''"
                    required
                >
                    <option value="" disabled>Choose a type…</option>
                    <option
                        v-for="option in types"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.type" />
            </div>

            <div class="grid gap-2">
                <Label for="region">Region</Label>
                <Input
                    id="region"
                    name="region"
                    :default-value="target?.region ?? ''"
                    placeholder="e.g. Minnesota"
                />
                <InputError :message="errors.region" />
            </div>

            <LocationPicker
                :latitude="target?.latitude ?? null"
                :longitude="target?.longitude ?? null"
                :errors="errors"
            />

            <div class="grid gap-2">
                <Label for="website">Website</Label>
                <Input
                    id="website"
                    name="website"
                    type="url"
                    :default-value="target?.website ?? ''"
                    placeholder="https://"
                />
                <InputError :message="errors.website" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Why look here</Label>
                <Textarea
                    id="description"
                    name="description"
                    :default-value="target?.description ?? ''"
                    rows="4"
                    placeholder="Optional. Budgets, programs, news reports, or tips that make this worth examining."
                />
                <InputError :message="errors.description" />
            </div>

            <Button :disabled="processing">
                {{
                    target
                        ? 'Save changes'
                        : isAdmin
                          ? 'Add target'
                          : 'Submit for review'
                }}
            </Button>
        </Form>
    </div>
</template>
