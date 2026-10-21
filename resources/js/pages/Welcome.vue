<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronUp,
    ClipboardCheck,
    Crosshair,
    MapPin,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';

const name = usePage().props.name;

const steps = [
    {
        icon: Crosshair,
        title: 'Suggest vectors',
        description:
            'Describe a fraud pattern worth checking, like billing for care that was never delivered.',
        tint: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
    },
    {
        icon: MapPin,
        title: 'Point to targets',
        description:
            'Name the agencies, programs and places where public money is spent.',
        tint: 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    },
    {
        icon: ChevronUp,
        title: 'Upvote together',
        description:
            'Vote for what matters most, so the community checks it first.',
        tint: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
    },
    {
        icon: ClipboardCheck,
        title: 'Review the leads',
        description:
            'AI cross-checks vectors against targets into scored leads for people to review.',
        tint: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    },
];
</script>

<template>
    <Head title="Welcome" />
    <div
        class="relative flex min-h-svh flex-col overflow-hidden bg-linear-to-b from-blue-50 via-background to-background dark:from-blue-950/40"
    >
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -top-48 left-1/2 size-[40rem] -translate-x-1/2 rounded-full bg-blue-400/20 blur-3xl dark:bg-blue-500/10"
        />

        <header
            class="relative mx-auto flex w-full max-w-5xl items-center justify-between gap-4 p-6"
        >
            <Link
                :href="home()"
                class="flex items-center gap-2.5 text-lg font-semibold tracking-tight"
            >
                <AppLogoIcon class="size-9 drop-shadow-sm" />
                {{ name }}
            </Link>
            <nav class="flex items-center gap-2">
                <Button
                    v-if="$page.props.auth.user"
                    as-child
                    class="rounded-full"
                >
                    <Link :href="dashboard()">Dashboard</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child class="rounded-full">
                        <Link :href="login()">Log in</Link>
                    </Button>
                    <Button as-child class="rounded-full">
                        <Link :href="register()">Sign up</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main
            class="relative mx-auto flex w-full max-w-5xl flex-1 flex-col items-center px-6 pt-8 pb-16 text-center lg:pt-16"
        >
            <AppLogoIcon class="size-20 drop-shadow-xl sm:size-24" />
            <span
                class="mt-8 rounded-full bg-secondary px-3 py-1 text-sm font-medium text-secondary-foreground"
            >
                Aletheia: Greek for truth, unconcealed
            </span>
            <h1
                class="mt-6 max-w-3xl text-4xl font-bold tracking-tight text-balance sm:text-6xl"
            >
                Bring the truth about public spending to light.
            </h1>
            <p class="mt-6 max-w-2xl text-lg text-pretty text-muted-foreground">
                Find fraud in public spending together. Suggest the patterns
                worth checking and the places to look, upvote what matters most,
                and turn them into scored leads for careful human review.
            </p>
            <div class="mt-10 flex flex-wrap justify-center gap-3">
                <Button
                    v-if="$page.props.auth.user"
                    as-child
                    size="lg"
                    class="h-12 rounded-full px-7 text-base shadow-lg shadow-primary/25"
                >
                    <Link :href="dashboard()">
                        Open your dashboard
                        <ArrowRight />
                    </Link>
                </Button>
                <template v-else>
                    <Button
                        as-child
                        size="lg"
                        class="h-12 rounded-full px-7 text-base shadow-lg shadow-primary/25"
                    >
                        <Link :href="register()">
                            Get started
                            <ArrowRight />
                        </Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="outline"
                        class="h-12 rounded-full px-7 text-base"
                    >
                        <Link :href="login()">Log in</Link>
                    </Button>
                </template>
            </div>

            <section
                aria-label="How it works"
                class="mt-20 grid w-full gap-4 text-left sm:grid-cols-2 lg:grid-cols-4"
            >
                <div
                    v-for="(step, index) in steps"
                    :key="step.title"
                    class="rounded-2xl border bg-card p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="flex size-12 items-center justify-center rounded-2xl"
                            :class="step.tint"
                        >
                            <component :is="step.icon" class="size-6" />
                        </span>
                        <span
                            class="flex size-7 items-center justify-center rounded-full bg-muted text-sm font-semibold text-muted-foreground tabular-nums"
                        >
                            {{ index + 1 }}
                        </span>
                    </div>
                    <h2 class="mt-5 font-semibold">{{ step.title }}</h2>
                    <p class="mt-1.5 text-sm text-muted-foreground">
                        {{ step.description }}
                    </p>
                </div>
            </section>
        </main>

        <footer
            class="relative mx-auto w-full max-w-5xl px-6 pb-8 text-center text-sm text-muted-foreground"
        >
            Leads are starting points, not conclusions. Verify each one against
            primary records before acting on it.
        </footer>
    </div>
</template>
