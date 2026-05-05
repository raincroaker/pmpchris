export type EmployeeIndexPosition = {
    id: number;
    code: string;
    title: string;
    is_primary: boolean;
};

export type EmployeeIndexUnit = {
    name: string;
    unit_type: string;
    code: string | null;
};

export type EmployeeIndexContact = {
    phone: string | null;
    email: string | null;
};

export type EmployeeRow = {
    id: number;
    display_name: string;
    id_number: string;
    avatar_url: string | null;
    is_org_wide: boolean;
    positions: EmployeeIndexPosition[];
    units: EmployeeIndexUnit[];
    contact: EmployeeIndexContact;
};

export type EmployeesPaginator = {
    data: EmployeeRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type EmployeeFilters = {
    search: string;
    sort: 'last_name' | 'first_name' | 'id_number' | 'id';
    direction: 'asc' | 'desc';
    per_page: number;
    position_id: number | null;
    unit_id: number | 'unassigned' | null;
    org_scope: 'org_wide' | 'branch_scoped' | null;
};

export type EmployeePositionFilterOption = {
    id: number;
    code: string;
    title: string;
};

export type EmployeeUnitFilterOption = {
    id: number;
    unit_type: string;
    code: string;
    name: string;
};
