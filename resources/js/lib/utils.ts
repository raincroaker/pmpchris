import type { ClassValue } from 'clsx';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Merges Tailwind classes - The gold standard for shadcn/ui
 */
export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Handles navigation URLs for Sidebar/Nav components.
 * Supports route objects with .url (wayfinder) or .href; returns pathname for comparison.
 */
export function toUrl(
    url: string | { href?: string; url?: string } | null | undefined,
): string {
    if (url == null) return '';
    if (typeof url === 'string') {
        const s = url.trim();
        if (s.startsWith('http')) {
            try {
                return new URL(s).pathname;
            } catch {
                return s;
            }
        }
        return s;
    }
    const raw = url.url ?? url.href ?? '';
    if (!raw) return '';
    if (raw.startsWith('http')) {
        try {
            return new URL(raw).pathname;
        } catch {
            return raw;
        }
    }
    return raw;
}

/**
 * Formats numbers to Philippine Peso (PHP)
 */
export function formatCurrency(value: number) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
}

/**
 * Formats dates to Philippine local format (e.g., Mar 07, 2026)
 */
export function formatDate(date: Date | string) {
    return new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(date));
}
