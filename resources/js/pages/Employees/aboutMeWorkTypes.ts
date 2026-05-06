/**
 * Inertia prop `aboutMeWork` from {@see EmployeesAboutMeController}.
 * Editor rows use ISO `YYYY-MM-DD` dates; lists are scoped to current active employment only.
 */

export type AboutMeWorkAffiliationRootOption = {
    id: number;
    code: string;
    name: string;
    area_name: string | null;
    group_label: string;
};

export type AboutMeWorkPositionCatalogRow = {
    id: number;
    code: string;
    title: string;
};

export type AboutMeWorkPositionDraftSource = {
    id: number;
    position_id: number;
    start_date: string;
    end_date: string | null;
    is_primary: boolean;
};

export type AboutMeWorkAffiliationDraftSource = {
    id: number;
    root_unit_id: number | null;
    start_date: string;
    end_date: string | null;
    is_primary: boolean;
};

export type AboutMeWorkPayload = {
    employment_id: number;
    employee_id: number;
    hire_date: string;
    hire_adjustment_max_date: string | null;
    positions: AboutMeWorkPositionDraftSource[];
    affiliations: AboutMeWorkAffiliationDraftSource[];
    positions_catalog: AboutMeWorkPositionCatalogRow[];
    affiliation_roots: AboutMeWorkAffiliationRootOption[];
    affiliation_organization: { id: number; code: string; name: string } | null;
    allow_org_wide_affiliation: boolean;
};
