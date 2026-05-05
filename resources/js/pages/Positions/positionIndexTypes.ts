export type PositionRow = {
    id: number;
    code: string;
    title: string;
    description: string | null;
    is_active: boolean;
};

export type PositionsPaginator = {
    data: PositionRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type PositionFilters = {
    search: string;
    status: 'active' | 'inactive' | 'all';
    sort: 'code' | 'title' | 'created_at' | 'id';
    direction: 'asc' | 'desc';
    per_page: number;
};

export type PositionEmployeeListItem = {
    id: number;
    display_name: string;
    id_number: string;
};
