<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmployeeInformationSections from '@/components/hris/EmployeeInformationSections.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { mockEmployeeShowProfile } from '@/pages/Employees/employeeProfileDisplay';
import { employees } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    employeeId: number;
}>();

const profile = computed(() => mockEmployeeShowProfile(props.employeeId));

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
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="flex flex-col gap-1">
                    <h1 class="text-xl font-semibold text-foreground">
                        {{ profile.display_name }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Directory ID
                        <span
                            class="font-mono font-medium text-foreground tabular-nums"
                            >{{ props.employeeId }}</span
                        >
                        · actions below follow your HR policies once enabled.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        disabled
                        class="rounded-lg"
                    >
                        Edit
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled
                        class="rounded-lg"
                    >
                        End employment
                    </Button>
                </div>
            </div>

            <EmployeeInformationSections variant="hr" :profile="profile" />
        </div>
    </AppLayout>
</template>
