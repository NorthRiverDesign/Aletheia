<script setup lang="ts">
import { Pause, Play, Radio } from '@lucide/vue';

/** A compact replay control for the full-screen map, where the timeline card is out of view. */
const props = defineProps<{
    start: number;
    end: number;
    at: number | null;
    playing: boolean;
    disabled: boolean;
}>();

const emit = defineEmits<{
    seek: [at: number];
    toggle: [];
    live: [];
}>();

const label = (time: number) =>
    new Date(time).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
</script>

<template>
    <div
        class="flex items-center gap-2 rounded-lg border bg-background/95 p-2 shadow-lg backdrop-blur"
    >
        <button
            type="button"
            class="grid size-8 shrink-0 place-items-center rounded-md bg-primary text-primary-foreground disabled:opacity-50"
            :aria-label="props.playing ? 'Pause replay' : 'Play replay'"
            :disabled="props.disabled"
            @click="emit('toggle')"
        >
            <component :is="props.playing ? Pause : Play" class="size-4" />
        </button>
        <div class="min-w-0 flex-1">
            <input
                type="range"
                class="block w-full accent-primary"
                :min="props.start"
                :max="props.end"
                :step="(props.end - props.start) / 500"
                :value="props.at ?? props.end"
                :disabled="props.disabled"
                aria-label="Replay time"
                :aria-valuetext="label(props.at ?? props.end)"
                @input="
                    emit(
                        'seek',
                        Number(($event.target as HTMLInputElement).value),
                    )
                "
            />
            <p class="truncate text-xs text-muted-foreground">
                {{
                    props.at === null
                        ? 'Live now'
                        : `Replay · ${label(props.at)}`
                }}
            </p>
        </div>
        <button
            v-if="props.at !== null"
            type="button"
            class="flex shrink-0 items-center gap-1 rounded-md border px-2 py-1.5 text-xs font-medium hover:bg-accent"
            @click="emit('live')"
        >
            <Radio class="size-3.5" /> Live
        </button>
    </div>
</template>
