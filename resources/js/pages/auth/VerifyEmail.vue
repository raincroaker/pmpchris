<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { appToast } from '@/lib/app-toast-client';
import { createLogoutFinishedPromise } from '@/lib/logout-toast-promise';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { inertiaRouteForm } from '@/wayfinder';

defineProps<{
    status?: string;
}>();

let loggingOut = false;

function handleLogout() {
    if (loggingOut) {
        return;
    }
    loggingOut = true;
    router.flushAll();
    const p = createLogoutFinishedPromise().finally(() => {
        loggingOut = false;
    });
    appToast.promise(p, {
        loading: 'Logging out...',
        success: 'Logged out',
        error: 'Logout failed',
    });
}
</script>

<template>
    <AuthLayout
        title="Verify email"
        description="Please verify your email address by clicking on the link we just emailed to you."
    >
        <Head title="Email verification" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            A new verification link has been sent to the email address you
            provided during registration.
        </div>

        <Form
            v-bind="inertiaRouteForm(send())"
            class="space-y-6 text-center"
            v-slot="{ processing }"
        >
            <Button :disabled="processing" variant="secondary">
                <Spinner v-if="processing" />
                Resend verification email
            </Button>

            <TextLink
                :href="logout()"
                as="button"
                class="mx-auto block text-sm"
                @click="handleLogout"
            >
                Log out
            </TextLink>
        </Form>
    </AuthLayout>
</template>
