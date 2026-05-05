import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';

const NON_SEARCH_KEYS = new Set([
    'Shift',
    'Control',
    'Alt',
    'Meta',
    'CapsLock',
    'Tab',
    'ArrowLeft',
    'ArrowRight',
    'ArrowUp',
    'ArrowDown',
    'Home',
    'End',
    'PageUp',
    'PageDown',
    'Escape',
]);

export function useDebouncedSearchInput(options: {
    initialValue: string;
    debounceMs: number;
    onDebouncedSearch: (value: string) => void;
}) {
    const localSearch = ref(options.initialValue);
    const debouncedApplySearch = useDebounceFn(
        (value: string) => options.onDebouncedSearch(value),
        options.debounceMs,
    );

    function syncFromServerSearch(value: string): void {
        if (value !== localSearch.value) {
            localSearch.value = value;
        }
    }

    function onSearchUpdate(value: string | number): void {
        localSearch.value = String(value);
    }

    function onSearchKeyup(event: KeyboardEvent): void {
        if (NON_SEARCH_KEYS.has(event.key)) {
            return;
        }

        debouncedApplySearch(localSearch.value);
    }

    function onSearchCommit(value: string | number | Event): void {
        const normalized =
            value instanceof Event
                ? ((value.target as HTMLInputElement | null)?.value ?? '')
                : String(value);
        localSearch.value = normalized;
        debouncedApplySearch(normalized);
    }

    return {
        localSearch,
        syncFromServerSearch,
        onSearchUpdate,
        onSearchKeyup,
        onSearchCommit,
    };
}
