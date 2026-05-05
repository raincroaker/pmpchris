<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { EmployeeIndexUnit } from '@/pages/Employees/employeeIndexTypes';

const props = defineProps<{
    units: EmployeeIndexUnit[];
}>();

const summary = computed(() =>
    props.units.length === 0 ? 'Unassigned' : props.units[0].name,
);

const summaryMeta = computed(() => {
    if (props.units.length === 0) {
        return null;
    }

    const first = props.units[0];

    return first.code ? `${first.unit_type} • ${first.code}` : first.unit_type;
});

const hasPopover = computed(() => props.units.length > 1);

const triggerLabel = computed(() => {
    const n = props.units.length;

    return n <= 1 ? 'View unit details' : `View all ${n} assignments`;
});
</script>

<template>
    <div
        class="flex min-w-0 gap-1"
        :class="units.length === 0 ? 'items-center' : 'items-start'"
    >
        <div class="min-w-0">
            <template v-if="units.length === 0">
                <Badge
                    variant="secondary"
                    class="rounded-full border border-border/60 text-[11px] font-medium"
                >
                    Unassigned
                </Badge>
            </template>
            <span
                v-else
                class="block min-w-0 truncate text-sm text-foreground"
                :title="summary"
            >
                {{ summary }}
            </span>
            <span
                v-if="summaryMeta !== null"
                class="block min-w-0 truncate text-xs text-muted-foreground"
                :title="summaryMeta"
            >
                {{ summaryMeta }}
            </span>
        </div>
        <Popover v-if="hasPopover">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0 text-muted-foreground hover:text-foreground"
                    :aria-label="triggerLabel"
                >
                    <ChevronDown class="size-4" aria-hidden="true" />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" class="w-80 p-0">
                <div class="px-3 pt-2 pb-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        Units
                    </p>
                </div>
                <ScrollArea class="max-h-64">
                    <ul class="px-2 pt-0 pb-2">
                        <li
                            v-for="(u, idx) in units"
                            :key="idx"
                            class="flex min-h-8 items-center gap-2 rounded-sm px-1.5 py-1 text-sm"
                        >
                            <div
                                class="flex min-w-0 flex-1 items-baseline gap-2 leading-snug"
                            >
                                <span
                                    class="min-w-0 truncate text-sm font-medium text-foreground"
                                    >{{ u.name }}</span
                                >
                                <span
                                    class="shrink-0 font-mono text-xs leading-snug text-muted-foreground"
                                >
                                    {{
                                        u.code
                                            ? `${u.unit_type} • ${u.code}`
                                            : u.unit_type
                                    }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </ScrollArea>
            </PopoverContent>
        </Popover>
    </div>
</template>
