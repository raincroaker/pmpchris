<script setup lang="ts">
import {
    BellOff,
    Images,
    Link2,
    MoreHorizontal,
    Pencil,
    Pin,
    Trash2,
    UserMinus,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { ChatMember, ChatRoom } from '@/components/chat/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
    room: ChatRoom | null;
}>();

const emit = defineEmits<{
    (event: 'update-room', payload: { roomId: number; updates: Partial<ChatRoom> }): void;
    (event: 'update-member-role', payload: { roomId: number; memberId: number; role: ChatMember['role'] }): void;
    (event: 'remove-member', payload: { roomId: number; memberId: number }): void;
}>();

const activeTab = ref<'about' | 'members' | 'media' | 'settings'>('about');
const memberSearch = ref('');
const editName = ref(false);
const editDescription = ref(false);
const nameDraft = ref('');
const descriptionDraft = ref('');
const leaveDialogOpen = ref(false);
const deleteDialogOpen = ref(false);

const filteredMembers = computed(() => {
    const room = props.room;
    if (!room) {
        return [];
    }

    const keyword = memberSearch.value.trim().toLowerCase();
    if (keyword === '') {
        return room.members;
    }

    return room.members.filter((member) => {
        return (
            member.name.toLowerCase().includes(keyword) ||
            member.employeeCode.toLowerCase().includes(keyword) ||
            member.statusLine.toLowerCase().includes(keyword)
        );
    });
});

watch(
    () => props.room,
    (room) => {
        if (!room) {
            return;
        }

        nameDraft.value = room.name;
        descriptionDraft.value = room.description;
        activeTab.value = 'about';
        editName.value = false;
        editDescription.value = false;
        memberSearch.value = '';
    },
    { immediate: true },
);

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

function roleLabel(role: ChatMember['role']): string {
    if (role === 'owner') {
        return 'Owner';
    }
    if (role === 'admin') {
        return 'Admin';
    }

    return 'Member';
}

function saveName(): void {
    const room = props.room;
    const nextName = nameDraft.value.trim();
    if (!room || nextName === '') {
        return;
    }

    emit('update-room', {
        roomId: room.id,
        updates: {
            name: nextName,
        },
    });
    editName.value = false;
}

function saveDescription(): void {
    const room = props.room;
    if (!room) {
        return;
    }

    emit('update-room', {
        roomId: room.id,
        updates: {
            description: descriptionDraft.value.trim(),
        },
    });
    editDescription.value = false;
}

