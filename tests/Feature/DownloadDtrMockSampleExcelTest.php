<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

uses(RefreshDatabase::class);

test('guests are redirected when downloading dtr mock excel', function (): void {
    $this->get(route('attendance.reports.dtr-mock-sample'))
        ->assertRedirect(route('login'));
});

test('authenticated users can download dtr mock excel', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('attendance.reports.dtr-mock-sample'));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
    expect($response->headers->get('content-disposition'))->toContain('DTR_Santos_EMP20240042_May-2026.xlsx');

    $binary = $response->baseResponse;
    expect($binary)->toBeInstanceOf(BinaryFileResponse::class);
    assert($binary instanceof BinaryFileResponse);
    $path = $binary->getFile()->getPathname();
    expect(substr((string) file_get_contents($path), 0, 2))->toBe('PK');
});
