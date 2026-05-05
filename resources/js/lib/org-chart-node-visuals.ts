export type NodeVisualStyle = {
    unitLabel: string;
    cardClass: string;
    unitLabelClass: string;
    nameClass: string;
    codeClass: string;
};

const nodeVisualStyles: Record<
    'org' | 'branch' | 'department' | 'section' | 'generic',
    NodeVisualStyle
> = {
    org: {
        unitLabel: 'Organization',
        cardClass:
            'border-blue-500 bg-slate-50 transition-transform duration-200 will-change-transform hover:scale-[1.03] group-hover/node:scale-[1.03]',
        unitLabelClass: 'text-muted-foreground',
        nameClass: 'text-foreground',
        codeClass: 'text-muted-foreground',
    },
    branch: {
        unitLabel: 'Branch',
        cardClass:
            'border-indigo-500 bg-slate-50 transition-transform duration-200 will-change-transform hover:scale-[1.03] group-hover/node:scale-[1.03]',
        unitLabelClass: 'text-muted-foreground',
        nameClass: 'text-foreground',
        codeClass: 'text-muted-foreground',
    },
    department: {
        unitLabel: 'Department',
        cardClass:
            'border-emerald-500 bg-slate-50 transition-transform duration-200 will-change-transform hover:scale-[1.03] group-hover/node:scale-[1.03]',
        unitLabelClass: 'text-muted-foreground',
        nameClass: 'text-foreground',
        codeClass: 'text-muted-foreground',
    },
    section: {
        unitLabel: 'Section',
        cardClass:
            'border-amber-600/90 bg-slate-50 transition-transform duration-200 will-change-transform hover:scale-[1.03] group-hover/node:scale-[1.03]',
        unitLabelClass: 'text-muted-foreground',
        nameClass: 'text-foreground',
        codeClass: 'text-muted-foreground',
    },
    generic: {
        unitLabel: 'Unit',
        cardClass:
            'border-slate-500 bg-slate-50 transition-transform duration-200 will-change-transform hover:scale-[1.03] group-hover/node:scale-[1.03]',
        unitLabelClass: 'text-muted-foreground',
        nameClass: 'text-foreground',
        codeClass: 'text-muted-foreground',
    },
};

export function resolveVisualBucket(
    nodeType: string,
): keyof typeof nodeVisualStyles {
    if (nodeType === 'org') return 'org';
    if (nodeType === 'branch') return 'branch';
    if (nodeType.startsWith('dept-')) return 'department';
    if (nodeType === 'section') return 'section';
    return 'generic';
}

export function nodeVisual(nodeType: string): NodeVisualStyle {
    return nodeVisualStyles[resolveVisualBucket(nodeType)];
}

export const orgVisual = nodeVisual('org');
export const branchVisual = nodeVisual('branch');
export const departmentVisual = nodeVisual('dept-hr');
export const sectionVisual = nodeVisual('section');
export const genericVisual = nodeVisual('node-generic');
