<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import AnalysisController from '@/actions/App/Http/Controllers/AnalysisController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import type { PickerOption } from '@/types';

/**
 * Pass a fixed vectorId or targetId to pin one side of the pairing; the other
 * side is picked from the options, which arrive sorted by community votes.
 */
const props = defineProps<{
    vectors?: PickerOption[];
    targets?: PickerOption[];
    vectorId?: number;
    targetId?: number;
}>();
</script>

<template>
    <Form
        v-bind="AnalysisController.store.form()"
        class="flex flex-col gap-4 sm:flex-row sm:items-end"
        v-slot="{ errors, processing }"
    >
        <input
            v-if="props.targetId"
            type="hidden"
            name="target_id"
            :value="props.targetId"
        />
        <div v-else class="grid flex-1 gap-2">
            <Label for="analysis-target">Target</Label>
            <NativeSelect
                id="analysis-target"
                name="target_id"
                default-value=""
                required
            >
                <option value="" disabled>Choose a target…</option>
                <option
                    v-for="target in props.targets ?? []"
                    :key="target.id"
                    :value="target.id"
                >
                    {{ target.label }} ({{ target.votes_count }} votes)
                </option>
            </NativeSelect>
            <InputError :message="errors.target_id" />
        </div>

        <input
            v-if="props.vectorId"
            type="hidden"
            name="vector_id"
            :value="props.vectorId"
        />
        <div v-else class="grid flex-1 gap-2">
            <Label for="analysis-vector">Vector</Label>
            <NativeSelect
                id="analysis-vector"
                name="vector_id"
                default-value=""
            >
                <option value="">All active vectors</option>
                <option
                    v-for="vector in props.vectors ?? []"
                    :key="vector.id"
                    :value="vector.id"
                >
                    {{ vector.label }} ({{ vector.votes_count }} votes)
                </option>
            </NativeSelect>
            <InputError :message="errors.vector_id" />
        </div>

        <InputError v-if="props.targetId" :message="errors.target_id" />
        <InputError v-if="props.vectorId" :message="errors.vector_id" />

        <Button :disabled="processing">
            <Sparkles />
            Run analysis
        </Button>
    </Form>
</template>
