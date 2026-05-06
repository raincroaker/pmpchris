<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatGroupInfoSheet from '@/components/chat/ChatGroupInfoSheet.vue';
import ChatMessageList from '@/components/chat/ChatMessageList.vue';
import ChatThreadHeader from '@/components/chat/ChatThreadHeader.vue';
import type { ChatRoom } from '@/components/chat/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import AppLayout from '@/layouts/AppLayout.vue';
import { chat } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Chats',
        href: chat(),
    },
];

const currentUser = {
    id: 999,
    name: 'You',
    employeeCode: 'ME-001',
    statusLine: 'Group owner',
};

const search = ref('');
const draftMessage = ref('');
const mobilePane = ref<'list' | 'thread'>('list');
const groupInfoOpen = ref(false);

const rooms = ref<ChatRoom[]>([
    {
        id: 1,
        name: 'HR Announcements',
        description: 'Official HR updates and reminders for all branch teams.',
        createdLabel: 'Jan 8, 2026',
        avatarUrl: null,
        statusLine: 'Official notices · typically replies in a day',
        unreadCount: 2,
        lastMessage: 'Please submit leave plans before Friday.',
        lastSeen: '10:24 AM',
        isMuted: false,
        isPinned: true,
        members: [
            { id: currentUser.id, name: currentUser.name, employeeCode: currentUser.employeeCode, avatarUrl: null, role: 'owner', statusLine: 'You' },
            { id: 2, name: 'Jordan Lim', employeeCode: 'EMP-1004', avatarUrl: null, role: 'admin', statusLine: 'HR Team' },
            { id: 5, name: 'Liam Cruz', employeeCode: 'EMP-0901', avatarUrl: null, role: 'member', statusLine: 'HR Team' },
        ],
        mediaItems: [
            { id: 11, kind: 'file', title: 'Q2 Leave Policy.pdf', subtitle: 'Shared by HR · 2 days ago' },
            { id: 12, kind: 'link', title: 'Attendance SOP link', subtitle: 'Pinned in this group' },
        ],
        messages: [
            {
                id: 101,
                text: 'Welcome to HR Announcements. This channel is for company-wide HR updates.',
                createdLabel: 'Mon · 9:00 AM',
                role: 'them',
                senderId: 2,
                senderLabel: 'Jordan Lim',
            },
            {
                id: 102,
                text: 'Please review the updated leave policy attached to the intranet.',
                createdLabel: 'Mon · 9:02 AM',
                role: 'them',
                senderId: 2,
                senderLabel: 'Jordan Lim',
            },
            {
                id: 103,
                text: 'Got it, thanks for the heads-up.',
                createdLabel: 'Mon · 10:15 AM',
                role: 'me',
            },
        ],
    },
    {
        id: 2,
        name: 'Operations Group',
        description: 'Daily operations coordination and scheduling updates.',
        createdLabel: 'Feb 3, 2026',
        avatarUrl: null,
        statusLine: '8 members · last active yesterday',
        unreadCount: 0,
        lastMessage: 'Meeting moved to 3:00 PM.',
        lastSeen: 'Yesterday',
        isMuted: false,
        isPinned: false,
        members: [
            { id: currentUser.id, name: currentUser.name, employeeCode: currentUser.employeeCode, avatarUrl: null, role: 'owner', statusLine: 'You' },
            { id: 1, name: 'Alex Reyes', employeeCode: 'EMP-1021', avatarUrl: null, role: 'admin', statusLine: 'Operations' },
            { id: 4, name: 'John Doe', employeeCode: 'EMP-1115', avatarUrl: null, role: 'member', statusLine: 'Branch Support' },
        ],
        mediaItems: [
            { id: 21, kind: 'image', title: 'Seating map photo', subtitle: 'Shared yesterday' },
            { id: 22, kind: 'file', title: 'Capacity matrix.xlsx', subtitle: 'Shared by Alex · yesterday' },
        ],
        messages: [
            {
                id: 201,
                text: 'Ops standup moved to 9:30 tomorrow.',
                createdLabel: 'Yesterday · 4:00 PM',
                role: 'them',
                senderId: 1,
                senderLabel: 'Alex Reyes',
            },
            {
                id: 202,
                text: 'Thanks — same room?',
                createdLabel: 'Yesterday · 4:05 PM',
                role: 'me',
            },
            {
                id: 203,
                text: 'Meeting moved to 3:00 PM.',
                createdLabel: 'Yesterday · 5:12 PM',
                role: 'them',
                senderId: 1,
                senderLabel: 'Alex Reyes',
            },
        ],
    },
]);

let nextMessageId = 1000;

const selectedRoomId = ref<number | null>(rooms.value[0]?.id ?? null);

const filteredRooms = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    const sortedRooms = [...rooms.value].sort((a, b) => Number(b.isPinned) - Number(a.isPinned));
    if (keyword === '') {
        return sortedRooms;
    }

    return sortedRooms.filter((room) => {
        return (
            room.name.toLowerCase().includes(keyword) ||
            room.lastMessage.toLowerCase().includes(keyword) ||
            room.description.toLowerCase().includes(keyword)
        );
    });
});

