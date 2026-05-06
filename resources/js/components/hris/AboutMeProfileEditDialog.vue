<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import updateEmployeeAboutMeBasics from '@/actions/App/Http/Controllers/UpdateEmployeeAboutMeBasicsController';
import {
    aboutMeClearFieldButtonClass,
    aboutMeDialogScrollAreaClass,
    aboutMeLabelRowClass,
} from '@/components/hris/aboutMeDialogUi';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useInitials } from '@/composables/useInitials';
import { appToast } from '@/lib/app-toast-client';
import { formatEmployeeDisplayName } from '@/pages/Employees/employeeProfileDisplay';
import type { EmployeeProfileDisplay } from '@/pages/Employees/employeeProfileDisplay';

type PageErrorsBag = Record<string, string>;

const props = defineProps<{
    profile: EmployeeProfileDisplay;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { getInitials } = useInitials();

const profilePhotoHintText = 'JPG, PNG, or WEBP up to 3MB.';

const firstName = ref('');
const middleName = ref('');
const lastName = ref('');
const idNumber = ref('');
const attendanceId = ref('');

const draftAvatarFile = ref<File | null>(null);
const draftAvatarPreviewUrl = ref<string | null>(null);
const removeAvatar = ref(false);
const avatarInputRef = ref<HTMLInputElement | null>(null);

const attemptedSave = ref(false);
const processing = ref(false);
const fieldErrors = ref<PageErrorsBag>({});

const previewDisplayName = computed(() =>
    formatEmployeeDisplayName(
        firstName.value,
        middleName.value,
        lastName.value,
    ),
);

const previewInitials = computed(() =>
    getInitials(previewDisplayName.value || 'Employee'),
);

const avatarImageSrc = computed(() => {
    if (draftAvatarPreviewUrl.value) {
        return draftAvatarPreviewUrl.value;
    }
    if (!removeAvatar.value && props.profile.avatar_url) {
        return props.profile.avatar_url;
    }

    return null;
});

const firstInvalid = computed(
    () => attemptedSave.value && firstName.value.trim() === '',
);
const lastInvalid = computed(
    () => attemptedSave.value && lastName.value.trim() === '',
);
const idInvalid = computed(
    () => attemptedSave.value && idNumber.value.trim() === '',
);

function revokeDraftPreview(): void {
    if (draftAvatarPreviewUrl.value) {
        URL.revokeObjectURL(draftAvatarPreviewUrl.value);
        draftAvatarPreviewUrl.value = null;
    }
}

function resetFromProfile(): void {
    attemptedSave.value = false;
    processing.value = false;
    fieldErrors.value = {};
    firstName.value = props.profile.first_name;
    middleName.value = props.profile.middle_name;
    lastName.value = props.profile.last_name;
    idNumber.value = props.profile.id_number;
    attendanceId.value = props.profile.attendance_id ?? '';
    draftAvatarFile.value = null;
    revokeDraftPreview();
    removeAvatar.value = false;
    if (avatarInputRef.value) {
        avatarInputRef.value.value = '';
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetFromProfile();
        }
    },
);

function onAvatarFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    draftAvatarFile.value = file;
    removeAvatar.value = false;
    revokeDraftPreview();
    if (file) {
        draftAvatarPreviewUrl.value = URL.createObjectURL(file);
    }
}

function clearAvatarSelection(): void {
    draftAvatarFile.value = null;
    revokeDraftPreview();
    removeAvatar.value = true;
    if (avatarInputRef.value) {
        avatarInputRef.value.value = '';
    }
}

function onSave(): void {
    attemptedSave.value = true;
    if (firstInvalid.value || lastInvalid.value || idInvalid.value) {
        return;
    }

    fieldErrors.value = {};

    const payload = {
        first_name: firstName.value.trim(),
        middle_name: middleName.value.trim(),
        last_name: lastName.value.trim(),
        id_number: idNumber.value.trim(),
        attendance_id: attendanceId.value.trim(),
        ...(draftAvatarFile.value !== null
            ? { avatar: draftAvatarFile.value }
            : {}),
        ...(removeAvatar.value ? { remove_avatar: true as const } : {}),
    } satisfies RequestPayload;

    processing.value = true;

    router.patch(
        updateEmployeeAboutMeBasics.url(props.profile.employee_id),
        payload,
        {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                appToast.success('Profile updated.');
                emit('update:open', false);
                router.reload({
                    only: ['profile'],
                });
            },
            onError: (pageErrors: PageErrorsBag) => {
                fieldErrors.value = pageErrors ?? {};
                if (
                    pageErrors !== null &&
                    typeof pageErrors === 'object' &&
                    Object.keys(pageErrors).length === 0
                ) {
                    appToast.error('Could not save profile. Please try again.');
                }
            },
        },
    );
}

