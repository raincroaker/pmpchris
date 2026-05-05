<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocumentsDriveBrowser from '@/components/hris/documents/DocumentsDriveBrowser.vue';
import AppLayout from '@/layouts/AppLayout.vue';
</script>

<template>
    <Head title="My Documents" />

    <AppLayout :breadcrumbs="[]">
        <div class="flex h-full min-h-0 flex-1 flex-col gap-4 p-4 lg:px-16">
            <div class="flex flex-col gap-1">
                <h1 class="text-xl font-semibold text-foreground">
                    My Documents
                </h1>
                <p class="text-sm text-muted-foreground">
                    Everything you have uploaded (session preview). Filter by
                    approval status — uploads happen from Team, Branch, or
                    Company libraries.
                </p>
            </div>
            <DocumentsDriveBrowser scope="my" />
        </div>
    </AppLayout>
</template>
