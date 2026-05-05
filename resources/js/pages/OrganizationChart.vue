<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import OrganizationChartFlowShell from '@/components/OrganizationChartFlowShell.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { OrgChartNodeSpec } from '@/lib/build-organization-chart';
import { organizationChart } from '@/routes';
import type { Branch } from '@/types';
import type { BreadcrumbItem } from '@/types';

defineProps<{
    orgChart?: { nodes: OrgChartNodeSpec[] } | null;
    chartScope?: 'branch' | 'all';
    chartUnitStatus?: 'active' | 'all';
    chartBranchId?: number | null;
    chartBranches?: Branch[];
    chartAreas?: Array<{ id: number; code: string; name: string }>;
    chartCapabilities?: {
        canManageBranch: boolean;
        canManageOrganizationNode: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Organization Chart',
        href: organizationChart(),
    },
];
</script>

<template>
    <Head title="Organization Chart" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full min-h-0 flex-1 flex-col gap-0 p-0">
            <OrganizationChartFlowShell
                class="min-h-0 flex-1"
                :org-chart="orgChart"
                :chart-scope="chartScope ?? 'branch'"
                :chart-unit-status="chartUnitStatus ?? 'active'"
                :chart-branch-id="chartBranchId ?? null"
                :chart-branches="chartBranches ?? []"
                :chart-areas="chartAreas ?? []"
                :chart-capabilities="
                    chartCapabilities ?? {
                        canManageBranch: false,
                        canManageOrganizationNode: false,
                    }
                "
            />
        </div>
    </AppLayout>
</template>
