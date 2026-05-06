<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import EmployeeInformationSections from '@/components/hris/EmployeeInformationSections.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AboutMeWorkPayload } from '@/pages/Employees/aboutMeWorkTypes';
import type { EmployeeProfileDisplay } from '@/pages/Employees/employeeProfileDisplay';
import { employees } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        employeeId: number;
        profile: EmployeeProfileDisplay;
        canEditAboutMeHris?: boolean;
        aboutMeWork?: AboutMeWorkPayload | null;
    }>(),
    {
        canEditAboutMeHris: false,
        aboutMeWork: null,
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

function setProfile(next: EmployeeProfileDisplay): void {
    profile.value = cloneProfile(next);
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Employees', href: employees() },
    {
        title: profile.value.display_name,
        href: `/employees/${props.employeeId}`,
    },
]);
</script>

<template>
    <Head :title="`${profile.display_name} · Employee`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full min-h-0 flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 lg:px-16"
        >
            <EmployeeInformationSections
                variant="hr"
                :profile="profile"
                :about-me-work="props.aboutMeWork"
                :can-edit-about-me-hris="props.canEditAboutMeHris"
                @update:profile="setProfile"
            />
        </div>
    </AppLayout>
</template>
