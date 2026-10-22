<script setup lang="ts">
import { riskColor, vectorColor } from '@/lib/leaflet';

/** Members only see pins (numbered by votes) and clusters, so their key is short. */
defineProps<{
    reviewer: boolean;
}>();

const pins = [
    { label: 'High risk', color: riskColor(75) },
    { label: 'Elevated', color: riskColor(50) },
    { label: 'Moderate', color: riskColor(25) },
    { label: 'Low', color: riskColor(0) },
    { label: 'No open leads', color: '#94a3b8' },
];
</script>

<template>
    <ul
        v-if="!reviewer"
        class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-muted-foreground"
    >
        <li class="flex items-center gap-1.5">
            <svg viewBox="0 0 30 40" class="h-3.5 w-3" aria-hidden="true">
                <path
                    d="M15 38.5S2 24.6 2 14.5a13 13 0 0 1 26 0c0 10.1-13 24-13 24z"
                    fill="#2563eb"
                />
            </svg>
            A target; the number is its community votes
        </li>
        <li class="flex items-center gap-1.5">
            <span
                class="grid size-3.5 place-items-center rounded-full bg-blue-600 text-[8px] font-bold text-white"
                >3</span
            >
            Nearby places, grouped (select to zoom in)
        </li>
    </ul>
    <ul
        v-else
        class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-muted-foreground"
    >
        <li
            v-for="item in pins"
            :key="item.label"
            class="flex items-center gap-1.5"
        >
            <svg viewBox="0 0 30 40" class="h-3.5 w-3" aria-hidden="true">
                <path
                    d="M15 38.5S2 24.6 2 14.5a13 13 0 0 1 26 0c0 10.1-13 24-13 24z"
                    :fill="item.color"
                />
            </svg>
            {{ item.label }}
        </li>
        <li class="flex items-center gap-1.5">
            <span
                class="size-3 rounded-full border border-blue-600 bg-blue-500/20"
            />
            Searching
        </li>
        <li class="flex items-center gap-1.5">
            <span
                class="size-3 rounded-full border border-dashed border-blue-600"
            />
            Queued
        </li>
        <li class="flex items-center gap-1.5">
            <span
                class="size-2.5 rounded-full border-2 border-white bg-emerald-500 shadow-sm"
            />
            New lead (24h)
        </li>
        <li class="flex items-center gap-1.5">
            <span
                class="grid size-3.5 place-items-center rounded-full bg-slate-500 text-[8px] font-bold text-white"
                >3</span
            >
            Nearby places, grouped
        </li>
        <li class="flex items-center gap-1.5">
            <span class="h-1 w-4 rounded-full bg-purple-500" />
            Shared names in leads
        </li>
        <li class="flex items-center gap-1.5">
            <span
                class="h-1 w-4 rounded-full"
                :style="{
                    background: `repeating-linear-gradient(90deg, ${vectorColor(3)} 0 2px, transparent 2px 5px)`,
                }"
            />
            Same search at both
        </li>
        <li class="flex items-center gap-1.5">
            <span class="h-0 w-4 border-t-2 border-dashed border-slate-500" />
            Same pattern (selected place)
        </li>
    </ul>
</template>
