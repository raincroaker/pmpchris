<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { VueFlow } from '@vue-flow/core';
import type { Edge, FitViewParams, VueFlowStore } from '@vue-flow/core';
import { ChevronDown, Undo2 } from 'lucide-vue-next';
import { computed, markRaw, provide, ref, shallowRef, watch } from 'vue';
import OrgChartFlowEdge from '@/components/org-chart/OrgChartFlowEdge.vue';
import OrgChartFlowNode from '@/components/org-chart/OrgChartFlowNode.vue';
import OrgChartNodeSheet from '@/components/org-chart/OrgChartNodeSheet.vue';
import chartConfig from '@/data/organization-chart.json';
import { appToast } from '@/lib/app-toast-client';
import { buildOrganizationChart } from '@/lib/build-organization-chart';
import type {
    OrgChartConfig,
    OrgChartNode,
    OrgChartNodeSpec,
} from '@/lib/build-organization-chart';
import {
    branchVisual,
    departmentVisual,
    genericVisual,
    orgVisual,
    sectionVisual,
} from '@/lib/org-chart-node-visuals';
import {
    createOrgChartSheetBridge,
    orgChartSheetBridgeKey,
} from '@/lib/org-chart-sheet-bridge';
import { organizationChart } from '@/routes';
import type { Branch } from '@/types';

import '@vue-flow/controls/dist/style.css';

/**
 * fitView framing: higher maxZoom allows a tighter (more zoomed-in) fit; padding is viewport margin.
 */
const chartFitViewParams: FitViewParams = {
    maxZoom: 0.75,
    padding: -0.25,
};

const sheetBridge = createOrgChartSheetBridge();
provide(orgChartSheetBridgeKey, sheetBridge);

const props = withDefaults(
    defineProps<{
        /** Server-built subtree; when null or empty, demo JSON is used. */
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
    }>(),
    {
        orgChart: null,
        chartScope: 'branch',
        chartUnitStatus: 'active',
        chartBranchId: null,
        chartBranches: () => [],
        chartAreas: () => [],
        chartCapabilities: () => ({
            canManageBranch: false,
            canManageOrganizationNode: false,
        }),
    },
);

function orgChartNodeBindings(slotProps: {
    id: string;
    data: OrgChartNode['data'];
}) {
    return {
        nodeId: slotProps.id,
        fullName: slotProps.data.fullName,
        code: slotProps.data.alias,
        isActive: slotProps.data.isActive ?? true,
        unitTypeColor: slotProps.data.unitTypeColor ?? null,
        directChildren: slotProps.data.directChildren ?? [],
        parentUnit: slotProps.data.parentUnit ?? null,
    };
}

function mergeChartConfig(): OrgChartConfig {
    const base = chartConfig as OrgChartConfig;
    const serverNodes = props.orgChart?.nodes;
    if (
        serverNodes !== undefined &&
        serverNodes !== null &&
        serverNodes.length > 0
    ) {
        return {
            layout: base.layout,
            nodes: serverNodes,
        };
    }

    return structuredClone(base);
}

const chartState = ref<OrgChartConfig>(mergeChartConfig());
const nodes = ref<OrgChartNode[]>([]);
const edges = ref<Edge[]>([]);

function rebuildChart() {
    const rebuilt = buildOrganizationChart(chartState.value);
    nodes.value = rebuilt.nodes;
    edges.value = rebuilt.edges;
}
rebuildChart();

watch(
    () => props.orgChart,
    () => {
        chartState.value = mergeChartConfig();
        rebuildChart();
    },
    { deep: true },
);

const activeOrgChartNode = computed((): OrgChartNode | null => {
    const id = sheetBridge.activeNodeId.value;
    if (!id) {
        return null;
    }

    return nodes.value.find((n) => n.id === id) ?? null;
});

const sheetOpenSynced = computed({
    get(): boolean {
        return sheetBridge.sheetOpen.value;
    },
    set(value: boolean) {
        sheetBridge.sheetOpen.value = value;
        if (!value) {
            sheetBridge.activeNodeId.value = null;
        }
    },
});

watch(activeOrgChartNode, (node) => {
    if (
        sheetBridge.sheetOpen.value &&
        !node &&
        sheetBridge.activeNodeId.value
    ) {
        sheetBridge.sheetOpen.value = false;
        sheetBridge.activeNodeId.value = null;
    }
});

const orgChartEdgeTypes = shallowRef({
    orgChart: markRaw(OrgChartFlowEdge),
});
const page = usePage();

