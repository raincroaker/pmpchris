import type { HrisDemoDirectoryFields } from '@/lib/hrisDemoDirectoryFields';
import { hrisAugmentTeamHrRowWithDemoDirectory } from '@/lib/hrisDemoDirectoryFields';
import type { TeamOvertimeRow } from '@/pages/Overtime/teamOvertimeTypes';

/** Same persona as My leaves mock for consistency. */
export const MY_OVERTIME_MOCK_PERSONA = {
    display_name: 'Jordan Cruz',
    id_number: 'PMPC-10177',
    avatar_url: null as string | null,
} as const;

type TeamOvertimeRowBare = Omit<TeamOvertimeRow, keyof HrisDemoDirectoryFields>;

/** Personal overtime history (UI mock). */
export function seedMyOvertimeRows(): TeamOvertimeRow[] {
    const e = MY_OVERTIME_MOCK_PERSONA;

    const base: TeamOvertimeRowBare[] = [
        {
            id: 6201,
            employee: { ...e },
            unit_filter_value: 'u-tagum',
            unit_name: 'Tagum Branch',
            unit_code: 'TAG',
            ot_date: '2026-05-04',
            hours: 6,
            context: 'rest_day',
            policy_code: 'OT-RD',
            rate_multiplier: 1.3,
            status: 'approved',
            submitted_at: '2026-05-04',
            decided_at: '2026-05-05',
            approver_employee_id: null,
            approver_id_number: 'PMPC-HRMO',
            approver_name: 'HR (mock)',
            reason: 'Inventory weekend.',
        },
        {
            id: 6202,
            employee: { ...e },
            unit_filter_value: 'u-tagum',
            unit_name: 'Tagum Branch',
            unit_code: 'TAG',
            ot_date: '2026-05-14',
            hours: 3.5,
            context: 'ordinary_weekday',
            policy_code: 'OT-WD',
            rate_multiplier: 1.25,
            status: 'approved',
            submitted_at: '2026-05-14',
            decided_at: '2026-05-15',
            approver_employee_id: null,
            approver_id_number: 'PMPC-20001',
            approver_name: 'R. Santos',
            reason: 'Release cutover.',
        },
        {
            id: 6203,
            employee: { ...e },
            unit_filter_value: 'u-tagum',
            unit_name: 'Tagum Branch',
            unit_code: 'TAG',
            ot_date: '2026-05-22',
            hours: 2,
            context: 'ordinary_weekday',
            policy_code: 'OT-WD',
            rate_multiplier: 1.25,
            status: 'approved',
            submitted_at: '2026-05-22',
            decided_at: '2026-05-23',
            approver_employee_id: null,
            approver_id_number: 'PMPC-20001',
            approver_name: 'R. Santos',
            reason: null,
        },
        {
            id: 6204,
            employee: { ...e },
            unit_filter_value: 'u-tagum',
            unit_name: 'Tagum Branch',
            unit_code: 'TAG',
            ot_date: '2026-06-01',
            hours: 4,
            context: 'special_holiday',
            policy_code: 'OT-SH',
            rate_multiplier: 1.3,
            status: 'approved',
            submitted_at: '2026-05-28',
            decided_at: '2026-05-29',
            approver_employee_id: null,
            approver_id_number: 'PMPC-HRCC',
            approver_name: 'HR Committee',
            reason: 'Holiday coverage.',
        },
        {
            id: 6205,
            employee: { ...e },
            unit_filter_value: 'u-tagum',
            unit_name: 'Tagum Branch',
            unit_code: 'TAG',
            ot_date: '2026-05-30',
            hours: 5,
            context: 'ordinary_weekday',
            policy_code: 'OT-WD',
            rate_multiplier: 1.25,
            status: 'rejected',
            submitted_at: '2026-05-30',
            decided_at: '2026-06-01',
            approver_employee_id: null,
            approver_id_number: 'PMPC-20002',
            approver_name: 'D. Ramos',
            reason: 'Late filing (mock policy).',
        },
    ];

    return base.map(hrisAugmentTeamHrRowWithDemoDirectory);
}
