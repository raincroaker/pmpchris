import type { Edge } from '@vue-flow/core';

export type OrgChartDirectChild = {
    id: string;
    fullName: string;
    alias: string;
};

export type OrgChartEmployeeRow = {
    assignment_id?: number;
    employee_id: number;
    full_name: string;
    position_title: string | null;
    position_options?: Array<{ id: number; title: string }>;
    avatar_url?: string | null;
    is_primary?: boolean;
    is_head?: boolean;
};

export type OrgChartNodeData = {
    fullName: string;
    alias: string;
    isActive?: boolean;
    /** Optional unit-type accent for org chart node visuals. */
    unitTypeColor?: string | null;
    directChildren: OrgChartDirectChild[];
    parentUnit: OrgChartDirectChild | null;
    employees?: OrgChartEmployeeRow[];
};

export type OrgChartNode = {
    id: string;
    type: string;
    position: { x: number; y: number };
    data: OrgChartNodeData;
};

type OrgChartLayout = {
    centerX: number;
    originY: number;
    verticalGap: number;
    horizontalSiblingGap: number;
    cardWidth: number;
    handleMapping: {
        source: string;
        target: string;
    };
};

export type OrgChartNodeSpec = {
    id: string;
    type: string;
    parentId: string | null;
    siblingIndex?: number;
    data: {
        fullName: string;
        alias: string;
        isActive?: boolean;
        employees?: OrgChartEmployeeRow[];
    };
};

export type OrgChartConfig = {
    layout: OrgChartLayout;
    nodes: OrgChartNodeSpec[];
};

function assertOrgChartConfig(value: unknown): asserts value is OrgChartConfig {
    if (typeof value !== 'object' || value === null) {
        throw new Error('Invalid org chart config: expected an object');
    }

    const v = value as Partial<OrgChartConfig>;
    if (!v.layout || !Array.isArray(v.nodes)) {
        throw new Error('Invalid org chart config: missing layout or nodes');
    }

    const l = v.layout as Partial<OrgChartLayout>;
    if (
        typeof l.centerX !== 'number' ||
        typeof l.originY !== 'number' ||
        typeof l.verticalGap !== 'number' ||
        typeof l.horizontalSiblingGap !== 'number' ||
        typeof l.cardWidth !== 'number' ||
        !l.handleMapping ||
        typeof l.handleMapping.source !== 'string' ||
        typeof l.handleMapping.target !== 'string'
    ) {
        throw new Error(
            'Invalid org chart config: layout requires centerX, originY, verticalGap, horizontalSiblingGap, cardWidth, handleMapping',
        );
    }
}

function roundToNearestPx(n: number): number {
    return Math.round(n);
}

function sortedChildren(
    parentId: string,
    childrenByParent: Map<string, OrgChartNodeSpec[]>,
): OrgChartNodeSpec[] {
    const raw = childrenByParent.get(parentId) ?? [];

    return [...raw].sort(
        (a, b) => (a.siblingIndex ?? 0) - (b.siblingIndex ?? 0),
    );
}

/**
 * Bottom-up: width reserved for each node's subtree (for horizontal packing).
 */
function computeSubtreeWidth(
    specId: string,
    childrenByParent: Map<string, OrgChartNodeSpec[]>,
    subtreeWidths: Map<string, number>,
    cardWidth: number,
    horizontalSiblingGap: number,
): number {
    const cached = subtreeWidths.get(specId);
    if (cached !== undefined) {
        return cached;
    }

    const children = sortedChildren(specId, childrenByParent);
    if (children.length === 0) {
        subtreeWidths.set(specId, cardWidth);

        return cardWidth;
    }

    let sum = 0;
    for (const child of children) {
        sum += computeSubtreeWidth(
            child.id,
            childrenByParent,
            subtreeWidths,
            cardWidth,
            horizontalSiblingGap,
        );
    }
    sum += (children.length - 1) * horizontalSiblingGap;
    const width = Math.max(cardWidth, sum);
    subtreeWidths.set(specId, width);

    return width;
}

function computeDepthById(
    roots: OrgChartNodeSpec[],
    childrenByParent: Map<string, OrgChartNodeSpec[]>,
): Map<string, number> {
    const depthById = new Map<string, number>();
    const queue = roots.map((r) => ({ id: r.id, depth: 0 }));

    for (const item of queue) {
        if (depthById.has(item.id)) {
            continue;
        }
        depthById.set(item.id, item.depth);
        for (const child of sortedChildren(item.id, childrenByParent)) {
            queue.push({ id: child.id, depth: item.depth + 1 });
        }
    }

    return depthById;
}

