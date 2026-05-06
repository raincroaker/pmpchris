<?php

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeEmployment;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\UnitChatMessage;
use App\Models\UnitChatRead;
use App\Models\UnitChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['hris.branch_picker_enabled' => false]);
    seedChatAccessRoles();
});

test('authenticated users can visit the chat page', function (): void {
    [$user] = createUnitChatMembership();

    $this->actingAs($user)
        ->get(route('chat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Chat')
            ->has('rooms', 1)
            ->where('searchQuery', '')
            ->where('rooms.0.members.0.statusLine', 'Unit head'));
});

test('unit members can send text-only messages', function (): void {
    [$user, $unit] = createUnitChatMembership();
    $room = UnitChatRoom::query()->create([
        'organizational_unit_id' => $unit->id,
    ]);

    $this->actingAs($user)
        ->post(route('chat.messages.store', ['room' => $room->id]), [
            'body' => 'Hello unit.',
        ])
        ->assertRedirect(route('chat', ['room' => $room->id]));

    $storedMessage = UnitChatMessage::query()->first();
    $room->refresh();

    expect($storedMessage)->not()->toBeNull()
        ->and((string) $storedMessage?->body)->toBe('Hello unit.')
        ->and((int) $storedMessage?->sender_user_id)->toBe($user->id)
        ->and((int) $room->last_message_id)->toBe((int) $storedMessage?->id)
        ->and($room->last_message_at)->not()->toBeNull();

    expect(UnitChatRead::query()
        ->where('unit_chat_room_id', $room->id)
        ->where('user_id', $user->id)
        ->value('last_read_message_id'))->toBe((int) $storedMessage?->id);
});

test('non-members cannot send unit chat messages', function (): void {
    [, $unit] = createUnitChatMembership();
    [$outsider] = createUnitChatMembership();

    $room = UnitChatRoom::query()->create([
        'organizational_unit_id' => $unit->id,
    ]);

    $this->actingAs($outsider)
        ->post(route('chat.messages.store', ['room' => $room->id]), [
            'body' => 'Should fail',
        ])
        ->assertForbidden();

    expect(UnitChatMessage::query()->get()->count())->toBe(0);
});

