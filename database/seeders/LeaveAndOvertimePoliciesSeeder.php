<?php

namespace Database\Seeders;

use App\Enums\LeavePolicyAccrualCadence;
use App\Enums\LeavePolicyUnit;
use App\Enums\OvertimePolicyContext;
use App\Models\LeavePolicy;
use App\Models\Organization;
use App\Models\OvertimePolicy;
use Illuminate\Database\Seeder;

class LeaveAndOvertimePoliciesSeeder extends Seeder
{
    /**
     * Baseline leave and overtime policies for the demo cooperative (matches former UI mocks).
     */
    public function run(): void
    {
        $organization = Organization::query()->where('code', 'PMPC')->first();
        if ($organization === null) {
            return;
        }

        $leaveRows = [
            [
                'code' => 'VL',
                'name' => 'Vacation leave',
                'unit' => LeavePolicyUnit::Days,
                'annual_entitlement' => 15,
                'use_accrual' => true,
                'accrual_cadence' => LeavePolicyAccrualCadence::Monthly,
                'accrual_per_period' => 1.25,
                'max_balance' => 45,
                'carryover_allowed' => true,
                'carryover_cap' => 30,
                'paid' => true,
                'requires_approval' => true,
                'applies_after_months' => null,
                'is_active' => true,
                'notes' => 'Accrues monthly; excess above cap forfeited at year-end unless carried over within cap.',
            ],
            [
                'code' => 'SL',
                'name' => 'Sick leave',
                'unit' => LeavePolicyUnit::Days,
                'annual_entitlement' => 12,
                'use_accrual' => false,
                'accrual_cadence' => null,
                'accrual_per_period' => null,
                'max_balance' => 90,
                'carryover_allowed' => true,
                'carryover_cap' => null,
                'paid' => true,
                'requires_approval' => false,
                'applies_after_months' => null,
                'is_active' => true,
                'notes' => 'Granted annually on hire anniversary; medical certificate may be required per branch rules.',
            ],
            [
                'code' => 'PL',
                'name' => 'Parental leave',
                'unit' => LeavePolicyUnit::Days,
                'annual_entitlement' => 105,
                'use_accrual' => false,
                'accrual_cadence' => null,
                'accrual_per_period' => null,
                'max_balance' => null,
                'carryover_allowed' => false,
                'carryover_cap' => null,
                'paid' => true,
                'requires_approval' => true,
                'applies_after_months' => null,
                'is_active' => true,
                'notes' => 'Statutory parental leave window; coordinate with HR before filing.',
            ],
            [
                'code' => 'LWOP',
                'name' => 'Leave without pay',
                'unit' => LeavePolicyUnit::Days,
                'annual_entitlement' => 0,
                'use_accrual' => false,
                'accrual_cadence' => null,
                'accrual_per_period' => null,
                'max_balance' => null,
                'carryover_allowed' => false,
                'carryover_cap' => null,
                'paid' => false,
                'requires_approval' => true,
                'applies_after_months' => 6,
                'is_active' => true,
                'notes' => 'Discretionary unpaid absence; manager approval required.',
            ],
            [
                'code' => 'BL',
                'name' => 'Bereavement leave',
                'unit' => LeavePolicyUnit::Days,
                'annual_entitlement' => 5,
                'use_accrual' => false,
                'accrual_cadence' => null,
                'accrual_per_period' => null,
                'max_balance' => null,
                'carryover_allowed' => false,
                'carryover_cap' => null,
                'paid' => true,
                'requires_approval' => true,
                'applies_after_months' => null,
                'is_active' => false,
                'notes' => 'Inactive template — pending policy review.',
            ],
        ];

        foreach ($leaveRows as $row) {
            LeavePolicy::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => $row['code'],
                ],
                array_merge($row, [
                    'organization_id' => $organization->id,
                ]),
            );
        }

        $overtimeRows = [
            [
                'code' => 'OT-WD',
                'name' => 'Weekday overtime',
                'context' => OvertimePolicyContext::OrdinaryWeekday,
                'rate_multiplier' => 1.25,
                'daily_threshold_hours' => 8,
                'daily_cap_hours' => 4,
                'weekly_cap_hours' => 24,
                'requires_approval' => true,
                'minimum_lead_time_hours' => 24,
                'is_active' => true,
                'notes' => 'Applies to hours worked beyond the standard 8h day on regular working days.',
            ],
            [
                'code' => 'OT-RD',
                'name' => 'Rest day overtime',
                'context' => OvertimePolicyContext::RestDay,
                'rate_multiplier' => 1.3,
                'daily_threshold_hours' => 0,
                'daily_cap_hours' => null,
                'weekly_cap_hours' => null,
                'requires_approval' => true,
                'minimum_lead_time_hours' => 48,
                'is_active' => true,
                'notes' => 'All hours on a scheduled rest day are premium; threshold at 0 for full-day coverage.',
            ],
            [
                'code' => 'OT-RH',
                'name' => 'Regular holiday work',
                'context' => OvertimePolicyContext::RegularHoliday,
                'rate_multiplier' => 2,
                'daily_threshold_hours' => 0,
                'daily_cap_hours' => null,
                'weekly_cap_hours' => null,
                'requires_approval' => true,
                'minimum_lead_time_hours' => null,
                'is_active' => true,
                'notes' => 'Double rate for work on regular national holidays (illustrative).',
            ],
            [
                'code' => 'OT-SH',
                'name' => 'Special holiday work',
                'context' => OvertimePolicyContext::SpecialHoliday,
                'rate_multiplier' => 1.3,
                'daily_threshold_hours' => 0,
                'daily_cap_hours' => null,
                'weekly_cap_hours' => null,
                'requires_approval' => true,
                'minimum_lead_time_hours' => 24,
                'is_active' => true,
                'notes' => 'Special non-working day premium; verify against current labor code.',
            ],
            [
                'code' => 'OT-LEG',
                'name' => 'Legacy night differential (inactive)',
                'context' => OvertimePolicyContext::OrdinaryWeekday,
                'rate_multiplier' => 1.1,
                'daily_threshold_hours' => 8,
                'daily_cap_hours' => 8,
                'weekly_cap_hours' => 16,
                'requires_approval' => false,
                'minimum_lead_time_hours' => null,
                'is_active' => false,
                'notes' => 'Superseded by night-shift template — kept for reference only.',
            ],
        ];

        foreach ($overtimeRows as $row) {
            OvertimePolicy::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => $row['code'],
                ],
                array_merge($row, [
                    'organization_id' => $organization->id,
                ]),
            );
        }
    }
}
