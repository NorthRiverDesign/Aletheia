<script setup lang="ts">
import { eventKinds, eventOutcome } from '@/lib/mapEvents';
import { cn, timeAgo } from '@/lib/utils';
import type { MapEvent } from '@/types';

/** The newest live event, for the full-screen map where the activity feed is out of view. */
const props = defineProps<{
    event: MapEvent;
    /** Places on the map, which the event can jump to. */
    placeIds: Set<number>;
    now: Date;
}>();

const emit = defineEmits<{
    select: [id: number];
}>();
</script>

<template>
    <Transition
        mode="out-in"
        enter-active-class="transition duration-300 motion-reduce:transition-none"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-150 motion-reduce:transition-none"
        leave-to-class="opacity-0"
    >
        <button
            :key="props.event.key"
            type="button"
            class="flex w-full items-center gap-2.5 rounded-lg border bg-background/95 px-3 py-2 text-left text-sm shadow-lg backdrop-blur disabled:cursor-default"
            :disabled="
                !props.event.target ||
                !props.placeIds.has(props.event.target.id)
            "
            aria-live="polite"
            @click="props.event.target && emit('select', props.event.target.id)"
        >
            <component
                :is="eventKinds[props.event.kind].icon"
                :class="
                    cn('size-4 shrink-0', eventKinds[props.event.kind].tone)
                "
            />
            <span class="min-w-0 flex-1">
                <span class="block truncate font-medium">
                    {{ eventKinds[props.event.kind].label }}
                    <span
                        v-if="eventOutcome(props.event)"
                        class="font-normal text-muted-foreground"
                    >
                        · {{ eventOutcome(props.event) }}
                    </span>
                </span>
                <span class="block truncate text-xs text-muted-foreground">
                    {{ props.event.finding?.title ?? props.event.vector ?? '' }}
                    <template v-if="props.event.target">
                        · {{ props.event.target.name }}
                    </template>
                    <template v-if="props.event.at">
                        · {{ timeAgo(props.event.at, props.now) }}
                    </template>
                </span>
            </span>
        </button>
    </Transition>
</template>