test('super admin can see all unit chats without assignment membership', function (): void {
    $unitA = OrganizationalUnit::factory()->create(['name' => 'Unit A']);
    $unitB = OrganizationalUnit::factory()->create(['name' => 'Unit B']);

    UnitChatRoom::query()->create(['organizational_unit_id' => $unitA->id]);
    UnitChatRoom::query()->create(['organizational_unit_id' => $unitB->id]);

    createAssignedEmployeeForUnit($unitA, false);
    createAssignedEmployeeForUnit($unitB, true);

    $superAdmin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create([
        'employee_id' => null,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('chat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Chat')
            ->has('rooms', 2));
});

test('hr head can post unit chat message without unit assignment membership', function (): void {
    [, $unit] = createUnitChatMembership();
    $room = UnitChatRoom::query()->create([
        'organizational_unit_id' => $unit->id,
    ]);

    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create([
        'employee_id' => null,
    ]);

    $this->actingAs($hrHead)
        ->post(route('chat.messages.store', ['room' => $room->id]), [
            'body' => 'HR announcement',
        ])
        ->assertRedirect(route('chat', ['room' => $room->id]));

    $storedMessage = UnitChatMessage::query()->latest('id')->first();

    expect($storedMessage)->not()->toBeNull()
        ->and((string) $storedMessage?->body)->toBe('HR announcement')
        ->and((int) $storedMessage?->sender_user_id)->toBe($hrHead->id);
});

test('chat list can be filtered by unit search query', function (): void {
    [$user] = createUnitChatMembership();
    $alphaUnit = OrganizationalUnit::factory()->create([
        'name' => 'Alpha Team',
        'code' => 'ALPHA',
    ]);
    $betaUnit = OrganizationalUnit::factory()->create([
        'name' => 'Beta Team',
        'code' => 'BETA',
    ]);

    attachEmployeeToUnit($user, $alphaUnit, false);
    attachEmployeeToUnit($user, $betaUnit, false);

    UnitChatRoom::query()->create(['organizational_unit_id' => $alphaUnit->id]);
    UnitChatRoom::query()->create(['organizational_unit_id' => $betaUnit->id]);

    $this->actingAs($user)
        ->get(route('chat', ['q' => 'alpha']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Chat')
            ->has('rooms', 1)
            ->where('rooms.0.name', 'Alpha Team')
            ->where('searchQuery', 'alpha'));
});

test('chat list returns unread count for current user', function (): void {
    [$user, $unit] = createUnitChatMembership();
    $room = UnitChatRoom::query()->create([
        'organizational_unit_id' => $unit->id,
    ]);

    $room->messages()->create(['sender_user_id' => $user->id, 'body' => 'One']);
    $room->messages()->create(['sender_user_id' => $user->id, 'body' => 'Two']);

    $this->actingAs($user)
        ->get(route('chat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Chat')
            ->where('rooms.0.unreadCount', 2));
});

test('chat list unread count does not include sender own just-sent message', function (): void {
    [$user, $unit] = createUnitChatMembership();
    $room = UnitChatRoom::query()->create([
        'organizational_unit_id' => $unit->id,
    ]);

    $this->actingAs($user)
        ->post(route('chat.messages.store', ['room' => $room->id]), [
            'body' => 'Self-sent',
        ])
        ->assertRedirect(route('chat', ['room' => $room->id]));

    $this->actingAs($user)
        ->get(route('chat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Chat')
            ->where('rooms.0.unreadCount', 0));
});

test('unit members can load room messages with pagination metadata', function (): void {
    [$user, $unit] = createUnitChatMembership();
    $room = UnitChatRoom::query()->create([
        'organizational_unit_id' => $unit->id,
    ]);

    $room->messages()->create([
        'sender_user_id' => $user->id,
        'body' => 'One',
    ]);
    $room->messages()->create([
        'sender_user_id' => $user->id,
        'body' => 'Two',
    ]);
    $room->messages()->create([
        'sender_user_id' => $user->id,
        'body' => 'Three',
    ]);

    $this->actingAs($user)
        ->get(route('chat.messages.index', ['room' => $room->id, 'limit' => 2]))
        ->assertOk()
        ->assertJson([
            'hasMore' => true,
        ])
        ->assertJsonCount(2, 'messages');
});

test('non-members cannot load room messages', function (): void {
    [, $unit] = createUnitChatMembership();
    [$outsider] = createUnitChatMembership();

    $room = UnitChatRoom::query()->create([
        'organizational_unit_id' => $unit->id,
    ]);

    $this->actingAs($outsider)
        ->get(route('chat.messages.index', ['room' => $room->id]))
        ->assertForbidden();
});

test('marking a room read stores read cursor and clears unread count', function (): void {
    [$user, $unit] = createUnitChatMembership();
    $room = UnitChatRoom::query()->create([
        'organizational_unit_id' => $unit->id,
    ]);
    $first = $room->messages()->create(['sender_user_id' => $user->id, 'body' => 'One']);
    $second = $room->messages()->create(['sender_user_id' => $user->id, 'body' => 'Two']);

    $this->actingAs($user)
        ->post(route('chat.read.store', ['room' => $room->id]), [
            'last_read_message_id' => $second->id,
        ])
        ->assertNoContent();

    expect(UnitChatRead::query()
        ->where('unit_chat_room_id', $room->id)
        ->where('user_id', $user->id)
        ->value('last_read_message_id'))->toBe((int) $second->id);

    $this->actingAs($user)
        ->get(route('chat'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('rooms.0.unreadCount', 0));

    expect($first)->not()->toBeNull();
});

test('non-members cannot mark room read', function (): void {
    [, $unit] = createUnitChatMembership();
    [$outsider] = createUnitChatMembership();
    $room = UnitChatRoom::query()->create([
        'organizational_unit_id' => $unit->id,
    ]);

    $this->actingAs($outsider)
        ->post(route('chat.read.store', ['room' => $room->id]))
        ->assertForbidden();
});

/**
 * @return array{0: User, 1: OrganizationalUnit}
 */
function createUnitChatMembership(bool $isHead = true): array
{
    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
    ]);
    $unit = OrganizationalUnit::factory()->create();

    EmployeeAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => $unit->id,
        'is_primary' => true,
        'is_head' => $isHead,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => null,
    ]);

    /** @var User $user */
    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);

    return [$user, $unit];
}

function createAssignedEmployeeForUnit(OrganizationalUnit $unit, bool $isHead = false): User
{
    $employee = Employee::factory()->create();
    $employment = EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'is_current' => true,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
    ]);

    EmployeeAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => $unit->id,
        'is_primary' => true,
        'is_head' => $isHead,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => null,
    ]);

    /** @var User $user */
    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);

    return $user;
}

function seedChatAccessRoles(): void
{
    Role::query()->firstOrCreate(
        ['code' => Role::CODE_SUPER_ADMIN],
        ['name' => 'Super Administrator'],
    );
    Role::query()->firstOrCreate(
        ['code' => Role::CODE_HR_HEAD],
        ['name' => 'HR Head'],
    );
}

function attachEmployeeToUnit(User $user, OrganizationalUnit $unit, bool $isHead = false): void
{
    $employeeId = (int) $user->employee_id;
    if ($employeeId <= 0) {
        return;
    }

    $employment = EmployeeEmployment::query()
        ->where('employee_id', $employeeId)
        ->where('is_current', true)
        ->first();
    if (! $employment) {
        return;
    }

    EmployeeAssignment::query()->create([
        'employee_id' => $employeeId,
        'employee_employment_id' => $employment->id,
        'organization_id' => null,
        'organizational_unit_id' => $unit->id,
        'is_primary' => false,
        'is_head' => $isHead,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => null,
    ]);
}
