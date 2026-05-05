<?php

use App\Exports\DtrMockExport;
use Carbon\CarbonImmutable;

test('format short duration range omits repeated year on start', function (): void {
    expect(DtrMockExport::formatShortDurationRange(
        CarbonImmutable::create(2026, 1, 1),
        CarbonImmutable::create(2026, 1, 31),
    ))->toBe('Jan 1 - Jan 31, 2026');
});

test('format short duration range for may sample', function (): void {
    expect(DtrMockExport::formatShortDurationRange(
        CarbonImmutable::create(2026, 5, 1),
        CarbonImmutable::create(2026, 5, 31),
    ))->toBe('May 1 - May 31, 2026');
});

test('sample export includes footer collections and compact duration', function (): void {
    $export = DtrMockExport::sample();

    expect($export->durationLabel)->toBe('May 1 - May 31, 2026');
    expect($export->otRequests)->toHaveCount(2);
    expect($export->otRequests->first()['type'])->toBe('Ordinary weekday OT');
    expect($export->leaveRequests)->toHaveCount(2);
    expect($export->leaveRequests->first()['type'])->toBe('Vacation leave (1 day)');
    expect($export->holidaysInMonth)->toHaveCount(1);
    expect($export->holidaysInMonth->first()['name'])->toBe('Labor Day');
    expect($export->workScheduleReference)->not->toBeNull();
    expect($export->workScheduleReference['template_name'])->toContain('Split day');
    expect($export->workScheduleReference['is_overnight'])->toBeFalse();
    expect($export->workScheduleReference['clock_pattern'])->toBe('Split sessions (same day)');
    expect($export->title())->toBe('Santos_May2026_EMP20240042');
    expect($export->downloadFileName())->toBe('DTR_Santos_EMP20240042_May-2026.xlsx');
});
