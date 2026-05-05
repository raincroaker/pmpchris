<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmployeeInformationSections from '@/components/hris/EmployeeInformationSections.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { mockAboutMeProfile } from '@/pages/Employees/employeeProfileDisplay';
import { employees } from '@/routes';
import { aboutMe } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';

const profile = computed(() => mockAboutMeProfile());

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Employees', href: employees() },
    { title: 'About Me', href: aboutMe() },
];
</script>

<template>
    <Head title="About Me" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <EmployeeInformationSections variant="self" :profile="profile" />
        </div>
    </AppLayout>
</template>
