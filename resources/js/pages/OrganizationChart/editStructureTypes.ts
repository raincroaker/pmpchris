export type EditStructureRow = {
    id: number;
    name: string;
    color: string | null;
    can_be_root: boolean;
    description: string | null;
    is_active: boolean;
    /** Units under the default organization (scoped). */
    units_count: number;
    /** Organizational units referencing this type across all organizations (for delete safety). */
    units_total_count: number;
    units: Array<{
        id: number;
        code: string;
        name: string;
        parent_name: string | null;
        parent_code: string | null;
        is_active: boolean;
    }>;
    allowed_parent_type_ids: number[];
    allowed_parent_type_names: string[];
};

export type EditStructurePaginator = {
    data: EditStructureRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type EditStructureFilters = {
    search: string;
    sort: 'name' | 'created_at' | 'id';
    direction: 'asc' | 'desc';
    per_page: number;
    root_unit_filter: number | null;
};

export type EditStructureAreaRow = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
};

export type EditStructureRootUnitFilterOption = {
    value: string;
    label: string;
    code?: string | null;
};
