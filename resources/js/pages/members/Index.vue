<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Trash2, Undo2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import BanEmailDomainForm from '@/components/BanEmailDomainForm.vue';
import BanMemberDialog from '@/components/BanMemberDialog.vue';
import Heading from '@/components/Heading.vue';
import Pagination from '@/components/Pagination.vue';
import TabLinks from '@/components/TabLinks.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { destroy as destroyDomain } from '@/routes/banned-domains';
import { index, unban } from '@/routes/members';
import type {
    BannedEmailDomain,
    Member,
    MemberStatus,
    Paginated,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: index() }],
    },
});

const props = defineProps<{
    members: Paginated<Member>;
    filters: { status: MemberStatus; search: string };
    counts: Record<MemberStatus, number>;
    domains: BannedEmailDomain[];
}>();

const search = ref(props.filters.search);

const tabs = computed(() =>
    (['active', 'banned'] as const).map((status) => ({
        label: status === 'active' ? 'Active' : 'Banned',
        href: index({
            query: { status: status === 'active' ? undefined : status },
        }),
        active: status === props.filters.status,
        count: props.counts[status],
    })),
);

function applyFilters(): void {
    router.get(
        index().url,
        {
            status:
                props.filters.status === 'active'
                    ? undefined
                    : props.filters.status,
            search: search.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString() : '—';
}

function banSummary(member: Member): string {
    const banned = `Banned ${formatDate(member.banned_at)}`;

    return member.ban_reason ? `${banned}: ${member.ban_reason}` : banned;
}

function liftBan(member: Member): void {
    if (confirm(`Let ${member.name} sign in again?`)) {
        router.delete(unban(member.id).url, { preserveScroll: true });
    }
}

function allowDomain(domain: BannedEmailDomain): void {
    if (
        confirm(
            `Allow sign-ups from ${domain.domain} again? Members banned along with it stay banned.`,
        )
    ) {
        router.delete(destroyDomain(domain.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Members" />

    <div class="flex flex-1 flex-col gap-6 p-4 lg:max-w-4xl">
        <Heading
            title="Members"
            description="Ban accounts that spam suggestions or game the votes, and block the email domains bots sign up from."
        />

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <TabLinks :tabs="tabs" />
            <form class="sm:ml-auto" @submit.prevent="applyFilters">
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search name or email…"
                    aria-label="Search members"
                    class="sm:w-64"
                />
            </form>
        </div>

        <ul v-if="members.data.length" class="divide-y rounded-xl border">
            <li
                v-for="member in members.data"
                :key="member.id"
                class="flex flex-col gap-2 p-3 sm:flex-row sm:items-start"
            >
                <div class="min-w-0 flex-1 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ member.name }}</span>
                        <Badge v-if="member.is_admin" variant="secondary">
                            Admin
                        </Badge>
                        <Badge v-if="!member.email_verified" variant="outline">
                            Unverified
                        </Badge>
                    </div>
                    <p class="text-sm break-all text-muted-foreground">
                        {{ member.email }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Joined {{ formatDate(member.created_at) }} ·
                        {{ member.vectors_count }} vectors ·
                        {{ member.targets_count }} targets ·
                        {{ member.votes_count }} votes
                    </p>
                    <p
                        v-if="member.banned_at"
                        class="text-xs text-red-600 dark:text-red-400"
                    >
                        {{ banSummary(member) }}
                    </p>
                </div>
                <div v-if="member.can_ban" class="flex shrink-0 gap-2">
                    <Button
                        v-if="member.banned_at"
                        variant="outline"
                        size="sm"
                        @click="liftBan(member)"
                    >
                        <Undo2 /> Lift ban
                    </Button>
                    <BanMemberDialog v-else :member="member" />
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">
            {{
                filters.status === 'banned'
                    ? 'Nobody is banned.'
                    : 'No members match.'
            }}
        </p>

        <Pagination :paginator="members" />

        <section class="space-y-3 rounded-xl border p-4">
            <Heading
                variant="small"
                title="Banned email domains"
                description="Nobody can sign up with, or switch to, an address at these domains or their subdomains."
            />
            <BanEmailDomainForm />

            <ul v-if="domains.length" class="divide-y rounded-lg border">
                <li
                    v-for="domain in domains"
                    :key="domain.id"
                    class="flex items-start gap-2 p-3"
                >
                    <div class="min-w-0 flex-1 space-y-1">
                        <p class="font-medium break-all">
                            {{ domain.domain }}
                        </p>
                        <p
                            v-if="domain.reason"
                            class="text-sm text-muted-foreground"
                        >
                            {{ domain.reason }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Banned {{ formatDate(domain.created_at) }}
                            <template v-if="domain.banned_by">
                                by {{ domain.banned_by }}</template
                            >
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        :aria-label="`Allow ${domain.domain} again`"
                        @click="allowDomain(domain)"
                    >
                        <Trash2 />
                    </Button>
                </li>
            </ul>
        </section>
    </div>
</template>
