<script setup lang="ts">
import { ArrowLeft, Info, MoreHorizontal } from 'lucide-vue-next';
import type { ChatRoom } from '@/components/chat/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';

defineProps<{
    room: ChatRoom;
}>();

defineEmits<{
    (event: 'back'): void;
    (event: 'open-info'): void;
}>();

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
        return 'G';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 1).toUpperCase();
    }

    return `${parts[0].slice(0, 1)}${parts[parts.length - 1].slice(0, 1)}`.toUpperCase();
}
</script>

<template>
    <div class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background px-4">
        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="size-9 shrink-0 rounded-full md:hidden"
            aria-label="Back to chat list"
            @click="$emit('back')"
        >
            <ArrowLeft class="size-5" />
        </Button>
        <Avatar class="size-10 shrink-0">
            <AvatarImage v-if="room.avatarUrl" :src="room.avatarUrl" :alt="room.name" />
            <AvatarFallback>
                {{ initials(room.name) }}
            </AvatarFallback>
        </Avatar>
        <button
            type="button"
            class="min-w-0 flex-1 text-left"
            aria-label="Open group details"
            @click="$emit('open-info')"
        >
            <p class="truncate text-sm font-semibold text-foreground md:text-base">
                {{ room.name }}
            </p>
            <p class="truncate text-xs text-muted-foreground">
                {{ room.statusLine }}
            </p>
        </button>
        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="size-9 shrink-0 rounded-full"
            aria-label="Open group details"
            @click="$emit('open-info')"
        >
            <Info class="size-4" />
        </Button>
        <Button type="button" variant="ghost" size="icon" class="size-9 shrink-0 rounded-full" aria-label="Chat actions">
            <MoreHorizontal class="size-5" />
        </Button>
    </div>
</template>
