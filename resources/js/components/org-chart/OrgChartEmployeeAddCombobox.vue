<script setup lang="ts">
import { ChevronsUpDown, Loader2, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import TeamHrEmployeeComboboxFilterBridge from '@/components/hris/TeamHrEmployeeComboboxFilterBridge.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxTrigger,
    ComboboxViewport,
} from '@/components/ui/combobox';
import { fetchOrgChartAssignableEmployees } from '@/lib/orgChartEmployeeSearchApi';
import type { OrgChartAssignableEmployeeHit } from '@/lib/orgChartEmployeeSearchApi';

const MIN_QUERY_LEN = 2;

const props = withDefaults(
    defineProps<{
        chartBranchId: number | null;
        nodeId: string;
        modelValue: OrgChartAssignableEmployeeHit | null;
        disabled?: boolean;
        placeholder?: string;
        id?: string;
    }>(),
    {
        placeholder: 'Search by name or employee ID…',
        disabled: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: OrgChartAssignableEmployeeHit | null];
}>();

const employees = ref<OrgChartAssignableEmployeeHit[]>([]);
/** Length of trimmed query last sent from the panel search input (empty-state copy). */
const lastPanelSearchLen = ref(0);
const loading = ref(false);
const loadError = ref<string | null>(null);
let fetchAbort: AbortController | null = null;

const selected = computed({
    get(): OrgChartAssignableEmployeeHit | undefined {
        if (!props.modelValue) {
            return undefined;
        }

        const fromList = employees.value.find(
            (e) => e.id === props.modelValue?.id,
        );
        return fromList ?? props.modelValue;
    },
    set(next: OrgChartAssignableEmployeeHit | undefined) {
        emit('update:modelValue', next ?? null);
    },
});

const comboboxDisabled = computed(
    () => props.disabled || loading.value || props.chartBranchId === null,
);

function employeeInitials(name: string): string {
    const parts = name
        .trim()
        .split(/\s+/)
        .filter((part) => part.length > 0);
    if (parts.length === 0) {
        return 'E';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 1).toUpperCase();
    }

    return `${parts[0].slice(0, 1)}${parts[parts.length - 1].slice(0, 1)}`.toUpperCase();
}

async function loadEmployees(query: string): Promise<void> {
    const branchId = props.chartBranchId;
    const q = query.trim();

    if (branchId === null) {
        employees.value = [];
        loadError.value = null;
        loading.value = false;

        return;
    }

    if (q.length < MIN_QUERY_LEN) {
        employees.value = [];
        loadError.value = null;
        loading.value = false;

        return;
    }

    fetchAbort?.abort();
    fetchAbort = new AbortController();

    loading.value = true;
    loadError.value = null;

    try {
        employees.value = await fetchOrgChartAssignableEmployees({
            chartBranchId: branchId,
            nodeId: props.nodeId,
            query: q,
            signal: fetchAbort.signal,
            limit: 20,
        });
    } catch (error) {
        if ((error as { name?: string }).name === 'AbortError') {
            return;
        }

        employees.value = [];
        loadError.value =
            error instanceof Error &&
            error.message === 'Unable to search employees.'
                ? 'Unable to load employees right now.'
                : 'Unable to load employees right now.';
    } finally {
        loading.value = false;
        fetchAbort = null;
    }
}

watch(
    () => [props.chartBranchId, props.nodeId] as const,
    () => {
        employees.value = [];
        loadError.value = null;
        lastPanelSearchLen.value = 0;
    },
);

function onComboboxSearch(q: string): void {
    lastPanelSearchLen.value = q.trim().length;
    void loadEmployees(q.trim());
}

const triggerTitle = computed(() => {
    if (loading.value) {
        return 'Loading employees…';
    }

    if (selected.value) {
        return selected.value.full_name;
    }

    return props.placeholder;
});

const triggerSubtitle = computed(() => selected.value?.employee_number ?? null);

const comboSearchPlaceholder = 'Search by name or employee ID';

const emptyHint = computed(() => {
    if (loading.value) {
        return 'Loading…';
    }

    if (lastPanelSearchLen.value < MIN_QUERY_LEN) {
        return 'Enter at least 2 characters.';
    }

    return 'No matching employees.';
});
</script>

<template>
    <div class="grid gap-1">
        <Combobox
            v-model="selected"
            by="id"
            :open-on-focus="true"
            :open-on-click="true"
            :ignore-filter="true"
            :disabled="comboboxDisabled"
            class="w-full"
        >
            <TeamHrEmployeeComboboxFilterBridge
                @update:search="onComboboxSearch"
            />
            <ComboboxAnchor class="w-full">
                <ComboboxTrigger as-child>
                    <Button
                        :id="id"
                        type="button"
                        variant="outline"
                        role="combobox"
                        :disabled="comboboxDisabled"
                        class="flex h-auto min-h-9 w-full items-center justify-start gap-3 py-2 text-start font-normal"
                    >
                        <Avatar class="size-10 shrink-0">
                            <AvatarImage
                                v-if="selected?.avatar_url"
                                :src="selected.avatar_url"
                                :alt="selected.full_name"
                            />
                            <AvatarFallback class="bg-muted">
                                <template v-if="selected">{{
                                    employeeInitials(selected.full_name)
                                }}</template>
                                <Loader2
                                    v-else-if="loading"
                                    class="size-5 animate-spin text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <User
                                    v-else
                                    class="size-5 text-foreground"
                                    aria-hidden="true"
                                />
                            </AvatarFallback>
                        </Avatar>
                        <span
                            class="flex min-w-0 flex-1 flex-col items-stretch gap-0.5 text-start"
                        >
                            <span class="min-w-0 truncate">{{
                                triggerTitle
                            }}</span>
                            <span
                                v-if="triggerSubtitle"
                                class="w-full truncate font-mono text-xs text-muted-foreground"
                            >
                                {{ triggerSubtitle }}
                            </span>
                            <span
                                v-else-if="!loading && selected"
                                class="w-full truncate font-mono text-xs text-muted-foreground"
                            >
                                —
                            </span>
                        </span>
                        <ChevronsUpDown
                            class="size-4 shrink-0 self-center opacity-50"
                        />
                    </Button>
                </ComboboxTrigger>
            </ComboboxAnchor>
            <ComboboxList
                class="w-[max(var(--reka-combobox-trigger-width),24rem)] max-w-[92vw] p-0"
            >
                <ComboboxInput :placeholder="comboSearchPlaceholder" />
                <p
                    v-if="loadError"
                    class="border-b px-3 py-2 text-sm text-destructive"
                >
                    {{ loadError }}
                </p>
                <ComboboxEmpty>{{ emptyHint }}</ComboboxEmpty>
                <ComboboxViewport class="max-h-60 px-1.5 py-1">
                    <ComboboxItem
                        v-for="hit in employees"
                        :key="hit.id"
                        :value="hit"
                        class="items-center px-3 py-2"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <Avatar class="size-10 shrink-0">
                                <AvatarImage
                                    v-if="hit.avatar_url"
                                    :src="hit.avatar_url"
                                    :alt="hit.full_name"
                                />
                                <AvatarFallback>{{
                                    employeeInitials(hit.full_name)
                                }}</AvatarFallback>
                            </Avatar>
                            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span
                                    class="truncate leading-tight font-medium"
                                    >{{ hit.full_name }}</span
                                >
                                <span
                                    class="font-mono text-xs text-muted-foreground"
                                    >{{ hit.employee_number ?? '—' }}</span
                                >
                            </div>
                        </div>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxList>
        </Combobox>
    </div>
</template>
