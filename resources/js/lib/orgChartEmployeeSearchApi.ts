import organizationChart from '@/routes/organization-chart';

const MIN_QUERY_LENGTH = 2;

/** Payload row from `SearchOrganizationChartEmployeesController`. */
export type OrgChartAssignableEmployeeHit = {
    id: number;
    employee_id: number;
    employee_number: string | null;
    full_name: string;
    active_position_title: string | null;
    avatar_url: string | null;
    positions: Array<{ id: number; title: string }>;
};

export async function fetchOrgChartAssignableEmployees(args: {
    chartBranchId: number;
    nodeId: string;
    query: string;
    signal?: AbortSignal;
    limit?: number;
}): Promise<OrgChartAssignableEmployeeHit[]> {
    const q = args.query.trim();
    if (q.length < MIN_QUERY_LENGTH) {
        return [];
    }

    const queryParams: Record<string, string | number> = {
        chart_branch_id: args.chartBranchId,
        q,
        limit: args.limit ?? 20,
    };

    if (args.nodeId.startsWith('unit-')) {
        queryParams.node_id = args.nodeId;
    }

    const url = organizationChart.employees.search.url({
        query: queryParams,
    });

    const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        signal: args.signal,
    });

    if (!response.ok) {
        throw new Error('Unable to search employees.');
    }

    const payload = (await response.json()) as {
        data?: OrgChartAssignableEmployeeHit[];
    };

    return Array.isArray(payload.data) ? payload.data : [];
}
