<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import EmployeeInformationSections from '@/components/hris/EmployeeInformationSections.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AboutMeWorkPayload } from '@/pages/Employees/aboutMeWorkTypes';
import type { EmployeeProfileDisplay } from '@/pages/Employees/employeeProfileDisplay';
import { employees } from '@/routes';
import { aboutMe } from '@/routes/employees';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        profile: EmployeeProfileDisplay;
        aboutMeWork?: AboutMeWorkPayload | null;
        canEditAboutMeHris?: boolean;
    }>(),
    {
        aboutMeWork: null,
        canEditAboutMeHris: false,
    },
);

/** Deep clone plain Inertia/Vue proxy props (structuredClone rejects reactive Proxies). */
function cloneProfile(value: EmployeeProfileDisplay): EmployeeProfileDisplay {
    return JSON.parse(JSON.stringify(value)) as EmployeeProfileDisplay;
}

const profile = ref<EmployeeProfileDisplay>(cloneProfile(props.profile));

watch(
    () => props.profile,
    (next) => {
        profile.value = cloneProfile(next);
    },
    { deep: true },
);

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
            <EmployeeInformationSections
                variant="self"
                :profile="profile"
                :about-me-work="props.aboutMeWork"
                :can-edit-about-me-hris="props.canEditAboutMeHris"
            />
        </div>
    </AppLayout>
</template>
