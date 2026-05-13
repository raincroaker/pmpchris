<?php

use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\PhaseOneInitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calendar organization holidays index returns filtered rows', function (): void {
    (new PhaseOneInitialSystemSeeder)->run();

    $user = User::query()->where('email', 'superadmin@hrnexus.com')->firstOrFail();
    $panabo = OrganizationalUnit::query()->where('code', 'PAN')->firstOrFail();

    $response = $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => $panabo->id])
        ->getJson(route('calendar.organization-holidays.index', [
            'month' => '2026-04',
            'range' => 'custom',
            'custom_from' => '2026-04-01',
            'custom_to' => '2026-04-10',
            'search' => 'Black Saturday',
            'type_ids' => 'builtin-special-non-working',
            'per_page' => 50,
        ]));

    $response->assertOk();
    $response->assertJsonPath('meta.hasMore', false);
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'Black Saturday');
    $response->assertJsonPath('data.0.type_id', 'builtin-special-non-working');
});
