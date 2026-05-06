<script setup lang="ts">
import { Send } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    modelValue?: string;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
    (event: 'send'): void;
}>();

const draft = ref(props.modelValue ?? '');

const canSend = computed(() => draft.value.trim() !== '');

watch(
    () => props.modelValue,
    (nextValue) => {
        draft.value = nextValue ?? '';
    },
);

watch(draft, (nextValue) => {
    emit('update:modelValue', nextValue);
});

function onComposerKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        if (!canSend.value) {
            return;
        }
        emit('send');
    }
}

function sendMessage(): void {
    if (!canSend.value) {
        return;
    }
    emit('send');
}
</script>

<template>
    <div
        class="shrink-0 border-t border-sidebar-border/70 bg-background p-3 md:p-4"
    >
        <form class="flex items-end gap-2" @submit.prevent="sendMessage">
            <Textarea
                v-model="draft"
                placeholder="Message…"
                class="max-h-36 min-h-10 flex-1 resize-none overflow-y-auto rounded-2xl px-4 py-2"
                autocomplete="off"
                @keydown="onComposerKeydown"
            />
            <Button
                type="submit"
                size="icon"
                class="size-10 shrink-0 rounded-full"
                aria-label="Send message"
                :disabled="!canSend"
            >
                <Send class="size-4" />
            </Button>
        </form>
    </div>
</template>
