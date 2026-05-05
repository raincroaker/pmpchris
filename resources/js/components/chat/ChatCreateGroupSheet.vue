<script setup lang="ts">
import { ArrowLeft, Check, Search, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import type { EmployeeDirectoryItem } from '@/components/chat/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';

const open = defineModel<boolean>('open', { default: false });

const props = defineProps<{
    employees: EmployeeDirectoryItem[];
}>();

const emit = defineEmits<{
    (event: 'create-group', payload: { name: string; description: string; memberIds: number[] }): void;
}>();

const step = ref<1 | 2 | 3>(1);
const groupName = ref('');
const groupDescription = ref('');
const search = ref('');
const selectedMemberIds = ref<number[]>([]);

const selectedMembers = computed(() =>
    props.employees.filter((employee) => selectedMemberIds.value.includes(employee.id)),
);

const filteredEmployees = computed(() => {
    const keyword = search.value.trim().toLowerCase();
    if (keyword === '') {
        return props.employees;
    }

    return props.employees.filter((employee) => {
        return (
            employee.name.toLowerCase().includes(keyword) ||
            employee.employeeCode.toLowerCase().includes(keyword) ||
            employee.statusLine.toLowerCase().includes(keyword)
        );
    });
});

const canProceedStep1 = computed(() => groupName.value.trim() !== '');
const canProceedStep2 = computed(() => selectedMemberIds.value.length > 0);
const canCreate = computed(() => canProceedStep1.value && canProceedStep2.value);

watch(open, (isOpen) => {
    if (isOpen) {
        resetDraft();
    }
});

function resetDraft(): void {
    step.value = 1;
    groupName.value = '';
    groupDescription.value = '';
    search.value = '';
    selectedMemberIds.value = [];
}

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
        return 'E';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 1).toUpperCase();
    }

    return `${parts[0].slice(0, 1)}${parts[parts.length - 1].slice(0, 1)}`.toUpperCase();
}

function toggleMember(memberId: number): void {
    if (selectedMemberIds.value.includes(memberId)) {
        selectedMemberIds.value = selectedMemberIds.value.filter((id) => id !== memberId);
        return;
    }

    selectedMemberIds.value = [...selectedMemberIds.value, memberId];
}

function createGroup(): void {
    if (!canCreate.value) {
        return;
    }

    emit('create-group', {
        name: groupName.value.trim(),
        description: groupDescription.value.trim(),
        memberIds: selectedMemberIds.value,
    });
    open.value = false;
}
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent side="right" class="flex h-dvh w-full flex-col gap-0 sm:max-w-md">
            <SheetHeader class="border-b border-sidebar-border/70 px-4 py-4 text-left">
                <SheetTitle>Create group chat</SheetTitle>
                <SheetDescription>
                    Set group details first, then add members before creating a conversation.
                </SheetDescription>
            </SheetHeader>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div class="grid grid-cols-3 gap-2 border-b border-sidebar-border/70 px-4 py-3">
                    <Button
                        type="button"
                        :variant="step === 1 ? 'default' : 'outline'"
                        size="sm"
                        class="w-full"
                        @click="step = 1"
                    >
                        Details
                    </Button>
                    <Button
                        type="button"
                        :variant="step === 2 ? 'default' : 'outline'"
                        size="sm"
                        class="w-full"
                        @click="step = 2"
                    >
                        Members
                    </Button>
                    <Button
                        type="button"
                        :variant="step === 3 ? 'default' : 'outline'"
                        size="sm"
                        class="w-full"
                        @click="step = 3"
                    >
                        Review
                    </Button>
                </div>

                <ScrollArea class="min-h-0 flex-1">
                    <div v-if="step === 1" class="space-y-4 px-4 py-4">
                        <div class="space-y-2">
                            <Label for="new-group-name">Group name</Label>
                            <Input id="new-group-name" v-model="groupName" placeholder="e.g. Branch Operations Team" />
                        </div>
                        <div class="space-y-2">
                            <Label for="new-group-description">Description</Label>
                            <Textarea
                                id="new-group-description"
                                v-model="groupDescription"
                                class="min-h-24"
                                placeholder="Purpose or reminder for this group"
                            />
                        </div>
                        <div class="rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 p-3 text-xs text-muted-foreground">
                            Group photo upload can be added here once backend file handling is ready.
                        </div>
                    </div>

                    <div v-else-if="step === 2" class="space-y-3 px-4 py-4">
                        <div class="relative">
                            <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="search"
                                class="pl-9"
                                placeholder="Search employees by name, code, or status"
                            />
                        </div>

                        <div class="space-y-1">
                            <button
                                v-for="employee in filteredEmployees"
                                :key="employee.id"
                                type="button"
                                class="flex w-full items-center gap-3 rounded-md border border-sidebar-border/70 bg-muted/20 px-2 py-2 text-left transition hover:bg-muted/30"
                                @click="toggleMember(employee.id)"
                            >
                                <Checkbox
                                    :model-value="selectedMemberIds.includes(employee.id)"
                                    class="pointer-events-none"
                                />
                                <Avatar class="size-8 shrink-0">
                                    <AvatarImage v-if="employee.avatarUrl" :src="employee.avatarUrl" :alt="employee.name" />
                                    <AvatarFallback class="text-[11px]">
                                        {{ initials(employee.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-foreground">
                                        {{ employee.name }}
                                    </p>
                                    <p class="truncate text-xs text-muted-foreground">
                                        {{ employee.employeeCode }} · {{ employee.statusLine }}
                                    </p>
                                </div>
                            </button>

                            <div
                                v-if="filteredEmployees.length === 0"
                                class="rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 p-4 text-sm text-muted-foreground"
                            >
                                No employees match your search.
                            </div>
                        </div>
                    </div>

                    <div v-else class="space-y-3 px-4 py-4">
                        <div class="rounded-md border border-sidebar-border/70 bg-muted/20 p-3">
                            <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Group</p>
                            <p class="mt-2 text-sm font-semibold text-foreground">{{ groupName || 'Untitled Group' }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ groupDescription || 'No description provided.' }}
                            </p>
                        </div>
                        <div class="rounded-md border border-sidebar-border/70 bg-muted/20 p-3">
                            <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Members</p>
                            <p class="mt-2 text-sm text-foreground">
                                {{ selectedMembers.length }} selected
                            </p>
                            <Separator class="my-2" />
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-for="member in selectedMembers" :key="member.id" variant="outline">
                                    {{ member.name }}
                                </Badge>
                            </div>
                        </div>
                        <div
                            v-if="selectedMembers.length === 0"
                            class="rounded-md border border-dashed border-sidebar-border/70 bg-muted/20 p-3 text-sm text-muted-foreground"
                        >
                            No members selected yet.
                        </div>
                    </div>
                </ScrollArea>

                <div class="border-t border-sidebar-border/70 px-4 py-3">
                    <div class="flex items-center justify-between gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="step === 1"
                            @click="step = step === 1 ? 1 : ((step - 1) as 1 | 2)"
                        >
                            <ArrowLeft class="size-4" />
                            Back
                        </Button>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="step < 3"
                                type="button"
                                :disabled="(step === 1 && !canProceedStep1) || (step === 2 && !canProceedStep2)"
                                @click="step = (step + 1) as 2 | 3"
                            >
                                Continue
                            </Button>
                            <Button v-else type="button" :disabled="!canCreate" @click="createGroup">
                                <Check class="size-4" />
                                Create Group
                            </Button>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        <Users class="mr-1 inline size-3" />
                        Group chats must be created with members.
                    </p>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
