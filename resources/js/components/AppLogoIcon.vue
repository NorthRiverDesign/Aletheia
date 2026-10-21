<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useId } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();

const gradientId = `app-logo-${useId()}`;
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 64 64"
        :class="className"
        v-bind="$attrs"
    >
        <defs>
            <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="#3B82F6" />
                <stop offset="1" stop-color="#1D4ED8" />
            </linearGradient>
        </defs>
        <rect width="64" height="64" rx="15" :fill="`url(#${gradientId})`" />
        <circle
            cx="29"
            cy="29"
            r="13"
            fill="#FFFFFF"
            fill-opacity=".18"
            stroke="#FFFFFF"
            stroke-width="6"
        />
        <path
            d="m39.5 39.5 8 8"
            fill="none"
            stroke="#FFFFFF"
            stroke-width="7"
            stroke-linecap="round"
        />
        <path
            d="m23 29.5 4.5 4.5 8-9"
            fill="none"
            stroke="#FCD34D"
            stroke-width="4.5"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