const chartScopeModel = ref<'branch' | 'all'>(props.chartScope ?? 'branch');
watch(
    () => props.chartScope,
    (next) => {
        chartScopeModel.value = next ?? 'branch';
    },
    { immediate: true },
);

const chartBranchIdModel = ref<string>(
    props.chartBranchId ? String(props.chartBranchId) : '',
);
watch(
    () => props.chartBranchId,
    (next) => {
        chartBranchIdModel.value = next ? String(next) : '';
    },
    { immediate: true },
);

const branchSelectValue = computed((): string => {
    return chartScopeModel.value === 'all' ? '' : chartBranchIdModel.value;
});

const chartUnitStatusModel = ref<'active' | 'all'>(
    props.chartUnitStatus ?? 'active',
);
watch(
    () => props.chartUnitStatus,
    (next) => {
        chartUnitStatusModel.value = next ?? 'active';
    },
    { immediate: true },
);

const workspaceBranchId = computed<number | null>(() => {
    const ctx = page.props.branchContext as
        | { id: number; code: string; name: string }
        | null
        | undefined;
    return ctx?.id ?? null;
});

const canReturnToWorkspaceBranch = computed(() => {
    const workspaceId = workspaceBranchId.value;
    return workspaceId !== null && props.chartBranchId !== workspaceId;
});

const organizationChartPartialOnly = [
    'orgChart',
    'chartScope',
    'chartUnitStatus',
    'chartBranchId',
    'chartBranches',
    'chartCapabilities',
] as const;

function branchListingLabel(branch: Branch): string {
    return branch.code ? `${branch.name} (${branch.code})` : branch.name;
}

const workspaceBranchLabel = computed(() => {
    const ctx = page.props.branchContext as
        | { id: number; code: string; name: string }
        | null
        | undefined;
    if (!ctx) {
        return '';
    }
    return ctx.code ? `${ctx.name} (${ctx.code})` : ctx.name;
});

function chartBranchLabelForId(branchId: number): string {
    const b = props.chartBranches.find((br) => br.id === branchId);
    return b ? branchListingLabel(b) : `Branch #${branchId}`;
}

function visitOrganizationChartFiltered(
    query:
        | Record<string, string | number>
        | Record<string, string | number | undefined>,
    description: string,
): void {
    const cleaned = Object.fromEntries(
        Object.entries(query).filter(
            (entry): entry is [string, string | number] =>
                entry[1] !== undefined,
        ),
    );

    const loadingToastId = appToast.loading('Updating chart…', {
        description,
    });
    router.get(organizationChart().url, cleaned, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        showProgress: false,
        only: [...organizationChartPartialOnly],
        onSuccess: () => {
            appToast.success('Chart updated.', { description });
        },
        onError: () => {
            appToast.error('Could not update chart.', {
                description,
            });
        },
        onFinish: () => {
            appToast.dismiss(loadingToastId);
        },
    });
}

function returnToWorkspaceBranch(): void {
    const workspaceId = workspaceBranchId.value;
    if (workspaceId === null) {
        return;
    }

    chartBranchIdModel.value = String(workspaceId);
    chartScopeModel.value = 'branch';
    visitOrganizationChartFiltered(
        {
            chart_scope: 'branch',
            chart_branch_id: workspaceId,
            chart_unit_status: chartUnitStatusModel.value,
        },
        workspaceBranchLabel.value || 'Workspace branch',
    );
}

function onChartBranchChange(event: Event): void {
    const targetEl = event.target as HTMLSelectElement | null;
    const value = targetEl?.value ?? '';
    chartBranchIdModel.value = value;
    chartScopeModel.value = 'branch';

    const description =
        value === ''
            ? 'Branch selection'
            : chartBranchLabelForId(Number(value));

    visitOrganizationChartFiltered(
        value === ''
            ? {
                  chart_scope: 'branch',
                  chart_unit_status: chartUnitStatusModel.value,
              }
            : {
                  chart_scope: 'branch',
                  chart_branch_id: Number(value),
                  chart_unit_status: chartUnitStatusModel.value,
              },
        description,
    );
}

