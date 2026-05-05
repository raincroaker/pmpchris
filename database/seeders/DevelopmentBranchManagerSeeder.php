<?php

namespace Database\Seeders;

use App\Models\BranchManager;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentBranchManagerSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->where('code', 'PMPC')->first();
        if ($organization === null) {
            return;
        }

        $roots = OrganizationalUnit::query()
            ->where('organization_id', $organization->id)
            ->whereNull('parent_id')
            ->whereIn('code', ['PAN', 'TAG'])
            ->get(['id', 'code'])
            ->keyBy('code');

        $panRoot = $roots->get('PAN');
        $tagRoot = $roots->get('TAG');
        if ($panRoot === null || $tagRoot === null) {
            return;
        }

        $managerOne = User::query()->where('email', 'hrmanager1@example.com')->first();
        $managerTwo = User::query()->where('email', 'hrmanager2@example.com')->first();
        if ($managerOne === null || $managerTwo === null) {
            return;
        }

        BranchManager::query()
            ->whereIn('user_id', [$managerOne->id, $managerTwo->id])
            ->delete();

        BranchManager::query()->create([
            'user_id' => $managerOne->id,
            'root_unit_id' => (int) $panRoot->id,
            'is_active' => true,
            'notes' => 'Scope demo: managed PAN, affiliated PAN+TAG.',
        ]);

        BranchManager::query()->create([
            'user_id' => $managerTwo->id,
            'root_unit_id' => (int) $tagRoot->id,
            'is_active' => true,
            'notes' => 'Scope demo: managed TAG, affiliated PAN+TAG.',
        ]);
    }
}
