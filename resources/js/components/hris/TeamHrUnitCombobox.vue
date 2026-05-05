<script setup lang="ts">
import { Building2, ChevronsUpDown, Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';
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
import type { TeamHrFormUnit } from '@/lib/teamHrFormApi';

const props = withDefaults(
    defineProps<{
        units: TeamHrFormUnit[];
        modelValue: number | null;
        disabled?: boolean;
        loading?: boolean;
        placeholder?: string;
        emptyMessage?: string;
        id?: string;
    }>(),
    {
        placeholder: 'Select unit…',
        emptyMessage: 'No units found.',
        disabled: false,
        loading: false,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const selected = computed({
    get(): TeamHrFormUnit | undefined {
        if (props.modelValue === null) {
            return undefined;
        }

        return props.units.find((u) => u.id === props.modelValue);
    },
    set(next: TeamHrFormUnit | undefined) {
        emit('update:modelValue', next?.id ?? null);
    },
});

const triggerLabel = computed(() => {
    if (props.loading) {
        return 'Loading units…';
    }

    if (selected.value) {
        return selected.value.name;
    }

    return props.placeholder;
});

const triggerCode = computed(() => selected.value?.code ?? null);
</script>

<template>
    <Combobox
        v-model="selected"
        by="id"
        :open-on-focus="true"
        :open-on-click="true"
        :disabled="disabled || loading"
        class="w-full"
    >
        <ComboboxAnchor class="w-full">
            <ComboboxTrigger as-child>
                <Button
                    :id="id"
                    type="button"
                    variant="outline"
                    role="combobox"
                    :disabled="disabled || loading"
                    class="flex h-auto min-h-9 w-full items-center justify-start gap-3 py-2 text-start font-normal"
                >
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted"
                    >
                        <Loader2
                            v-if="loading"
                            class="size-5 animate-spin text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Building2
                            v-else
                            class="size-5 text-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <span
                        class="flex min-w-0 flex-1 flex-col items-stretch gap-0.5 text-start"
                    >
                        <span class="min-w-0 truncate">{{ triggerLabel }}</span>
                        <span
                            v-if="triggerCode"
                            class="w-full truncate font-mono text-xs text-muted-foreground"
                        >
                            {{ triggerCode }}
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
            <ComboboxInput placeholder="Search unit…" />
            <ComboboxEmpty>{{ emptyMessage }}</ComboboxEmpty>
            <ComboboxViewport class="max-h-60 px-1.5 py-1">
                <ComboboxItem
                    v-for="unit in units"
                    :key="unit.id"
                    :value="unit"
                    class="px-3 py-2"
                >
                    <div class="flex min-w-0 flex-col gap-0.5 py-0.5">
                        <span class="truncate leading-tight font-medium">{{
                            unit.name
                        }}</span>
                        <span
                            v-if="unit.code"
                            class="font-mono text-xs text-muted-foreground"
                            >{{ unit.code }}</span
                        >
                    </div>
                </ComboboxItem>
            </ComboboxViewport>
        </ComboboxList>
    </Combobox>
</template>