function onChartScopeChange(event: Event): void {
    const targetEl = event.target as HTMLSelectElement | null;
    const value = targetEl?.value === 'all' ? 'all' : 'branch';
    chartScopeModel.value = value;

    const query: { chart_scope: 'branch' | 'all'; chart_branch_id?: number } = {
        chart_scope: value,
    };

    if (value === 'branch' && chartBranchIdModel.value !== '') {
        query.chart_branch_id = Number(chartBranchIdModel.value);
    }

    const description =
        value === 'all'
            ? 'Overall organization view'
            : chartBranchIdModel.value === ''
              ? 'Branch view'
              : chartBranchLabelForId(Number(chartBranchIdModel.value));

    visitOrganizationChartFiltered(
        { ...query, chart_unit_status: chartUnitStatusModel.value },
        description,
    );
}

function onChartUnitStatusChange(event: Event): void {
    const targetEl = event.target as HTMLSelectElement | null;
    const value = targetEl?.value === 'all' ? 'all' : 'active';
    chartUnitStatusModel.value = value;

    const query: {
        chart_scope: 'branch' | 'all';
        chart_unit_status: 'active' | 'all';
        chart_branch_id?: number;
    } = {
        chart_scope: chartScopeModel.value,
        chart_unit_status: value,
    };

    if (chartScopeModel.value === 'branch' && chartBranchIdModel.value !== '') {
        query.chart_branch_id = Number(chartBranchIdModel.value);
    }

    visitOrganizationChartFiltered(
        query,
        value === 'all' ? 'All units (status)' : 'Active units only',
    );
}

function onVueFlowInit(store: VueFlowStore): void {
    void store.fitView(chartFitViewParams);
}
</script>

