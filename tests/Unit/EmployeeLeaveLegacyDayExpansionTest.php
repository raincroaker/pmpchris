<?php

use App\Support\EmployeeLeaveLegacyDayExpansion;

describe('EmployeeLeaveLegacyDayExpansion', function (): void {
    it('expands a single calendar day with optional half flags', function (): void {
        $days = EmployeeLeaveLegacyDayExpansion::expandFromLegacySpan(
            '2026-05-20',
            '2026-05-20',
            true,
            false,
        );

        expect($days)->toHaveCount(1)
            ->and($days[0]['leave_date'])->toBe('2026-05-20')
            ->and($days[0]['is_half_day'])->toBeTrue();
    });

    it('expands contiguous span with half only on first and last day', function (): void {
        $days = EmployeeLeaveLegacyDayExpansion::expandFromLegacySpan(
            '2026-05-12',
            '2026-05-14',
            false,
            false,
        );

        expect($days)->toHaveCount(3)
            ->and($days[0]['leave_date'])->toBe('2026-05-12')
            ->and($days[0]['is_half_day'])->toBeFalse()
            ->and($days[1]['is_half_day'])->toBeFalse()
            ->and($days[2]['leave_date'])->toBe('2026-05-14')
            ->and($days[2]['is_half_day'])->toBeFalse();
    });

    it('returns empty when start is after end', function (): void {
        expect(
            EmployeeLeaveLegacyDayExpansion::expandFromLegacySpan(
                '2026-05-14',
                '2026-05-12',
                false,
                false,
            ),
        )->toBe([]);
    });
});
