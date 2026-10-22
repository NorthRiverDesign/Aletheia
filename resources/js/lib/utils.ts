import type { InertiaLinkProps } from '@inertiajs/vue3';
import { formatTimeAgo } from '@vueuse/core';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

/** "5 minutes ago" for an ISO timestamp, never in the future when clocks disagree. */
export function timeAgo(value: string, now: Date) {
    return formatTimeAgo(
        new Date(Math.min(Date.parse(value), now.getTime())),
        {},
        now,
    );
}
