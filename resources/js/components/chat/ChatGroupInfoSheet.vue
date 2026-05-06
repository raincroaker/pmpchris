<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { ChatRoom } from '@/components/chat/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
    room: ChatRoom | null;
}>();
const memberSearch = ref('');

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
    <Sheet v-model:open="open">
        <SheetContent
            side="right"
            class="flex h-dvh w-full flex-col gap-0 sm:max-w-md"
        >
            <template v-if="room">
                <SheetHeader
                    class="border-b border-sidebar-border/70 px-4 py-4 text-left"
                >
                    <SheetTitle class="pr-8">Unit members</SheetTitle>
                    <SheetDescription>
                        Membership is fixed from organization chart assignments.
                    </SheetDescription>
                </SheetHeader>
                <div class="border-b border-sidebar-border/70 px-4 py-3">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="memberSearch"
                            placeholder="Search members"
                            class="h-9 pl-9"
                        />
                    </div>
                </div>

                <ScrollArea class="min-h-0 flex-1">
                    <div class="space-y-2 px-4 py-4">
                        <div
                            v-for="member in filteredMembers"
                            :key="member.id"
                            class="flex items-center gap-2 rounded-md border border-sidebar-border/70 bg-muted/20 px-2 py-2"
                        >
                            <Avatar class="size-8 shrink-0">
                                <AvatarImage
                                    v-if="member.avatarUrl"
                                    :src="member.avatarUrl"
                                    :alt="member.name"
                                />
                                <AvatarFallback class="text-[11px]">
                                    {{ initials(member.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-medium text-foreground"
                                >
                                    {{ member.name }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ member.employeeCode }} ·
                                    {{ member.statusLine }}
                                </p>
                            </div>
                            <Badge
                                v-if="member.role === 'admin'"
                                variant="outline"
                                >Head</Badge
                            >
                        </div>

                        <div
                            v-if="(props.room?.members.length ?? 0) === 0"
                            class="rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 p-4 text-sm text-muted-foreground"
                        >
                            No members assigned yet.
                        </div>
                        <div
                            v-else-if="filteredMembers.length === 0"
                            class="rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 p-4 text-sm text-muted-foreground"
                        >
                            No matching members found.
                        </div>
                    </div>
                </ScrollArea>
            </template>
        </SheetContent>
    </Sheet>
</template>