const activeRoom = computed(() => {
    if (selectedRoomId.value === null) {
        return null;
    }

    return rooms.value.find((room) => room.id === selectedRoomId.value) ?? null;
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

function clearUnread(roomId: number): void {
    const room = rooms.value.find((entry) => entry.id === roomId);
    if (room) {
        room.unreadCount = 0;
    }
}

function openRoom(roomId: number): void {
    selectedRoomId.value = roomId;
    clearUnread(roomId);
    mobilePane.value = 'thread';
}

function backToChatList(): void {
    mobilePane.value = 'list';
}

function formatFullDateTime(date: Date): string {
    const datePart = date.toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
    const timePart = date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });

    return `${datePart} · ${timePart}`;
}

function sendMessage(): void {
    const room = activeRoom.value;
    const text = draftMessage.value.trim();
    if (!room || text === '') {
        return;
    }

    room.messages.push({
        id: nextMessageId++,
        text,
        createdLabel: 'Just now',
        createdAtFullLabel: formatFullDateTime(new Date()),
        role: 'me',
    });
    room.lastMessage = text;
    room.lastSeen = 'Just now';
    draftMessage.value = '';
}

function updateRoom(payload: { roomId: number; updates: Partial<ChatRoom> }): void {
    const room = rooms.value.find((entry) => entry.id === payload.roomId);
    if (!room) {
        return;
    }

    Object.assign(room, payload.updates);
}

function updateMemberRole(payload: { roomId: number; memberId: number; role: 'owner' | 'admin' | 'member' }): void {
    const room = rooms.value.find((entry) => entry.id === payload.roomId);
    if (!room) {
        return;
    }

    const member = room.members.find((entry) => entry.id === payload.memberId);
    if (!member) {
        return;
    }

    member.role = payload.role;
}

function removeMember(payload: { roomId: number; memberId: number }): void {
    const room = rooms.value.find((entry) => entry.id === payload.roomId);
    if (!room) {
        return;
    }

    room.members = room.members.filter((member) => member.id !== payload.memberId || member.role === 'owner');
    room.statusLine = `${room.members.length} members · active today`;
}

onMounted(() => {
    if (selectedRoomId.value !== null) {
        clearUnread(selectedRoomId.value);
    }
});
</script>

<template>
    <Head title="Chats" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-0 flex-1 flex-col gap-0 p-0">
            <div class="flex min-h-0 flex-1 flex-col overflow-hidden bg-background md:flex-row">
                <aside
                    class="flex min-h-0 w-full shrink-0 flex-col border-sidebar-border/70 md:w-[30%] md:border-r"
                    :class="mobilePane === 'thread' ? 'hidden md:flex' : 'flex'"
                >
                    <div class="flex h-16 items-center border-b border-sidebar-border/70 px-4">
                        <h1 class="text-xl font-semibold tracking-tight text-foreground md:text-2xl">Chats</h1>
                    </div>

                    <div class="border-b border-sidebar-border/70 px-4 py-3">
                        <div class="relative">
                            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input v-model="search" placeholder="Search groups" class="h-9 pl-9" />
                        </div>
                    </div>

                    <ScrollArea class="min-h-0 flex-1">
                        <div class="px-2 py-2">
                            <button
                                v-for="item in filteredRooms"
                                :key="item.id"
                                type="button"
                                class="mb-1 flex w-full items-start gap-3 rounded-lg px-2 py-2 text-left transition hover:bg-primary/5"
                                :class="item.id === selectedRoomId ? 'bg-primary/10 shadow-sm ring-1 ring-primary/30' : ''"
                                @click="openRoom(item.id)"
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
                                    </div>
                                </div>
                            </button>

                            <div
                                v-if="filteredRooms.length === 0"
                                class="rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 px-3 py-5 text-center text-sm text-muted-foreground"
                            >
                                No group chats found.
                            </div>
                        </div>
                    </ScrollArea>
                </aside>

                <section
                    class="flex min-h-0 min-w-0 flex-1 flex-col bg-muted/15 md:w-[70%]"
                    :class="mobilePane === 'list' ? 'hidden md:flex' : 'flex'"
                >
                    <template v-if="activeRoom">
                        <ChatThreadHeader :room="activeRoom" @back="backToChatList" @open-info="groupInfoOpen = true" />
                        <ChatMessageList :messages="activeRoom.messages" />
                        <ChatComposer v-model="draftMessage" @send="sendMessage" />
                    </template>

                    <div v-else class="flex min-h-0 flex-1 items-center justify-center px-6 text-center">
                        <p class="text-base font-semibold text-foreground">No chat selected</p>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>

    <ChatGroupInfoSheet
        v-model:open="groupInfoOpen"
        :room="activeRoom"
        @update-room="updateRoom"
        @update-member-role="updateMemberRole"
        @remove-member="removeMember"
    />

</template>
