<?php

namespace Database\Seeders;

use App\Models\HolidayType;
use App\Models\Organization;
use App\Models\OrganizationHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Sample PH-oriented holidays for local demo (PMPC org).
 *
 * Fixed Gregorian dates use yearly recurrence for stable calendar UX.
 * Movable holidays (Holy Week, Chinese New Year, National Heroes Day rules) are seeded for 2026 only — update or replace yearly per official proclamations.
 */
class OrganizationHolidaysSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()
            ->where('code', 'PMPC')
            ->where('is_active', true)
            ->first();

        if (! $organization instanceof Organization) {
            return;
        }

        $actorId = OrganizationSeedActorResolver::resolveUserId();

        $typeIds = HolidayType::query()
            ->where('organization_id', $organization->id)
            ->whereIn('slug', ['builtin-regular', 'builtin-special-non-working', 'builtin-special-working'], 'and', false)
            ->pluck('id', 'slug')
            ->all();

        $regularId = $typeIds['builtin-regular'] ?? null;
        $specialNwId = $typeIds['builtin-special-non-working'] ?? null;
        $specialWorkingId = $typeIds['builtin-special-working'] ?? null;

        if ($regularId === null || $specialNwId === null || $specialWorkingId === null) {
            return;
        }

        $yearlyNever = [
            'frequency' => 'yearly',
            'interval' => 1,
            'ends' => ['type' => 'never'],
        ];

        /** @var list<array{name: string, start_date: string, end_date: string, holiday_type_id: int, recurrence: array<string, mixed>|null, notes: string|null}> $rows */
        $rows = [
            // Globally recognized anchors also observed in PH
            ['name' => "New Year's Day", 'start_date' => '01-01', 'end_date' => '01-01', 'type' => 'regular', 'recurrence' => $yearlyNever, 'notes' => 'Fixed date; yearly repetition in calendar.'],
            ['name' => 'Christmas Day', 'start_date' => '12-25', 'end_date' => '12-25', 'type' => 'regular', 'recurrence' => $yearlyNever, 'notes' => 'Fixed date; widely observed.'],

            // Common PH fixed-date holidays (regular)
            ['name' => 'Araw ng Kagitingan (Day of Valor)', 'start_date' => '04-09', 'end_date' => '04-09', 'type' => 'regular', 'recurrence' => $yearlyNever, 'notes' => 'Republic Act No. 3022 / subsequent laws; verify classification yearly.'],
            ['name' => 'Labor Day', 'start_date' => '05-01', 'end_date' => '05-01', 'type' => 'regular', 'recurrence' => $yearlyNever, 'notes' => 'International Workers’ Day; PH legal holiday.'],
            ['name' => 'Independence Day', 'start_date' => '06-12', 'end_date' => '06-12', 'type' => 'regular', 'recurrence' => $yearlyNever, 'notes' => null],
            ['name' => 'Bonifacio Day', 'start_date' => '11-30', 'end_date' => '11-30', 'type' => 'regular', 'recurrence' => $yearlyNever, 'notes' => null],
            ['name' => 'Rizal Day', 'start_date' => '12-30', 'end_date' => '12-30', 'type' => 'regular', 'recurrence' => $yearlyNever, 'notes' => null],

            // Typical special non-working (fixed)
            ['name' => 'Ninoy Aquino Day', 'start_date' => '08-21', 'end_date' => '08-21', 'type' => 'special_nw', 'recurrence' => $yearlyNever, 'notes' => 'Often observed as special non-working; confirm annual proclamation.'],
            ['name' => "All Saints' Day", 'start_date' => '11-01', 'end_date' => '11-01', 'type' => 'special_nw', 'recurrence' => $yearlyNever, 'notes' => 'Undas; commonly special non-working when proclaimed.'],
            ['name' => "All Souls' Day", 'start_date' => '11-02', 'end_date' => '11-02', 'type' => 'special_nw', 'recurrence' => $yearlyNever, 'notes' => 'Common observance in PH; holiday status is proclamation-dependent.'],
            ['name' => 'Feast of the Immaculate Conception of Mary', 'start_date' => '12-08', 'end_date' => '12-08', 'type' => 'special_nw', 'recurrence' => $yearlyNever, 'notes' => null],
            ['name' => 'Christmas Eve', 'start_date' => '12-24', 'end_date' => '12-24', 'type' => 'special_nw', 'recurrence' => $yearlyNever, 'notes' => 'Frequently declared special non-working by proclamation.'],

            // Typical special working (fixed)
            ['name' => 'EDSA People Power Revolution Anniversary', 'start_date' => '02-25', 'end_date' => '02-25', 'type' => 'special_working', 'recurrence' => $yearlyNever, 'notes' => 'Commonly classified as a special working holiday.'],
        ];

        // Anchor recurring rows to a representative year for month/day storage (expansion uses month/day only).
        $anchorYear = 2026;
        foreach ($rows as $row) {
            [$m, $d] = explode('-', $row['start_date']);
            [$me, $de] = explode('-', $row['end_date']);
            $start = sprintf('%04d-%02d-%02d', $anchorYear, (int) $m, (int) $d);
            $end = sprintf('%04d-%02d-%02d', $anchorYear, (int) $me, (int) $de);
            $typeId = match ($row['type']) {
                'special_nw' => $specialNwId,
                'special_working' => $specialWorkingId,
                default => $regularId,
            };

            OrganizationHoliday::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $row['name'],
                    'start_date' => $start,
                    'end_date' => $end,
                ],
                [
                    'holiday_type_id' => $typeId,
                    'notes' => $row['notes'],
                    'recurrence' => $row['recurrence'],
                    'set_by_user_id' => $actorId,
                    'last_edited_by_user_id' => $actorId,
                ],
            );
        }

        $this->seedTwoThousandTwentySixMovable(
            (int) $organization->id,
            (int) $regularId,
            (int) $specialNwId,
            $actorId !== null ? (int) $actorId : null,
        );
    }

    /**
     * One-off rows for 2026 movable / ruled dates (no yearly recurrence).
     */
    private function seedTwoThousandTwentySixMovable(
        int $organizationId,
        int $regularTypeId,
        int $specialNwTypeId,
        ?int $actorId,
    ): void {
        $easterSunday = CarbonImmutable::createFromTimestamp(easter_date(2026))
            ->startOfDay();
        $maundyThursday = $easterSunday->subDays(3);
        $goodFriday = $easterSunday->subDays(2);
        $nationalHeroesDay = $this->lastMondayOfMonth(2026, 8);

        $movable = [
            [
                'name' => 'Maundy Thursday',
                'start' => $maundyThursday->toDateString(),
                'end' => $maundyThursday->toDateString(),
                'type_id' => $regularTypeId,
                'notes' => '2026 only (Holy Week). Dates move with Easter; replace yearly per proclamation.',
            ],
            [
                'name' => 'Good Friday',
                'start' => $goodFriday->toDateString(),
                'end' => $goodFriday->toDateString(),
                'type_id' => $regularTypeId,
                'notes' => '2026 only (Holy Week).',
            ],
            [
                'name' => 'National Heroes Day',
                'start' => $nationalHeroesDay->toDateString(),
                'end' => $nationalHeroesDay->toDateString(),
                'type_id' => $regularTypeId,
                'notes' => '2026: last Monday of August. Law uses rule-based date — use app logic or annual updates for other years.',
            ],
            [
                'name' => 'Chinese New Year',
                'start' => '2026-02-17',
                'end' => '2026-02-17',
                'type_id' => $specialNwTypeId,
                'notes' => '2026 lunisolar Year of the Horse; often special non-working when proclaimed. Verify annually.',
            ],
            [
                'name' => 'Black Saturday',
                'start' => '2026-04-04',
                'end' => '2026-04-04',
                'type_id' => $specialNwTypeId,
                'notes' => '2026 Holy Week observance; proclamation-dependent classification.',
            ],
            [
                'name' => 'Last Day of the Year (special non-working — when proclaimed)',
                'start' => '2026-12-31',
                'end' => '2026-12-31',
                'type_id' => $specialNwTypeId,
                'notes' => 'Observance varies by proclamation; sample 2026 row for demo.',
            ],
        ];

        foreach ($movable as $row) {
            OrganizationHoliday::query()->updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'name' => $row['name'],
                    'start_date' => $row['start'],
                    'end_date' => $row['end'],
                ],
                [
                    'holiday_type_id' => $row['type_id'],
                    'notes' => $row['notes'],
                    'recurrence' => null,
                    'set_by_user_id' => $actorId,
                    'last_edited_by_user_id' => $actorId,
                ],
            );
        }
    }

    private function lastMondayOfMonth(int $year, int $month): CarbonImmutable
    {
        $day = CarbonImmutable::create($year, $month, 1)->endOfMonth();

        while (! $day->isMonday()) {
            $day = $day->subDay();
        }

        return $day;
    }
}
