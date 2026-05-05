<?php

use App\Enums\LeavePolicyUnit;
use App\Enums\OvertimePolicyContext;
use App\Models\LeavePolicy;
use App\Models\Organization;
use App\Models\OvertimePolicy;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists a leave policy scoped to an organization', function (): void {
    $organization = Organization::factory()->create();

    $policy = LeavePolicy::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'VL',
        'name' => 'Vacation leave',
        'unit' => LeavePolicyUnit::Days,
        'annual_entitlement' => 15,
        'use_accrual' => true,
        'paid' => true,
        'requires_approval' => true,
        'is_active' => true,
    ]);

    expect($policy->organization->is($organization))->toBeTrue()
        ->and($policy->fresh()->code)->toBe('VL');
});

it('persists an overtime policy scoped to an organization', function (): void {
    $organization = Organization::factory()->create();

    $policy = OvertimePolicy::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'OT-WD',
        'name' => 'Weekday overtime',
        'context' => OvertimePolicyContext::OrdinaryWeekday,
        'rate_multiplier' => 1.25,
        'daily_threshold_hours' => 8,
        'requires_approval' => true,
        'is_active' => true,
    ]);

    expect($policy->organization->is($organization))->toBeTrue()
        ->and($policy->fresh()->context)->toBe(OvertimePolicyContext::OrdinaryWeekday);
});

it('enforces unique policy code per organization', function (): void {
    $organization = Organization::factory()->create();

    LeavePolicy::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'SAME',
    ]);

    expect(fn () => LeavePolicy::factory()->create([
        'organization_id' => $organization->id,
        'code' => 'SAME',
    ]))->toThrow(QueryException::class);
});

it('allows the same policy code in different organizations', function (): void {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $a = LeavePolicy::factory()->create(['organization_id' => $orgA->id, 'code' => 'VL']);
    $b = LeavePolicy::factory()->create(['organization_id' => $orgB->id, 'code' => 'VL']);

    expect($a->code)->toBe($b->code)
        ->and($a->organization_id)->not->toBe($b->organization_id);
});

it('records deleted_by_user_id on soft delete when authenticated', function (): void {
    $user = User::factory()->create();
    $policy = OvertimePolicy::factory()->create();

    $this->actingAs($user);
    $policy->delete();

    $trashed = OvertimePolicy::onlyTrashed()->findOrFail($policy->id);

    expect($trashed->deleted_at)->not->toBeNull()
        ->and($trashed->deleted_by_user_id)->toBe($user->id);
});

it('clears deleted_by_user_id when restoring', function (): void {
    $user = User::factory()->create();
    $policy = LeavePolicy::factory()->create();

    $this->actingAs($user);
    $policy->delete();

    $trashed = LeavePolicy::onlyTrashed()->findOrFail($policy->id);
    expect($trashed->deleted_by_user_id)->toBe($user->id);

    $trashed->restore();

    expect($trashed->fresh()->deleted_at)->toBeNull()
        ->and($trashed->fresh()->deleted_by_user_id)->toBeNull();
});

it('exposes organization relations for policies', function (): void {
    $organization = Organization::factory()->create();
    LeavePolicy::factory()->count(2)->create(['organization_id' => $organization->id]);
    OvertimePolicy::factory()->create(['organization_id' => $organization->id]);

    $organization->load('leavePolicies', 'overtimePolicies');

    expect($organization->leavePolicies)->toHaveCount(2)
        ->and($organization->overtimePolicies)->toHaveCount(1);
});
