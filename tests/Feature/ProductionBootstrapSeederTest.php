<?php

use App\Models\BranchCalendarEvent;
use App\Models\CalendarEventCategory;
use App\Models\CompanyCalendarEvent;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\EmployeePosition;
use App\Models\OrganizationalUnit;
use App\Models\TeamCalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductionBootstrapSeeder;
use Database\Seeders\WorkScheduleTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'production_bootstrap.super_admin_email' => 'krysta.bootstrap@test.local',
        'production_bootstrap.hr_head_email' => 'kenneth.bootstrap@test.local',
        'production_bootstrap.organization_code' => 'PMPC',
    ]);
});

test('production bootstrap seeds panabo structure two accounts policies holidays and categories without calendar events', function (): void {
    (new ProductionBootstrapSeeder)->run();

    expect(User::query()->count())->toBe(2)
        ->and(OrganizationalUnit::query()->whereNull('parent_id')->count())->toBe(1)
        ->and(OrganizationalUnit::query()->where('code', 'PAN')->exists())->toBeTrue()
        ->and(OrganizationalUnit::query()->where('code', 'TAG')->doesntExist())->toBeTrue()
        ->and(EmployeeAssignment::query()->count())->toBe(0)
        ->and(CompanyCalendarEvent::query()->count())->toBe(0)
        ->and(BranchCalendarEvent::query()->count())->toBe(0)
        ->and(TeamCalendarEvent::query()->count())->toBe(0)
        ->and(CalendarEventCategory::query()->count())->toBe(12);

    $krysta = User::query()->where('email', 'krysta.bootstrap@test.local')->firstOrFail();

    /** @var Employee $krystaEmployee */
    $krystaEmployee = $krysta->employee()->firstOrFail();

    expect(EmployeePosition::query()->count())->toBe(2)
        ->and(EmployeePosition::query()->where('employee_id', $krystaEmployee->id)->count())->toBe(1)
        ->and(EmployeeEmployment::query()->where('employee_id', $krystaEmployee->id)->where('is_current', true)->exists())->toBeTrue()
        ->and((string) $krystaEmployee->workScheduleTemplate?->name)->toBe(WorkScheduleTemplatesSeeder::TEMPLATE_NAME_MON_SAT_SPLIT_OT);

    $superHire = EmployeeEmployment::query()
        ->where('employee_id', $krystaEmployee->id)
        ->where('is_current', true)
        ->value('hire_date');

    expect(CarbonImmutable::parse((string) $superHire)->toDateString())->toBe('2026-02-23');

    $hrHeadEmp = Employee::query()->where('id_number', config('production_bootstrap.hr_head_id_number'))->firstOrFail();

    expect(CarbonImmutable::parse((string) EmployeeEmployment::query()
        ->where('employee_id', $hrHeadEmp->id)
        ->where('is_current', true)
        ->value('hire_date'))->toDateString())->toBe('2016-06-24');
});
