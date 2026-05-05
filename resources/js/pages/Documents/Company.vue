<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DocumentsDriveBrowser from '@/components/hris/documents/DocumentsDriveBrowser.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { company as documentsCompany } from '@/routes/documents';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Company Documents', href: documentsCompany() },
];
</script>

<template>
    <Head title="Company Documents" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-0 flex-1 flex-col gap-4 p-4 lg:px-16">
            <div class="flex flex-col gap-1">
                <h1 class="text-xl font-semibold text-foreground">
                    Company Documents
                </h1>
                <p class="text-sm text-muted-foreground">
                    Company-wide policies and assets (session preview).
                </p>
            </div>
            <DocumentsDriveBrowser scope="company" />
        </div>
    </AppLayout>
</template>