function assignPositions(
    specId: string,
    parentCenterX: number,
    childrenByParent: Map<string, OrgChartNodeSpec[]>,
    subtreeWidths: Map<string, number>,
    positioned: Map<string, OrgChartNode>,
    specById: Map<string, OrgChartNodeSpec>,
    layout: OrgChartLayout,
    depthById: Map<string, number>,
): void {
    const spec = specById.get(specId);
    if (!spec) {
        return;
    }

    const depth = depthById.get(specId) ?? 0;
    const y = roundToNearestPx(layout.originY + depth * layout.verticalGap);
    const half = layout.cardWidth / 2;
    positioned.set(specId, {
        id: spec.id,
        type: spec.type,
        position: {
            x: roundToNearestPx(parentCenterX - half),
            y,
        },
        data: {
            fullName: spec.data.fullName,
            alias: spec.data.alias,
            directChildren: [],
            parentUnit: null,
        },
    });

    const children = sortedChildren(specId, childrenByParent);
    if (children.length === 0) {
        return;
    }

    const naturals = children.map(
        (c) => subtreeWidths.get(c.id) ?? layout.cardWidth,
    );
    const slotWidth = Math.max(layout.cardWidth, ...naturals);
    const widths = children.map(() => slotWidth);
    const total =
        widths.reduce((a, w) => a + w, 0) +
        (children.length - 1) * layout.horizontalSiblingGap;
    let cursor = parentCenterX - total / 2;

    for (let i = 0; i < children.length; i += 1) {
        const w = widths[i];
        const child = children[i];
        const childCenterX = cursor + w / 2;
        assignPositions(
            child.id,
            childCenterX,
            childrenByParent,
            subtreeWidths,
            positioned,
            specById,
            layout,
            depthById,
        );
        cursor += w + layout.horizontalSiblingGap;
    }
}

export function buildOrganizationChart(rawConfig: unknown): {
    nodes: OrgChartNode[];
    edges: Edge[];
} {
    assertOrgChartConfig(rawConfig);

    const config = rawConfig;
    const layout = config.layout;
    const sourceHandle = layout.handleMapping.source;
    const targetHandle = layout.handleMapping.target;

    const childrenByParent = new Map<string, OrgChartNodeSpec[]>();
    for (const node of config.nodes) {
        if (!node.parentId) {
            continue;
        }
        const list = childrenByParent.get(node.parentId) ?? [];
        list.push(node);
        childrenByParent.set(node.parentId, list);
    }

    const specById = new Map(config.nodes.map((n) => [n.id, n]));
    const positioned = new Map<string, OrgChartNode>();

    const roots = config.nodes.filter((n) => n.parentId === null);
    const sortedRoots = [...roots].sort(
        (a, b) => (a.siblingIndex ?? 0) - (b.siblingIndex ?? 0),
    );

    const subtreeWidths = new Map<string, number>();
    for (const node of config.nodes) {
        computeSubtreeWidth(
            node.id,
            childrenByParent,
            subtreeWidths,
            layout.cardWidth,
            layout.horizontalSiblingGap,
        );
    }

    const depthById = computeDepthById(sortedRoots, childrenByParent);

    if (sortedRoots.length === 0) {
        // empty chart
    } else if (sortedRoots.length === 1) {
        assignPositions(
            sortedRoots[0].id,
            layout.centerX,
            childrenByParent,
            subtreeWidths,
            positioned,
            specById,
            layout,
            depthById,
        );
    } else {
        const rootNaturals = sortedRoots.map(
            (r) => subtreeWidths.get(r.id) ?? layout.cardWidth,
        );
        const rootSlot = Math.max(layout.cardWidth, ...rootNaturals);
        const rootWidths = sortedRoots.map(() => rootSlot);
        const total =
            rootWidths.reduce((a, w) => a + w, 0) +
            (sortedRoots.length - 1) * layout.horizontalSiblingGap;
        let cursor = layout.centerX - total / 2;
        for (let i = 0; i < sortedRoots.length; i += 1) {
            const w = rootWidths[i];
            const root = sortedRoots[i];
            const rootCenterX = cursor + w / 2;
            assignPositions(
                root.id,
                rootCenterX,
                childrenByParent,
                subtreeWidths,
                positioned,
                specById,
                layout,
                depthById,
            );
            cursor += w + layout.horizontalSiblingGap;
        }
    }

    const nodes: OrgChartNode[] = config.nodes
        .map((spec) => {
            const node = positioned.get(spec.id);
            if (!node) {
                return null;
            }

            const childSpecs = sortedChildren(spec.id, childrenByParent);
            const directChildren: OrgChartDirectChild[] = childSpecs.map(
                (c) => {
                    const s = specById.get(c.id);
                    return {
                        id: c.id,
                        fullName: s?.data.fullName ?? '',
                        alias: s?.data.alias ?? '',
                    };
                },
            );

            const parentUnit: OrgChartDirectChild | null =
                spec.parentId === null
                    ? null
                    : (() => {
                          const parentSpec = specById.get(spec.parentId);
                          if (!parentSpec) {
                              return null;
                          }

                          return {
                              id: parentSpec.id,
                              fullName: parentSpec.data.fullName,
                              alias: parentSpec.data.alias,
                          };
                      })();

            return {
                ...node,
                data: {
                    ...spec.data,
                    directChildren,
                    parentUnit,
                },
            };
        })
        .filter((node): node is OrgChartNode => node !== null);

    const edges: Edge[] = [];
    for (const nodeSpec of config.nodes) {
        if (!nodeSpec.parentId) {
            continue;
        }
        const parentId = nodeSpec.parentId;

        edges.push({
            id: `e-${parentId}-${nodeSpec.id}`,
            source: parentId,
            target: nodeSpec.id,
            sourceHandle,
            targetHandle,
            type: 'orgChart',
            animated: true,
        });
    }

    const positionedIds = new Set(nodes.map((node) => node.id));
    const safeEdges = edges.filter(
        (edge) =>
            positionedIds.has(String(edge.source)) &&
            positionedIds.has(String(edge.target)),
    );

    return { nodes, edges: safeEdges };
}
