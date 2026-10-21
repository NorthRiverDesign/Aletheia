<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Ban } from '@lucide/vue';
import { ref } from 'vue';
import MemberController from '@/actions/App/Http/Controllers/MemberController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Member } from '@/types';

const props = defineProps<{
    member: Member;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm"><Ban /> Ban</Button>
        </DialogTrigger>
        <DialogContent>
            <Form
                v-bind="MemberController.ban.form(props.member.id)"
                :options="{ preserveScroll: true }"
                class="space-y-6"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <DialogHeader class="space-y-3">
                    <DialogTitle>Ban {{ props.member.name }}?</DialogTitle>
                    <DialogDescription>
                        They are signed out at once and can't sign back in.
                        Their upvotes are removed and suggestions still awaiting
                        review are rejected. Lifting the ban later doesn't bring
                        those back.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label :for="`ban-reason-${props.member.id}`">Reason</Label>
                    <Input
                        :id="`ban-reason-${props.member.id}`"
                        name="reason"
                        maxlength="500"
                        placeholder="Optional, e.g. spam suggestions"
                    />
                    <InputError :message="errors.reason" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        Ban member
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
