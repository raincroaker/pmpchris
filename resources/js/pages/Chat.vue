<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CheckCheck, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatGroupInfoSheet from '@/components/chat/ChatGroupInfoSheet.vue';
import ChatMessageList from '@/components/chat/ChatMessageList.vue';
import ChatThreadHeader from '@/components/chat/ChatThreadHeader.vue';
import type { UnitChatThread } from '@/components/chat/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import AppLayout from '@/layouts/AppLayout.vue';
import { chat } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Unit Chats',
        href: chat(),
    },
];

const props = defineProps<{
    rooms: UnitChatThread[];
    selectedRoomId: number | null;
    searchQuery: string;
}>();

const draftMessage = ref('');
const search = ref(props.searchQuery ?? '');
const mobilePane = ref<'list' | 'thread'>('list');
const groupInfoOpen = ref(false);
const selectedRoomId = ref<number | null>(props.selectedRoomId ?? props.rooms[0]?.id ?? null);

const visibleUnitThreads = computed(() => [...props.rooms]);

const activeThread = computed(() => {
    if (selectedRoomId.value === null) {
        return null;
    }

    return props.rooms.find((room) => room.id === selectedRoomId.value) ?? null;
});

function getInitials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
        return 'C';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 1).toUpperCase();
    }

    return `${parts[0].slice(0, 1)}${parts[parts.length - 1].slice(0, 1)}`.toUpperCase();
}

function openUnitThread(threadId: number): void {
    selectedRoomId.value = threadId;
    router.get('/chat', {
        room: threadId,
        q: search.value.trim() !== '' ? search.value.trim() : undefined,
    }, { preserveScroll: true, preserveState: true, replace: true });
    markRoomAsRead(threadId);
    mobilePane.value = 'thread';
}

function backToChatList(): void {
    mobilePane.value = 'list';
}

function searchUnitChats(): void {
    router.get('/chat', {
        room: selectedRoomId.value ?? undefined,
        q: search.value.trim() !== '' ? search.value.trim() : undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function sendMessage(): void {
    const thread = activeThread.value;
    const text = draftMessage.value.trim();
    if (!thread || text === '') {
        return;
    }

    router.post(`/chat/rooms/${thread.id}/messages`, {
        body: text,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            draftMessage.value = '';
        },
    });
}

function markRoomAsRead(roomId: number): void {
    if (roomId <= 0) {
        return;
    }

    const thread = props.rooms.find((room) => room.id === roomId);
    const latestMessageId = thread?.messages.at(-1)?.id;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    void fetch(`/chat/rooms/${roomId}/read`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken !== '' ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
        body: JSON.stringify({
            last_read_message_id: latestMessageId ?? null,
        }),
    }).catch(() => {
        // Keep UX non-blocking; unread will recover on next refresh.
    });
}

watch(
    () => props.selectedRoomId,
    (nextRoomId) => {
        if (typeof nextRoomId === 'number' && nextRoomId > 0) {
            markRoomAsRead(nextRoomId);
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Unit Chats" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-0 flex-1 flex-col gap-0 p-0">
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-background md:flex-row">
                <aside
                    class="flex min-h-0 w-full shrink-0 flex-col border-sidebar-border/70 md:w-[28%] md:border-r"
                    :class="mobilePane === 'thread' ? 'hidden md:flex' : 'flex'"
                >
                    <div class="flex h-16 items-center border-b border-sidebar-border/70 px-4">
                        <h1 class="text-xl font-semibold tracking-tight text-foreground md:text-2xl">My Unit Chats</h1>
                    </div>
                    <div class="border-b border-sidebar-border/70 px-4 py-3">
                        <div class="relative">
                            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="search"
                                placeholder="Search units"
                                class="h-9 pl-9"
                                @keyup.enter="searchUnitChats"
                                @blur="searchUnitChats"
                            />
                        </div>
                    </div>

                    <ScrollArea class="min-h-0 flex-1">
                        <div class="space-y-2 px-2 py-2">
                            <button
                                v-for="item in visibleUnitThreads"
                                :key="item.id"
                                type="button"
                                class="mb-1 flex w-full items-start gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-primary/5"
                                :class="item.id === selectedRoomId ? 'bg-primary/10 shadow-sm ring-1 ring-primary/30' : ''"
                                @click="openUnitThread(item.id)"
                            >
                                <Avatar class="size-10 shrink-0">
                                    <AvatarImage v-if="item.avatarUrl" :src="item.avatarUrl" :alt="item.name" />
                                    <AvatarFallback>
                                        {{ getInitials(item.name) }}
                                    </AvatarFallback>
                                </Avatar>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="truncate text-sm font-medium text-foreground">
                                            {{ item.name }}
                                        </p>
                                        <span class="shrink-0 text-xs text-muted-foreground">
                                            {{ item.lastSeen }}
                                        </span>
                                    </div>

                                    <div class="mt-1 flex items-center justify-between gap-2">
                                        <p class="truncate text-xs text-muted-foreground">
                                            {{ item.lastMessage }}
                                        </p>
                                        <span
                                            v-if="item.unreadCount > 0"
                                            class="inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground"
                                        >
                                            {{ item.unreadCount }}
                                        </span>
                                        <span
                                            v-else-if="item.lastMessage !== 'No messages yet.'"
                                            class="inline-flex shrink-0 items-center text-muted-foreground"
                                            aria-label="All messages read"
                                            title="All messages read"
                                        >
                                            <CheckCheck class="size-3.5" />
                                        </span>
                                    </div>
                                </div>
                            </button>

                            <div
                                v-if="visibleUnitThreads.length === 0"
                                class="rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 px-3 py-5 text-center text-sm text-muted-foreground"
                            >
                                No active unit chat found for your assignments.
                            </div>
                        </div>
                    </ScrollArea>
                </aside>

                <section
                    class="flex min-h-0 min-w-0 flex-1 flex-col bg-muted/15 md:w-[72%]"
                    :class="mobilePane === 'list' ? 'hidden md:flex' : 'flex'"
                >
                    <template v-if="activeThread">
                        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
                            <ChatThreadHeader :room="activeThread" @back="backToChatList" @open-info="groupInfoOpen = true" />
                            <ChatMessageList :messages="activeThread.messages" />
                            <ChatComposer v-model="draftMessage" @send="sendMessage" />
                        </div>
                    </template>

                    <div v-else class="flex min-h-0 flex-1 items-center justify-center px-6 text-center">
                        <p class="text-base font-semibold text-foreground">No unit selected</p>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>

    <ChatGroupInfoSheet
        v-model:open="groupInfoOpen"
        :room="activeThread"
    />

</template>
