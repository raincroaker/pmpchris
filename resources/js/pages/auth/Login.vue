<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { AlertTriangle, Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DialogClose,
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { appToast } from '@/lib/app-toast-client';
import { store } from '@/routes/login';

const showPassword = ref(false);
const isLoggingIn = ref(false);
const portalAccessDialogOpen = ref(false);
const portalAccessMessage = ref('');

let resolveLoginToast: (() => void) | undefined;
let rejectLoginToast: ((reason?: unknown) => void) | undefined;

function onLoginStart() {
    if (isLoggingIn.value) {
        return;
    }
    isLoggingIn.value = true;

    const p = new Promise<void>((resolve, reject) => {
        resolveLoginToast = resolve;
        rejectLoginToast = reject;
    });

    appToast.promise(p, {
        loading: 'Signing in...',
        success: 'Welcome back!',
        error: 'Login failed',
    });

    portalAccessDialogOpen.value = false;
    portalAccessMessage.value = '';
}

function onLoginSuccess() {
    resolveLoginToast?.();
    resolveLoginToast = undefined;
    rejectLoginToast = undefined;
    isLoggingIn.value = false;
    portalAccessDialogOpen.value = false;
    portalAccessMessage.value = '';
}

function extractErrorMessage(
    value: string | string[] | undefined,
): string | undefined {
    if (Array.isArray(value)) {
        return value[0];
    }

    return value;
}

function onLoginError(errors: Record<string, string | string[]>) {
    rejectLoginToast?.();
    resolveLoginToast = undefined;
    rejectLoginToast = undefined;
    isLoggingIn.value = false;

    const message = extractErrorMessage(errors.portal_access);

    if (message) {
        portalAccessMessage.value = message;
        portalAccessDialogOpen.value = true;
    }
}

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthBase
        title="Log-in"
        description="Enter your email and password below to log in"
        logo-src="/images/HRNexus_Login.png"
        logo-alt="HRNexus"
    >
        <Head title="Log in" />

        <Dialog v-model:open="portalAccessDialogOpen">
            <DialogContent
                class="gap-4 border-amber-300 bg-amber-50/95 p-5 shadow-lg shadow-amber-900/10 sm:max-w-md dark:border-amber-500/40 dark:bg-amber-500/10"
            >
                <DialogHeader class="space-y-2 text-left">
                    <DialogTitle
                        class="flex items-start gap-2.5 text-base text-amber-900 dark:text-amber-200"
                    >
                        <AlertTriangle
                            class="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-300"
                            aria-hidden="true"
                        />
                        <span>Access no longer available</span>
                    </DialogTitle>
                    <DialogDescription
                        class="pl-6.5 text-sm leading-relaxed text-amber-900/85 dark:text-amber-100/90"
                    >
                        {{ portalAccessMessage }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="pt-1">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            class="w-full bg-amber-700 text-white hover:bg-amber-800 sm:w-auto dark:bg-amber-500 dark:text-amber-950 dark:hover:bg-amber-400"
                        >
                            Understood
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            :on-start="onLoginStart"
            :on-success="onLoginSuccess"
            :on-error="onLoginError"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">Password</Label>
                    </div>
                    <div class="relative">
                        <Input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            placeholder="Password"
                            class="pr-10"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="absolute top-1/2 right-0 mr-1 size-8 shrink-0 -translate-y-1/2 cursor-pointer rounded-md text-muted-foreground hover:bg-transparent hover:text-foreground"
                            aria-label="Toggle password visibility"
                            @click="showPassword = !showPassword"
                        >
                            <Eye v-if="!showPassword" class="size-4" />
                            <EyeOff v-else class="size-4" />
                        </Button>
                    </div>
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-2">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" />
                    Log in
                </Button>

                <div class="text-center text-xs text-muted-foreground">
                    <Dialog>
                        <span>By logging in, you agree to our </span>
                        <DialogTrigger as-child>
                            <Button
                                type="button"
                                variant="link"
                                class="h-auto cursor-pointer p-0 text-xs font-medium text-primary underline underline-offset-2 hover:text-primary/80"
                            >
                                Terms &amp; Privacy
                            </Button>
                        </DialogTrigger>
                        <span>.</span>
                        <DialogContent class="sm:max-w-2xl">
                            <DialogHeader>
                                <DialogTitle
                                    >Terms &amp; Privacy Notice</DialogTitle
                                >
                                <DialogDescription>
                                    This notice explains the general
                                    responsibilities and data handling
                                    expectations when using this system.
                                </DialogDescription>
                            </DialogHeader>
                            <ScrollArea class="max-h-[60vh] pr-4">
                                <div
                                    class="space-y-4 text-sm leading-relaxed text-muted-foreground"
                                >
                                    <p>
                                        By accessing and using this system, you
                                        acknowledge that information you provide
                                        or generate through use of the platform
                                        may be collected, stored, and processed
                                        for legitimate organizational and
                                        operational purposes.
                                    </p>
                                    <ol class="list-decimal space-y-3 pl-5">
                                        <li>
                                            You agree to use the system only for
                                            authorized and lawful purposes, in
                                            line with company policies and
                                            professional standards.
                                        </li>
                                        <li>
                                            Information in this system is
                                            handled to support normal operations
                                            and service delivery, and should be
                                            maintained with reasonable accuracy.
                                        </li>
                                        <li>
                                            Access is restricted to authorized
                                            users based on assigned roles and
                                            responsibilities.
                                        </li>
                                        <li>
                                            Unauthorized access, misuse,
                                            disclosure, copying, or sharing of
                                            information is prohibited.
                                        </li>
                                        <li>
                                            Users are expected to protect
                                            account credentials and promptly
                                            report suspected unauthorized
                                            activity.
                                        </li>
                                        <li>
                                            System use remains subject to
                                            applicable internal policies and
                                            relevant legal or regulatory
                                            obligations.
                                        </li>
                                        <li>
                                            These terms may be updated from time
                                            to time; continued use indicates
                                            acknowledgment of the latest
                                            version.
                                        </li>
                                    </ol>
                                    <p>
                                        By continuing to use this system, you
                                        confirm that you have read and
                                        understood this Terms &amp; Privacy
                                        Notice.
                                    </p>
                                </div>
                            </ScrollArea>
                            <DialogFooter>
                                <DialogClose as-child>
                                    <Button
                                        type="button"
                                        class="w-full sm:w-auto"
                                    >
                                        Agree
                                    </Button>
                                </DialogClose>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </Form>
    </AuthBase>
</template>
