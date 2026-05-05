<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { reports as attendanceReports } from '@/routes/attendance';
import { dtrMockSample } from '@/routes/attendance/reports';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Attendance reports', href: attendanceReports() },
];

const dtrMockSampleUrl = dtrMockSample.url();
</script>

<template>
    <Head title="Attendance reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <h1 class="text-xl font-semibold text-foreground">
                Attendance reports
            </h1>
            <p class="text-sm text-muted-foreground">
                Placeholder for attendance analytics and exports.
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <Button variant="outline" as-child>
                    <a :href="dtrMockSampleUrl">Download DTR sample (Excel)</a>
                </Button>
                <p class="text-xs text-muted-foreground">
                    Mock data only — opens as a normal file download (not
                    Inertia).
                </p>
            </div>
        </div>
    </AppLayout>
</template>
