<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { auditLogs as adminAuditLogs } from '@/routes/admin';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Audit logs', href: adminAuditLogs() },
];
</script>

<template>
    <Head title="Audit logs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1 class="text-xl font-semibold text-foreground">Audit logs</h1>
            <p class="text-sm text-muted-foreground">
                Placeholder for security and compliance audit trail.
            </p>
        </div>
    </AppLayout>
</template>
