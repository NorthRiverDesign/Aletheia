<script setup lang="ts">
import { RefreshCw, WifiOff } from '@lucide/vue';
import { computed } from 'vue';
import { cn, timeAgo } from '@/lib/utils';

/** Whether the map is keeping up: live, retrying after failed refreshes, or offline. */
const props = defineProps<{
    state: 'live' | 'retrying' | 'offline';
    updatedAt: string;
    now: Date;
    /** Searches are running, so refreshes come every few seconds. */
    busy: boolean;
}>();

const emit = defineEmits<{
    retry: [];
}>();

const title = computed(() =>
    props.state === 'offline'
        ? 'No connection. The map catches up when you are back online.'
        : props.state === 'retrying'
          ? 'The last refresh failed. Retrying with growing pauses.'
          : props.busy
            ? 'Refreshing every 5 seconds while searches run'
            : 'Refreshing every 30 seconds',
);
</script>

<template>
    <p
        class="flex items-center gap-2 text-xs text-muted-foreground"
        :title="title"
    >
        <WifiOff
            v-if="state === 'offline'"
            class="size-3.5 text-muted-foreground"
            aria-hidden="true"
        />
        <span v-else class="relative flex size-2.5">
            <span
                v-if="state === 'live' && busy"
                class="absolute inline-flex size-full rounded-full bg-emerald-400 opacity-75 motion-safe:animate-ping"
            />
            <span
                :class="
                    cn(
                        'relative inline-flex size-2.5 rounded-full',
                        state === 'retrying'
                            ? 'bg-amber-500'
                            : busy
                              ? 'bg-emerald-500'
                              : 'bg-muted-foreground/50',
                    )
                "
            />
        </span>
        <span role="status">
            <span class="font-medium text-foreground">{{
                state === 'offline'
                    ? 'Offline'
                    : state === 'retrying'
                      ? 'Reconnecting'
                      : 'Live'
            }}</span>
            · updated {{ timeAgo(updatedAt, now) }}
        </span>
        <button
            v-if="state === 'retrying'"
            type="button"
            class="flex items-center gap-1 font-medium text-primary hover:underline"
            @click="emit('retry')"
        >
            <RefreshCw class="size-3" /> Retry now
        </button>
    </p>
</template>
