<?php

use App\Enums\WorkScheduleClockPattern;
use App\Models\BranchManager;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use App\Models\WorkScheduleTemplate;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new OrganizationalStructureSeeder)->run();
    (new RoleSeeder)->run();
});

test('authorized user can create update and delete work schedule templates via api', function (): void {
    $org = Organization::factory()->create([
        'code' => 'T-WS-TEMPLATES',
        'name' => 'WS Templates Coop',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-WS-TEMPLATES']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    $branch = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-WS',
        'name' => 'WS Branch',
        'is_active' => true,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $storePayload = [
        'name' => 'Standard Weekday',
        'clock_pattern' => WorkScheduleClockPattern::SinglePair->value,
        'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        'segments' => null,
        'time_in' => '08:30',
        'time_out' => '17:00',
        'is_overnight' => false,
        'is_active' => true,
        'unpaid_break_minutes' => 60,
        'grace_late_arrival_minutes' => 15,
        'notes' => 'From test.',
        'attendance_rules' => [
            'graceUsesLimitEnabled' => true,
            'graceUsesPerMonth' => 2,
            'clockInRounding' => '15',
            'clockInRoundingCustomMinutes' => 10,
            'netRegularHoursCapEnabled' => true,
            'netRegularHoursCap' => 8,
            'advancedOpen' => false,
        ],
        'overtime_rules' => [
            'otBlockEnabled' => true,
            'otTimeIn' => '18:00',
            'otTimeOut' => '21:30',
            'otIsOvernight' => false,
            'continuousAfterRegularNet' => true,
            'otGraceMinutes' => 7,
        ],
    ];

    $createResponse = $this->actingAs($user)
        ->postJson(route('attendance.work-schedule-templates.store'), $storePayload);

    $createResponse->assertCreated()
        ->assertJsonPath('data.name', 'Standard Weekday')
        ->assertJsonPath('data.attendance_rules.clockInRounding', '15')
        ->assertJsonPath('data.overtime_rules.otGraceMinutes', 7);

    $templateId = (int) $createResponse->json('data.id');

    expect(
        WorkScheduleTemplate::query()->where('organization_id', $org->id)->count(),
    )->toBe(1);

    $persisted = WorkScheduleTemplate::query()->findOrFail($templateId);
    expect($persisted->attendance_rules['graceUsesPerMonth'] ?? null)->toBe(2);
    expect($persisted->overtime_rules['otTimeOut'] ?? null)->toBe('21:30');

    $patchPayload = array_merge($storePayload, [
        'name' => 'Standard Weekday Updated',
        'grace_late_arrival_minutes' => 20,
        'overtime_rules' => [
            'otBlockEnabled' => true,
            'otTimeIn' => '17:45',
            'otTimeOut' => '21:30',
            'otIsOvernight' => false,
            'continuousAfterRegularNet' => false,
            'otGraceMinutes' => 3,
        ],
    ]);

    $this->actingAs($user)
        ->patchJson(route('attendance.work-schedule-templates.update', ['workScheduleTemplate' => $templateId]), $patchPayload)
        ->assertOk()
        ->assertJsonPath('data.name', 'Standard Weekday Updated')
        ->assertJsonPath('data.overtime_rules.continuousAfterRegularNet', false)
        ->assertJsonPath('data.overtime_rules.otGraceMinutes', 3);

    $persistedFresh = WorkScheduleTemplate::query()->findOrFail($templateId);
    expect($persistedFresh->overtime_rules['continuousAfterRegularNet'] ?? null)->toBeFalse();

    $this->actingAs($user)
        ->delete(route('attendance.work-schedule-templates.destroy', ['workScheduleTemplate' => $templateId]))
        ->assertNoContent();

    expect(
        WorkScheduleTemplate::query()->where('organization_id', $org->id)->count(),
    )->toBe(0);
});

test('hr manager may view work schedules page but cannot mutate templates via api', function (): void {
    $org = Organization::factory()->create([
        'code' => 'T-WS-HRM',
        'name' => 'WS HR Manager Coop',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-WS-HRM']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    $branch = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-HRM',
        'name' => 'HR Manager Branch',
        'is_active' => true,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_MANAGER)->create();

    BranchManager::factory()->create([
        'user_id' => $user->id,
        'root_unit_id' => $branch->id,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $template = WorkScheduleTemplate::factory()->create([
        'organization_id' => $org->id,
        'name' => 'Locked Row',
    ]);

    $this->actingAs($user)
        ->get(route('attendance.shifts'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.canViewWorkSchedules', true)
            ->where('can.canMutateWorkSchedules', false));

    $storePayload = [
        'name' => 'Should Fail',
        'clock_pattern' => WorkScheduleClockPattern::SinglePair->value,
        'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        'segments' => null,
        'time_in' => '08:30',
        'time_out' => '17:00',
        'is_overnight' => false,
        'is_active' => true,
        'unpaid_break_minutes' => 60,
        'grace_late_arrival_minutes' => 15,
        'notes' => null,
    ];

    $this->actingAs($user)
        ->postJson(route('attendance.work-schedule-templates.store'), $storePayload)
        ->assertForbidden();

    $patchPayload = [
        'name' => 'Still Locked',
        'clock_pattern' => WorkScheduleClockPattern::SinglePair->value,
        'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        'segments' => null,
        'time_in' => '09:00',
        'time_out' => '18:00',
        'is_overnight' => false,
        'is_active' => true,
        'unpaid_break_minutes' => 45,
        'grace_late_arrival_minutes' => 10,
        'notes' => null,
    ];

    $this->actingAs($user)
        ->patchJson(route('attendance.work-schedule-templates.update', ['workScheduleTemplate' => $template->id]), $patchPayload)
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('attendance.work-schedule-templates.destroy', ['workScheduleTemplate' => $template->id]))
        ->assertForbidden();

    expect(WorkScheduleTemplate::query()->whereKey($template->id)->exists())->toBeTrue();
});

test('work schedule api rejects overtime rules block enabled without ot times', function (): void {
    $org = Organization::factory()->create([
        'code' => 'T-WS-OT-V',
        'name' => 'WS OT Validation Coop',
        'is_active' => true,
    ]);

    config(['hris.default_organization_code' => 'T-WS-OT-V']);

    $branchType = UnitType::query()->where('name', 'Branch')->firstOrFail();

    $branch = OrganizationalUnit::factory()->create([
        'organization_id' => $org->id,
        'unit_type_id' => $branchType->id,
        'parent_id' => null,
        'code' => 'BR-OTV',
        'name' => 'OT Branch',
        'is_active' => true,
    ]);

    /** @var User $user */
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->post(route('branch.store'), [
            'branch_id' => $branch->id,
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $badOtPayload = [
        'name' => 'Broken OT payload',
        'clock_pattern' => WorkScheduleClockPattern::SinglePair->value,
        'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        'segments' => null,
        'time_in' => '08:30',
        'time_out' => '17:00',
        'is_overnight' => false,
        'is_active' => true,
        'unpaid_break_minutes' => 60,
        'grace_late_arrival_minutes' => 15,
        'notes' => null,
        'attendance_rules' => null,
        'overtime_rules' => [
            'otBlockEnabled' => true,
            'otTimeIn' => '18:00',
            'otTimeOut' => '',
            'otIsOvernight' => false,
            'continuousAfterRegularNet' => true,
            'otGraceMinutes' => 5,
        ],
    ];

    $this->actingAs($user)
        ->postJson(route('attendance.work-schedule-templates.store'), $badOtPayload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['overtime_rules']);
});
