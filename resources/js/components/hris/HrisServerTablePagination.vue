<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const props = withDefaults(
    defineProps<{
        total: number;
        from: number | null;
        to: number | null;
        currentPage: number;
        lastPage: number;
        perPage: number;
        perPageOptions?: number[];
        canPreviousPage: boolean;
        canNextPage: boolean;
    }>(),
    {
        perPageOptions: () => [10, 20, 50],
    },
);

const emit = defineEmits<{
    'update:perPage': [value: number];
    goFirst: [];
    goPrev: [];
    goNext: [];
    goLast: [];
}>();

const perPageModel = computed({
    get: () => String(props.perPage),
    set: (v: string) => {
        emit('update:perPage', Number.parseInt(v, 10) || 10);
    },
});

const pageLabelTotal = computed(() => Math.max(props.lastPage, 1));
</script>

<template>
    <div
        class="flex flex-col gap-4 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="text-sm text-muted-foreground">
            <template v-if="total > 0">
                Showing
                {{ from ?? 0 }}
                –
                {{ to ?? 0 }}
                of
                {{ total }}
            </template>
            <template v-else> No rows </template>
        </div>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
            <div class="flex items-center gap-2">
                <p
                    class="text-sm font-medium whitespace-nowrap text-foreground"
                >
                    Rows per page
                </p>
                <Select v-model="perPageModel">
                    <SelectTrigger class="h-9 w-[72px]">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent side="top">
                        <SelectItem
                            v-for="opt in perPageOptions"
                            :key="opt"
                            :value="String(opt)"
                        >
                            {{ opt }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="flex items-center gap-1 sm:gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="hidden size-9 p-0 lg:inline-flex"
                    :disabled="currentPage <= 1"
                    aria-label="First page"
                    @click="emit('goFirst')"
                >
                    <ChevronsLeft class="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="size-9 p-0"
                    :disabled="!canPreviousPage"
                    aria-label="Previous page"
                    @click="emit('goPrev')"
                >
                    <ChevronLeft class="size-4" />
                </Button>
                <span
                    class="min-w-32 text-center text-sm text-muted-foreground"
                >
                    Page {{ currentPage }} of
                    {{ pageLabelTotal }}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    class="size-9 p-0"
                    :disabled="!canNextPage"
                    aria-label="Next page"
                    @click="emit('goNext')"
                >
                    <ChevronRight class="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="hidden size-9 p-0 lg:inline-flex"
                    :disabled="currentPage >= lastPage"
                    aria-label="Last page"
                    @click="emit('goLast')"
                >
                    <ChevronsRight class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
