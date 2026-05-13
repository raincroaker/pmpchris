<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue';
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

type PageErrorsBag = Record<string, string>;

const props = withDefaults(
    defineProps<{
        open: boolean;
        submitting?: boolean;
        title?: string;
        description?: string;
        confirmLabel?: string;
        /** Server-side errors for `current_password` / `current_password_confirmation` */
        errors?: PageErrorsBag;
    }>(),
    {
        submitting: false,
        title: 'Confirm your password',
        description:
            'For security, enter your current account password to apply these changes.',
        confirmLabel: 'Confirm and save',
        errors: () => ({}),
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [
        payload: {
            current_password: string;
            current_password_confirmation: string;
        },
    ];
}>();

const id = useId();
const passwordId = `${id}-current`;
const confirmId = `${id}-confirm`;

const password = ref('');
const passwordConfirmation = ref('');
const localErrors = ref<PageErrorsBag>({});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            password.value = '';
            passwordConfirmation.value = '';
            localErrors.value = {};
        }
    },
);

const passwordError = computed(
    () => props.errors?.current_password ?? localErrors.value.current_password,
);

const confirmationError = computed(
    () =>
        props.errors?.current_password_confirmation ??
        localErrors.value.current_password_confirmation,
);

function close(): void {
    emit('update:open', false);
}

function onConfirm(): void {
    localErrors.value = {};
    if (password.value.trim() === '') {
        localErrors.value = {
            current_password: 'Enter your current password.',
        };

        return;
    }

    if (passwordConfirmation.value.trim() === '') {
        localErrors.value = {
            current_password_confirmation: 'Confirm your current password.',
        };

        return;
    }

    if (password.value !== passwordConfirmation.value) {
        localErrors.value = {
            current_password_confirmation:
                'Password confirmation must match.',
        };

        return;
    }

    emit('confirm', {
        current_password: password.value,
        current_password_confirmation: passwordConfirmation.value,
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="z-100 gap-4 border-primary/30 shadow-lg sm:max-w-md"
            @pointer-down-outside="
                (e: Event) => {
                    if (submitting) {
                        e.preventDefault();
                    }
                }
            "
        >
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label :for="passwordId">Current password</Label>
                    <Input
                        :id="passwordId"
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        :aria-invalid="Boolean(passwordError)"
                        @keydown.enter.prevent="onConfirm"
                    />
                    <p
                        v-if="passwordError"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ passwordError }}
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label :for="confirmId">Confirm current password</Label>
                    <Input
                        :id="confirmId"
                        v-model="passwordConfirmation"
                        type="password"
                        autocomplete="current-password"
                        :aria-invalid="Boolean(confirmationError)"
                        @keydown.enter.prevent="onConfirm"
                    />
                    <p
                        v-if="confirmationError"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ confirmationError }}
                    </p>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="submitting"
                    @click="close"
                >
                    Back
                </Button>
                <Button
                    type="button"
                    :disabled="submitting"
                    @click="onConfirm"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
