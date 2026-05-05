import type { InjectionKey, Ref } from 'vue';
import { ref } from 'vue';

export type OrgChartNodeSheetActionKind =
    | 'editUnit'
    | 'addUnit'
    | 'addEmployee';

export type OrgChartPendingNodeAction = {
    kind: OrgChartNodeSheetActionKind;
    nodeId: string;
    seq: number;
};

export type OrgChartSheetBridge = {
    sheetOpen: Ref<boolean>;
    activeNodeId: Ref<string | null>;
    openNodeSheet: (nodeId: string) => void;
    pendingNodeAction: Ref<OrgChartPendingNodeAction | null>;
    requestNodeAction: (
        nodeId: string,
        kind: OrgChartNodeSheetActionKind,
    ) => void;
    consumePendingNodeAction: () => void;
};

export const orgChartSheetBridgeKey: InjectionKey<OrgChartSheetBridge> = Symbol(
    'orgChartSheetBridge',
);

export function createOrgChartSheetBridge(): OrgChartSheetBridge {
    const sheetOpen = ref(false);
    const activeNodeId = ref<string | null>(null);
    const pendingNodeAction = ref<OrgChartPendingNodeAction | null>(null);
    let actionSeq = 0;

    function openNodeSheet(nodeId: string): void {
        activeNodeId.value = nodeId;
        sheetOpen.value = true;
    }

    function requestNodeAction(
        nodeId: string,
        kind: OrgChartNodeSheetActionKind,
    ): void {
        actionSeq += 1;
        pendingNodeAction.value = { kind, nodeId, seq: actionSeq };
    }

    function consumePendingNodeAction(): void {
        pendingNodeAction.value = null;
    }

    return {
        sheetOpen,
        activeNodeId,
        openNodeSheet,
        pendingNodeAction,
        requestNodeAction,
        consumePendingNodeAction,
    };
}