function onOpenChange(value: boolean): void {
    emit('update:open', value);
}

const genericFormError = computed((): string | null => {
    const errs = fieldErrors.value;
    const generic = errs.error ?? errs.message;
    return typeof generic === 'string' && generic !== '' ? generic : null;
});
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Edit profile</DialogTitle>
                <DialogDescription>
                    Update how this employee appears in the directory. Changes
                    are saved to HR records immediately.
                </DialogDescription>
            </DialogHeader>

            <ScrollArea :class="aboutMeDialogScrollAreaClass">
                <div class="grid gap-6 px-1 py-1">
                    <p
                        v-if="genericFormError !== null"
                        class="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
                    >
                        {{ genericFormError }}
                    </p>
                    <div
                        class="grid min-w-0 gap-4 sm:grid-cols-[auto_1fr] sm:items-start"
                    >
                        <div
                            class="overflow-hidden rounded-xl border border-border/70 bg-muted/30 shadow-sm"
                        >
                            <Avatar class="size-28 rounded-none sm:size-32">
                                <AvatarImage
                                    v-if="avatarImageSrc"
                                    :src="avatarImageSrc"
                                    alt="Profile photo preview"
                                    class="h-full w-full object-cover object-center"
                                />
                                <AvatarFallback
                                    class="rounded-none bg-muted text-base font-semibold text-foreground"
                                >
                                    {{ previewInitials }}
                                </AvatarFallback>
                            </Avatar>
                        </div>
                        <div class="grid min-w-0 gap-3">
                            <Label
                                for="about_me_profile_photo"
                                :class="aboutMeLabelRowClass"
                            >
                                <span>Profile photo</span>
                                <Badge variant="outline"> Optional </Badge>
                                <Button
                                    v-if="
                                        draftAvatarFile !== null ||
                                        (!removeAvatar && profile.avatar_url)
                                    "
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :class="aboutMeClearFieldButtonClass"
                                    aria-label="Clear selected profile photo"
                                    @click="clearAvatarSelection"
                                >
                                    <X class="size-3.5" />
                                </Button>
                            </Label>
                            <input
                                id="about_me_profile_photo"
                                ref="avatarInputRef"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp"
                                autocomplete="off"
                                class="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30"
                                @change="onAvatarFileChange"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ profilePhotoHintText }}
                            </p>
                            <p
                                v-if="fieldErrors.avatar"
                                class="text-xs text-destructive"
                            >
                                {{ fieldErrors.avatar }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div class="grid gap-3">
                            <Label
                                for="about_me_first_name"
                                :class="aboutMeLabelRowClass"
                            >
                                <span>First name</span>
                                <Badge
                                    v-if="firstInvalid"
                                    variant="destructive"
                                >
                                    Required
                                </Badge>
                                <Button
                                    v-if="firstName !== ''"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :class="aboutMeClearFieldButtonClass"
                                    aria-label="Clear first name"
                                    @click="firstName = ''"
                                >
                                    <X class="size-3.5" />
                                </Button>
                            </Label>
                            <Input
                                id="about_me_first_name"
                                v-model="firstName"
                                class="w-full"
                                autocomplete="given-name"
                                placeholder="e.g. Juan"
                                :aria-invalid="
                                    firstInvalid ||
                                    Boolean(fieldErrors.first_name)
                                "
                            />
                            <p
                                v-if="fieldErrors.first_name"
                                class="text-xs text-destructive"
                            >
                                {{ fieldErrors.first_name }}
                            </p>
                        </div>
                        <div class="grid gap-3">
                            <Label
                                for="about_me_last_name"
                                :class="aboutMeLabelRowClass"
                            >
                                <span>Last name</span>
                                <Badge v-if="lastInvalid" variant="destructive">
                                    Required
                                </Badge>
                                <Button
                                    v-if="lastName !== ''"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :class="aboutMeClearFieldButtonClass"
                                    aria-label="Clear last name"
                                    @click="lastName = ''"
                                >
                                    <X class="size-3.5" />
                                </Button>
                            </Label>
                            <Input
                                id="about_me_last_name"
                                v-model="lastName"
                                class="w-full"
                                autocomplete="family-name"
                                placeholder="e.g. Dela Cruz"
                                :aria-invalid="
                                    lastInvalid ||
                                    Boolean(fieldErrors.last_name)
                                "
                            />
                            <p
                                v-if="fieldErrors.last_name"
                                class="text-xs text-destructive"
                            >
                                {{ fieldErrors.last_name }}
                            </p>
                        </div>
                        <div class="grid gap-3 md:col-span-2 lg:col-span-1">
                            <Label
                                for="about_me_middle_name"
                                :class="aboutMeLabelRowClass"
                            >
                                <span>Middle name</span>
                                <Badge variant="secondary"> Optional </Badge>
                                <Button
                                    v-if="middleName !== ''"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :class="aboutMeClearFieldButtonClass"
                                    aria-label="Clear middle name"
                                    @click="middleName = ''"
                                >
                                    <X class="size-3.5" />
                                </Button>
                            </Label>
                            <Input
                                id="about_me_middle_name"
                                v-model="middleName"
                                class="w-full"
                                autocomplete="additional-name"
                                placeholder="e.g. Santos"
                            />
                            <p
                                v-if="fieldErrors.middle_name"
                                class="text-xs text-destructive"
                            >
                                {{ fieldErrors.middle_name }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-1 gap-x-4 gap-y-6 md:grid-cols-2"
                    >
                        <div class="grid gap-3">
                            <Label
                                for="about_me_id_number"
                                :class="aboutMeLabelRowClass"
                            >
                                <span>ID number</span>
                                <Badge v-if="idInvalid" variant="destructive">
                                    Required
                                </Badge>
                                <Button
                                    v-if="idNumber !== ''"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :class="aboutMeClearFieldButtonClass"
                                    aria-label="Clear ID number"
                                    @click="idNumber = ''"
                                >
                                    <X class="size-3.5" />
                                </Button>
                            </Label>
                            <Input
                                id="about_me_id_number"
                                v-model="idNumber"
                                class="w-full"
                                maxlength="50"
                                autocomplete="off"
                                placeholder="e.g. EMP-001"
                                :aria-invalid="
                                    idInvalid || Boolean(fieldErrors.id_number)
                                "
                            />
                            <p
                                v-if="fieldErrors.id_number"
                                class="text-xs text-destructive"
                            >
                                {{ fieldErrors.id_number }}
                            </p>
                        </div>
                        <div class="grid gap-3">
                            <Label
                                for="about_me_attendance_id"
                                :class="aboutMeLabelRowClass"
                            >
                                <span>Attendance ID</span>
                                <Badge variant="secondary"> Optional </Badge>
                                <Button
                                    v-if="attendanceId !== ''"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :class="aboutMeClearFieldButtonClass"
                                    aria-label="Clear Attendance ID"
                                    @click="attendanceId = ''"
                                >
                                    <X class="size-3.5" />
                                </Button>
                            </Label>
                            <Input
                                id="about_me_attendance_id"
                                v-model="attendanceId"
                                class="w-full"
                                maxlength="50"
                                autocomplete="off"
                                placeholder="e.g. ZK-10042, RFID, or device code"
                            />
                            <p
                                v-if="fieldErrors.attendance_id"
                                class="text-xs text-destructive"
                            >
                                {{ fieldErrors.attendance_id }}
                            </p>
                        </div>
                    </div>
                </div>
            </ScrollArea>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    class="rounded-lg"
                    :disabled="processing"
                    @click="onOpenChange(false)"
                >
                    Cancel
                </Button>
                <Button
                    type="button"
                    class="rounded-lg"
                    :disabled="processing"
                    @click="onSave"
                >
                    Save
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