function setRoomFlag(field: 'isMuted' | 'isPinned', value: boolean): void {
    const room = props.room;
    if (!room) {
        return;
    }

    emit('update-room', {
        roomId: room.id,
        updates: {
            [field]: value,
        },
    });
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="flex h-dvh w-full flex-col gap-0 sm:max-w-md">
            <template v-if="room">
                <SheetHeader class="border-b border-sidebar-border/70 px-4 py-4 text-left">
                    <SheetTitle class="pr-8">Group info</SheetTitle>
                    <SheetDescription>
                        Review members, profile details, and group settings.
                    </SheetDescription>
                </SheetHeader>

                <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <ScrollArea class="min-h-0 flex-1">
                        <div class="space-y-4 px-4 py-4">
                            <div class="flex items-start gap-3">
                                <Avatar class="size-12 shrink-0">
                                    <AvatarImage v-if="room.avatarUrl" :src="room.avatarUrl" :alt="room.name" />
                                    <AvatarFallback>
                                        {{ initials(room.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-foreground">
                                        {{ room.name }}
                                    </p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ room.members.length }} members · Created {{ room.createdLabel }}
                                    </p>
                                </div>
                            </div>

                            <Tabs v-model="activeTab" class="flex min-h-0 flex-col gap-3">
                                <TabsList class="grid grid-cols-4">
                                    <TabsTrigger value="about">About</TabsTrigger>
                                    <TabsTrigger value="members">Members</TabsTrigger>
                                    <TabsTrigger value="media">Media</TabsTrigger>
                                    <TabsTrigger value="settings">Settings</TabsTrigger>
                                </TabsList>

                                <TabsContent value="about" class="space-y-3 focus-visible:outline-none">
                                    <div class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                Group Name
                                            </p>
                                            <Button type="button" size="icon-sm" variant="ghost" @click="editName = !editName">
                                                <Pencil class="size-4" />
                                            </Button>
                                        </div>
                                        <div v-if="editName" class="mt-2 space-y-2">
                                            <Input v-model="nameDraft" placeholder="Group name" />
                                            <div class="flex items-center justify-end gap-2">
                                                <Button type="button" variant="outline" size="sm" @click="editName = false">Cancel</Button>
                                                <Button type="button" size="sm" @click="saveName">Save</Button>
                                            </div>
                                        </div>
                                        <p v-else class="mt-2 text-sm text-foreground">{{ room.name }}</p>
                                    </div>

                                    <div class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                                Description
                                            </p>
                                            <Button
                                                type="button"
                                                size="icon-sm"
                                                variant="ghost"
                                                @click="editDescription = !editDescription"
                                            >
                                                <Pencil class="size-4" />
                                            </Button>
                                        </div>
                                        <div v-if="editDescription" class="mt-2 space-y-2">
                                            <Textarea v-model="descriptionDraft" class="min-h-20" placeholder="Describe this group" />
                                            <div class="flex items-center justify-end gap-2">
                                                <Button type="button" variant="outline" size="sm" @click="editDescription = false">Cancel</Button>
                                                <Button type="button" size="sm" @click="saveDescription">Save</Button>
                                            </div>
                                        </div>
                                        <p v-else class="mt-2 text-sm text-foreground">
                                            {{ room.description || 'No description yet.' }}
                                        </p>
                                    </div>

                                    <div class="grid grid-cols-3 gap-2">
                                        <div class="rounded-md border border-sidebar-border/70 bg-muted/20 p-2">
                                            <p class="text-[11px] text-muted-foreground">Members</p>
                                            <p class="mt-1 text-sm font-semibold text-foreground">
                                                {{ room.members.length }}
                                            </p>
                                        </div>
                                        <div class="rounded-md border border-sidebar-border/70 bg-muted/20 p-2">
                                            <p class="text-[11px] text-muted-foreground">Media</p>
                                            <p class="mt-1 text-sm font-semibold text-foreground">
                                                {{ room.mediaItems.filter((item) => item.kind === 'image').length }}
                                            </p>
                                        </div>
                                        <div class="rounded-md border border-sidebar-border/70 bg-muted/20 p-2">
                                            <p class="text-[11px] text-muted-foreground">Files</p>
                                            <p class="mt-1 text-sm font-semibold text-foreground">
                                                {{ room.mediaItems.filter((item) => item.kind === 'file').length }}
                                            </p>
                                        </div>
                                    </div>
                                </TabsContent>

                                <TabsContent value="members" class="space-y-3 focus-visible:outline-none">
                                    <div class="space-y-2">
                                        <Label for="member-search">Search members</Label>
                                        <Input id="member-search" v-model="memberSearch" placeholder="Search name, code, or status" />
                                    </div>
                                    <Button type="button" variant="outline" class="w-full justify-start">
                                        <UserPlus class="size-4" />
                                        Add members
                                    </Button>

                                    <div class="space-y-1">
                                        <div
                                            v-for="member in filteredMembers"
                                            :key="member.id"
                                            class="flex items-center gap-2 rounded-md border border-sidebar-border/70 bg-muted/20 px-2 py-2"
                                        >
                                            <Avatar class="size-8 shrink-0">
                                                <AvatarImage v-if="member.avatarUrl" :src="member.avatarUrl" :alt="member.name" />
                                                <AvatarFallback class="text-[11px]">
                                                    {{ initials(member.name) }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-foreground">
                                                    {{ member.name }}
                                                </p>
                                                <p class="truncate text-xs text-muted-foreground">
                                                    {{ member.employeeCode }} · {{ member.statusLine }}
                                                </p>
                                            </div>
                                            <Badge variant="outline" class="capitalize">
                                                {{ roleLabel(member.role) }}
                                            </Badge>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger :as-child="true">
                                                    <Button type="button" variant="ghost" size="icon-sm">
                                                        <MoreHorizontal class="size-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" class="min-w-44">
                                                    <DropdownMenuItem
                                                        :disabled="member.role === 'admin'"
                                                        @select="
                                                            emit('update-member-role', {
                                                                roomId: room.id,
                                                                memberId: member.id,
                                                                role: 'admin',
                                                            })
                                                        "
                                                    >
                                                        Promote to admin
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        :disabled="member.role !== 'admin'"
                                                        @select="
                                                            emit('update-member-role', {
                                                                roomId: room.id,
                                                                memberId: member.id,
                                                                role: 'member',
                                                            })
                                                        "
                                                    >
                                                        Make member
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        class="text-destructive focus:text-destructive"
                                                        :disabled="member.role === 'owner'"
                                                        @select="
                                                            emit('remove-member', {
                                                                roomId: room.id,
                                                                memberId: member.id,
                                                            })
                                                        "
                                                    >
                                                        <UserMinus class="size-4" />
                                                        Remove from group
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>

                                    <div
                                        v-if="filteredMembers.length === 0"
                                        class="rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 p-4 text-sm text-muted-foreground"
                                    >
                                        No matching members found.
                                    </div>
                                </TabsContent>

                                <TabsContent value="media" class="space-y-2 focus-visible:outline-none">
                                    <div
                                        v-for="item in room.mediaItems"
                                        :key="item.id"
                                        class="flex items-center gap-3 rounded-md border border-sidebar-border/70 bg-muted/20 px-3 py-2"
                                    >
                                        <Images v-if="item.kind === 'image'" class="size-4 shrink-0 text-muted-foreground" />
                                        <Link2 v-else-if="item.kind === 'link'" class="size-4 shrink-0 text-muted-foreground" />
                                        <Users v-else class="size-4 shrink-0 text-muted-foreground" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-foreground">
                                                {{ item.title }}
                                            </p>
                                            <p class="truncate text-xs text-muted-foreground">
                                                {{ item.subtitle }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        v-if="room.mediaItems.length === 0"
                                        class="rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 p-4 text-sm text-muted-foreground"
                                    >
                                        No shared media or files yet.
                                    </div>
                                </TabsContent>

                                <TabsContent value="settings" class="space-y-3 focus-visible:outline-none">
                                    <div class="rounded-lg border border-sidebar-border/70 bg-muted/20 p-3">
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-foreground">Mute notifications</p>
                                                <p class="text-xs text-muted-foreground">Silence alerts from this group.</p>
                                            </div>
                                            <Switch
                                                :model-value="room.isMuted"
                                                @update:model-value="(value) => setRoomFlag('isMuted', value)"
                                            />
                                        </div>
                                        <Separator class="my-3" />
                                        <div class="flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-foreground">Pin chat</p>
                                                <p class="text-xs text-muted-foreground">Keep this chat near the top list.</p>
                                            </div>
                                            <Switch
                                                :model-value="room.isPinned"
                                                @update:model-value="(value) => setRoomFlag('isPinned', value)"
                                            />
                                        </div>
                                    </div>

                                    <Button type="button" variant="outline" class="w-full justify-start">
                                        <BellOff class="size-4" />
                                        Mark as unread
                                    </Button>
                                    <Button type="button" variant="outline" class="w-full justify-start">
                                        <Pin class="size-4" />
                                        Pin in sidebar
                                    </Button>
                                    <Separator />
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        class="w-full justify-start"
                                        @click="leaveDialogOpen = true"
                                    >
                                        <UserMinus class="size-4" />
                                        Leave group
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        class="w-full justify-start"
                                        @click="deleteDialogOpen = true"
                                    >
                                        <Trash2 class="size-4" />
                                        Delete group
                                    </Button>
                                </TabsContent>
                            </Tabs>
                        </div>
                    </ScrollArea>
                </div>
            </template>
        </SheetContent>
    </Sheet>

    <Dialog v-model:open="leaveDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Leave group?</DialogTitle>
                <DialogDescription>
                    This is a frontend-only confirmation for now. Backend leave flow can be wired later.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-2">
                <Button type="button" variant="outline" @click="leaveDialogOpen = false">Cancel</Button>
                <Button type="button" variant="destructive" @click="leaveDialogOpen = false">Leave Group</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Delete group?</DialogTitle>
                <DialogDescription>
                    This is a frontend-only confirmation. Deletion constraints will be added with backend wiring.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-2">
                <Button type="button" variant="outline" @click="deleteDialogOpen = false">Cancel</Button>
                <Button type="button" variant="destructive" @click="deleteDialogOpen = false">Delete Group</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
