<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocumentsDriveBrowser from '@/components/hris/documents/DocumentsDriveBrowser.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { trash as documentsTrash } from '@/routes/documents';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trash', href: documentsTrash() },
];
</script>

<template>
    <Head title="Trash" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-0 flex-1 flex-col gap-4 p-4 lg:px-16">
            <div class="flex flex-col gap-1">
                <h1 class="text-xl font-semibold text-foreground">Trash</h1>
                <p class="text-sm text-muted-foreground">
                    Restore deleted items or remove them permanently (session
                    preview — stored in this browser only).
                </p>
            </div>
            <DocumentsDriveBrowser variant="trash" />
        </div>
    </AppLayout>
</template>
