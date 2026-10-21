<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import VectorController from '@/actions/App/Http/Controllers/VectorController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/vectors';
import type { Option, Vector } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vectors', href: index() }],
    },
});

const props = defineProps<{
    vector: Vector | null;
    categories: Option[];
}>();

const isAdmin = computed(() => usePage().props.auth.user.is_admin);

const action = computed(() =>
    props.vector
        ? VectorController.update.form(props.vector.id)
        : VectorController.store.form(),
);
</script>

<template>
    <Head :title="vector ? 'Edit vector' : 'Suggest a vector'" />

    <div class="flex flex-1 flex-col gap-6 p-4 lg:max-w-3xl">
        <Heading
            :title="vector ? 'Edit vector' : 'Suggest a vector'"
            :description="
                vector
                    ? 'Refine how this pattern is described and detected.'
                    : isAdmin
                      ? 'Describe a fraud pattern worth checking. It goes live immediately.'
                      : 'Describe a fraud pattern worth checking. Suggestions are reviewed before they are used in analyses.'
            "
        />

        <Form v-bind="action" class="space-y-6" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="title">Title</Label>
                <Input
                    id="title"
                    name="title"
                    :default-value="vector?.title"
                    required
                    placeholder="e.g. Daycares billing for inflated attendance"
                />
                <InputError :message="errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="category">Category</Label>
                <NativeSelect
                    id="category"
                    name="category"
                    :default-value="vector?.category.value ?? ''"
                    required
                >
                    <option value="" disabled>Choose a category…</option>
                    <option
                        v-for="option in categories"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </NativeSelect>
                <InputError :message="errors.category" />
            </div>

            <div class="grid gap-2">
                <Label for="summary">Summary</Label>
                <Input
                    id="summary"
                    name="summary"
                    :default-value="vector?.summary"
                    required
                    maxlength="500"
                    placeholder="One sentence describing the scheme"
                />
                <InputError :message="errors.summary" />
            </div>

            <div class="grid gap-2">
                <Label for="description">How it works</Label>
                <Textarea
                    id="description"
                    name="description"
                    :default-value="vector?.description"
                    required
                    rows="5"
                    placeholder="Who is involved, how the money moves, and why it goes unnoticed."
                />
                <InputError :message="errors.description" />
            </div>

            <div class="grid gap-2">
                <Label for="indicators">Red-flag indicators</Label>
                <Textarea
                    id="indicators"
                    name="indicators"
                    :default-value="vector?.indicators.join('\n')"
                    required
                    rows="5"
                    placeholder="One per line, e.g. Billed attendance exceeds licensed capacity"
                />
                <p class="text-xs text-muted-foreground">
                    One per line. These are what the analysis looks for.
                </p>
                <InputError :message="errors.indicators" />
            </div>

            <div class="grid gap-2">
                <Label for="data_sources">Where to look</Label>
                <Textarea
                    id="data_sources"
                    name="data_sources"
                    :default-value="vector?.data_sources.join('\n')"
                    rows="4"
                    placeholder="One per line, e.g. State childcare licensing inspection reports"
                />
                <p class="text-xs text-muted-foreground">
                    Optional. Public records that help confirm or rule out the
                    pattern, one per line.
                </p>
                <InputError :message="errors.data_sources" />
            </div>

            <Button :disabled="processing">
                {{
                    vector
                        ? 'Save changes'
                        : isAdmin
                          ? 'Add vector'
                          : 'Submit for review'
                }}
            </Button>
        </Form>
    </div>
</template>
