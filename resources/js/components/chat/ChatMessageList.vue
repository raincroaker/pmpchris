<script setup lang="ts">
import { computed } from 'vue';
import type { ChatMessage } from '@/components/chat/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const props = defineProps<{
    messages: ChatMessage[];
}>();

const activeMessages = computed(() => props.messages ?? []);

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
        return 'U';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 1).toUpperCase();
    }

    return `${parts[0].slice(0, 1)}${parts[parts.length - 1].slice(0, 1)}`.toUpperCase();
}

function shouldShowSenderAvatar(messages: ChatMessage[], index: number): boolean {
    const current = messages[index];
    if (!current || current.role !== 'them') {
        return false;
    }
    const next = messages[index + 1];
    if (!next) {
        return true;
    }

    return next.role !== 'them' || next.senderLabel !== current.senderLabel;
}
</script>

<template>
    <ScrollArea class="min-h-0 flex-1">
        <TooltipProvider>
            <div class="flex flex-col gap-3 px-4 py-4">
                <div
                    v-for="(msg, index) in activeMessages"
                    :key="msg.id"
                    class="flex w-full items-end gap-2"
                    :class="msg.role === 'me' ? 'justify-end' : 'justify-start'"
                >
                    <div v-if="msg.role === 'them'" class="w-7 shrink-0">
                        <Tooltip v-if="shouldShowSenderAvatar(activeMessages, index)">
                            <TooltipTrigger as-child>
                                <Avatar class="size-7 cursor-default transition hover:ring-2 hover:ring-primary/30">
                                    <AvatarImage
                                        v-if="msg.senderAvatarUrl"
                                        :src="msg.senderAvatarUrl"
                                        :alt="msg.senderLabel ?? 'Sender avatar'"
                                    />
                                    <AvatarFallback class="text-[10px]">
                                        {{ initials(msg.senderLabel ?? 'Sender') }}
                                    </AvatarFallback>
                                </Avatar>
                            </TooltipTrigger>
                            <TooltipContent side="top" :side-offset="6">
                                <p>{{ msg.senderLabel ?? 'Sender' }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
                    <div
                        class="max-w-[min(100%,28rem)] rounded-2xl px-3 py-2 text-sm shadow-sm"
                        :class="msg.role === 'me' ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground'"
                    >
                        <p
                            v-if="msg.role === 'them' && msg.senderLabel"
                            class="mb-0.5 text-xs font-medium text-muted-foreground"
                        >
                            {{ msg.senderLabel }}
                        </p>
                        <p class="wrap-break-word whitespace-pre-wrap">{{ msg.text }}</p>
                        <p
                            class="mt-1 text-[10px] opacity-80"
                            :class="msg.role === 'me' ? 'text-primary-foreground/80' : 'text-muted-foreground'"
                        >
                            {{ msg.createdLabel }}
                        </p>
                    </div>
                </div>
            </div>
        </TooltipProvider>
    </ScrollArea>
</template>