<template>
    <div
        class="relative flex min-h-0 w-full flex-1 flex-col overflow-hidden rounded-none border border-border/70 bg-muted/30"
        data-organization-chart-flow-shell
    >
        <div class="absolute top-3 left-3 z-20">
            <div class="relative w-40">
                <select
                    id="org-chart-unit-status-view"
                    class="h-9 w-full appearance-none rounded-sm border border-border bg-background pr-8 pl-3 text-sm shadow-sm transition-colors outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    :value="chartUnitStatusModel"
                    @change="onChartUnitStatusChange"
                >
                    <option value="active">Active Units</option>
                    <option value="all">All Units</option>
                </select>
                <ChevronDown
                    class="pointer-events-none absolute top-1/2 right-2 size-4 -translate-y-1/2 text-muted-foreground"
                />
            </div>
        </div>
        <div class="absolute top-3 right-3 z-20 flex items-center gap-2">
            <div class="relative w-40">
                <select
                    id="org-chart-scope-view"
                    class="h-9 w-full appearance-none rounded-sm border border-border bg-background pr-8 pl-3 text-sm shadow-sm transition-colors outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    :value="chartScopeModel"
                    @change="onChartScopeChange"
                >
                    <option value="branch">Branch View</option>
                    <option value="all">Overall View</option>
                </select>
                <ChevronDown
                    class="pointer-events-none absolute top-1/2 right-2 size-4 -translate-y-1/2 text-muted-foreground"
                />
            </div>
            <div class="relative w-56">
                <select
                    id="org-chart-branch-view"
                    class="h-9 w-full appearance-none rounded-sm border border-border bg-background pr-8 pl-3 text-sm shadow-sm transition-colors outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    :value="branchSelectValue"
                    :disabled="chartScopeModel === 'all'"
                    @change="onChartBranchChange"
                >
                    <option v-if="chartScopeModel === 'all'" value="">
                        Overall view is read-only.
                    </option>
                    <option
                        v-else
                        v-for="branch in props.chartBranches"
                        :key="branch.id"
                        :value="String(branch.id)"
                    >
                        {{ branch.name
                        }}<template v-if="branch.code">
                            ({{ branch.code }})</template
                        >
                    </option>
                </select>
                <ChevronDown
                    class="pointer-events-none absolute top-1/2 right-2 size-4 -translate-y-1/2 text-muted-foreground"
                />
            </div>

            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-sm border border-border bg-background shadow-sm transition-colors hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canReturnToWorkspaceBranch"
                title="Return to workspace branch"
                @click="returnToWorkspaceBranch"
            >
                <Undo2 class="size-4" />
            </button>
        </div>
        <OrgChartNodeSheet
            v-model:open="sheetOpenSynced"
            :active-node="activeOrgChartNode"
            :chart-capabilities="props.chartCapabilities"
            :chart-branch-id="props.chartBranchId ?? null"
        />
        <VueFlow
            class="h-full w-full flex-1"
            v-model:nodes="nodes"
            :edges="edges"
            :edge-types="orgChartEdgeTypes"
            :nodes-draggable="false"
            :nodes-connectable="false"
            :elements-selectable="false"
            :connect-on-click="false"
            :min-zoom="0.2"
            :max-zoom="2"
            @init="onVueFlowInit"
        >
            <Background
                variant="dots"
                :gap="24"
                patternColor="#81818a"
                :lineWidth="1"
            />

            <Controls
                class="org-chart-controls"
                position="bottom-right"
                :show-zoom="true"
                :show-fit-view="true"
                :show-interactive="false"
                :fit-view-params="chartFitViewParams"
            />

            <template #node-org="slotProps">
                <OrgChartFlowNode
                    v-bind="orgChartNodeBindings(slotProps)"
                    :visual="orgVisual"
                    nodeBucket="org"
                    :chart-branch-id="props.chartBranchId ?? null"
                    :chart-areas="props.chartAreas"
                    :chart-capabilities="props.chartCapabilities"
                />
            </template>

            <template #node-branch="slotProps">
                <OrgChartFlowNode
                    v-bind="orgChartNodeBindings(slotProps)"
                    :visual="branchVisual"
                    nodeBucket="branch"
                    :chart-branch-id="props.chartBranchId ?? null"
                    :chart-areas="props.chartAreas"
                    :chart-capabilities="props.chartCapabilities"
                />
            </template>

            <template #node-department="slotProps">
                <OrgChartFlowNode
                    v-bind="orgChartNodeBindings(slotProps)"
                    :visual="departmentVisual"
                    nodeBucket="department"
                    :chart-branch-id="props.chartBranchId ?? null"
                    :chart-areas="props.chartAreas"
                    :chart-capabilities="props.chartCapabilities"
                />
            </template>

            <template #node-dept-hr="slotProps">
                <OrgChartFlowNode
                    v-bind="orgChartNodeBindings(slotProps)"
                    :visual="departmentVisual"
                    nodeBucket="department"
                    :chart-branch-id="props.chartBranchId ?? null"
                    :chart-areas="props.chartAreas"
                    :chart-capabilities="props.chartCapabilities"
                />
            </template>

            <template #node-dept-it="slotProps">
                <OrgChartFlowNode
                    v-bind="orgChartNodeBindings(slotProps)"
                    :visual="departmentVisual"
                    nodeBucket="department"
                    :chart-branch-id="props.chartBranchId ?? null"
                    :chart-areas="props.chartAreas"
                    :chart-capabilities="props.chartCapabilities"
                />
            </template>

            <template #node-dept-fin="slotProps">
                <OrgChartFlowNode
                    v-bind="orgChartNodeBindings(slotProps)"
                    :visual="departmentVisual"
                    nodeBucket="department"
                    :chart-branch-id="props.chartBranchId ?? null"
                    :chart-areas="props.chartAreas"
                    :chart-capabilities="props.chartCapabilities"
                />
            </template>

            <template #node-section="slotProps">
                <OrgChartFlowNode
                    v-bind="orgChartNodeBindings(slotProps)"
                    :visual="sectionVisual"
                    nodeBucket="section"
                    :chart-branch-id="props.chartBranchId ?? null"
                    :chart-areas="props.chartAreas"
                    :chart-capabilities="props.chartCapabilities"
                />
            </template>

            <template #node-node-generic="slotProps">
                <OrgChartFlowNode
                    v-bind="orgChartNodeBindings(slotProps)"
                    :visual="genericVisual"
                    :chart-branch-id="props.chartBranchId ?? null"
                    :chart-areas="props.chartAreas"
                    :chart-capabilities="props.chartCapabilities"
                />
            </template>
        </VueFlow>
    </div>
</template>

<style scoped>
:deep(.org-chart-controls.vue-flow__controls) {
    border-radius: 0.375rem;
    overflow: hidden;
    border: 1px solid hsl(var(--border));
    background-color: hsl(var(--background));
    box-shadow: var(--shadow-sm);
}

:deep(.org-chart-controls .vue-flow__controls-button) {
    border-radius: 0;
    width: 1.75rem;
    height: 1.75rem;
    border: 0;
    border-bottom: 1px solid hsl(var(--border));
    background-color: transparent;
    transition: background-color 0.2s ease;
}

:deep(.org-chart-controls .vue-flow__controls-button svg) {
    width: 0.875rem;
    height: 0.875rem;
}

:deep(.org-chart-controls .vue-flow__controls-button:last-child) {
    border-bottom: 0;
}

:deep(.org-chart-controls .vue-flow__controls-button:hover) {
    background-color: hsl(var(--accent));
}
</style>
