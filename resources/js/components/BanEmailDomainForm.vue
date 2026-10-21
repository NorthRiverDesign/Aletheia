<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Ban } from '@lucide/vue';
import BannedEmailDomainController from '@/actions/App/Http/Controllers/BannedEmailDomainController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
</script>

<template>
    <Form
        v-bind="BannedEmailDomainController.store.form()"
        :options="{ preserveScroll: true }"
        reset-on-success
        class="grid gap-3 sm:grid-cols-2"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="banned-domain">Domain</Label>
            <Input
                id="banned-domain"
                name="domain"
                required
                autocomplete="off"
                placeholder="e.g. spam.example"
            />
            <InputError :message="errors.domain" />
        </div>
        <div class="grid gap-2">
            <Label for="banned-domain-reason">Reason</Label>
            <Input
                id="banned-domain-reason"
                name="reason"
                maxlength="500"
                placeholder="Optional, e.g. disposable addresses"
            />
            <InputError :message="errors.reason" />
        </div>
        <Label class="flex items-center gap-3 font-normal sm:col-span-2">
            <Checkbox name="ban_members" value="1" />
            <span>Also ban members already signed up with it</span>
        </Label>
        <div class="sm:col-span-2">
            <Button :disabled="processing" variant="outline">
                <Ban /> Ban domain
            </Button>
        </div>
    </Form>
</template>
