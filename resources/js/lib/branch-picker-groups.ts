export const UNASSIGNED_AREA_LABEL = 'Branches';

export type BranchWithAreaName = {
    id: number;
    code?: string;
    name: string;
    /** Geographic area when linked; null for e.g. Head Office. */
    area_name?: string | null;
    /** Picker section heading: area name, or unit type name, or falls back with area_name. */
    group_label?: string | null;
};

export type BranchPickerGroup<
    T extends BranchWithAreaName = BranchWithAreaName,
> = {
    label: string;
    branches: T[];
};

/**
 * Groups branches by `group_label` (server), then `area_name`, then Unassigned.
 * Group order follows first appearance in the input array; Unassigned is always last.
 */
export function branchPickerGroups<T extends BranchWithAreaName>(
    branches: T[],
): BranchPickerGroup<T>[] {
    const map = new Map<string, T[]>();
    const insertionOrder: string[] = [];

    for (const branch of branches) {
        const primary =
            branch.group_label != null && branch.group_label !== ''
                ? branch.group_label
                : branch.area_name != null && branch.area_name !== ''
                  ? branch.area_name
                  : '';
        const label = primary !== '' ? primary : UNASSIGNED_AREA_LABEL;
        const list = map.get(label);
        if (list === undefined) {
            map.set(label, [branch]);
            insertionOrder.push(label);
        } else {
            list.push(branch);
        }
    }

    const namedFirst = insertionOrder.filter(
        (l) => l !== UNASSIGNED_AREA_LABEL,
    );
    const hasUnassigned = map.has(UNASSIGNED_AREA_LABEL);
    const labels = hasUnassigned
        ? [...namedFirst, UNASSIGNED_AREA_LABEL]
        : namedFirst;

    return labels.map((label) => ({
        label,
        branches: map.get(label) ?? [],
    }));
}
