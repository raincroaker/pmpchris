<?php

use App\Models\Employee;
use App\Models\EmployeeEmployment;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('employee user with active current employment can authenticate', function () {
    $employee = Employee::factory()->create();
    EmployeeEmployment::factory()->create([
        'employee_id' => $employee->id,
        'employment_status' => EmployeeEmployment::STATUS_ACTIVE,
        'is_current' => true,
        'separation_date' => null,
    ]);

    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('employee user with resigned employment can not authenticate', function () {
    $employee = Employee::factory()->create();
    EmployeeEmployment::factory()->resigned()->create([
        'employee_id' => $employee->id,
    ]);

    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['portal_access']);
});

test('employee user with terminated employment can not authenticate', function () {
    $employee = Employee::factory()->create();
    EmployeeEmployment::factory()->terminated()->create([
        'employee_id' => $employee->id,
    ]);

    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['portal_access']);
});

test('employee user with retired employment can not authenticate', function () {
    $employee = Employee::factory()->create();
    EmployeeEmployment::factory()->retired()->create([
        'employee_id' => $employee->id,
    ]);

    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['portal_access']);
});

test('employee user with contract ended employment can not authenticate', function () {
    $employee = Employee::factory()->create();
    EmployeeEmployment::factory()->contractEnded()->create([
        'employee_id' => $employee->id,
    ]);

    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['portal_access']);
});

test('employee user without current employment can not authenticate', function () {
    $employee = Employee::factory()->create();
    $user = User::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['portal_access']);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
