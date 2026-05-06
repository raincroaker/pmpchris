<?php

namespace Database\Seeders;

use App\Models\HolidayType;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class HolidayTypesSeeder extends Seeder
{
    /**
     * Default PH-oriented holiday types per organization (aligned with `defaultHolidayTypesSeed` on the frontend).
     */
    public function run(): void
    {
        $organization = Organization::query()
            ->where('code', 'PMPC')
            ->where('is_active', true)
            ->first();

        if (! $organization instanceof Organization) {
            return;
        }

        $actorId = OrganizationSeedActorResolver::resolveUserId();

        $rows = [
            [
                'slug' => 'builtin-regular',
                'name' => 'Regular Holiday',
                'kind' => 'builtin',
                'color_key' => 'lime',
                'pay_policy' => 'Double Pay',
                'custom_multiplier' => null,
                'premium_note' => 'Typical PH rule: 200% of daily wage when the regular holiday is worked (first 8 hours). Check DOLE / CBA for exact stacks.',
            ],
            [
                'slug' => 'builtin-special-non-working',
                'name' => 'Special Non-Working Holiday',
                'kind' => 'builtin',
                'color_key' => 'amber',
                'pay_policy' => 'Custom Multiplier',
                'custom_multiplier' => '1.30x',
                'premium_note' => 'Typical PH rule when worked: 130% of daily wage (100% + 30%). Unworked is often “no work, no pay” unless company policy or CBA says otherwise.',
            ],
            [
                'slug' => 'builtin-special-working',
                'name' => 'Special Working Holiday',
                'kind' => 'builtin',
                'color_key' => 'sky',
                'pay_policy' => 'No Premium',
                'custom_multiplier' => null,
                'premium_note' => 'Typical PH rule: no statutory holiday premium—pay as an ordinary workday unless policy adds more.',
            ],
        ];

        foreach ($rows as $row) {
            HolidayType::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'slug' => $row['slug'],
                ],
                [
                    'name' => $row['name'],
                    'kind' => $row['kind'],
                    'color_key' => $row['color_key'],
                    'pay_policy' => $row['pay_policy'],
                    'custom_multiplier' => $row['custom_multiplier'],
                    'premium_note' => $row['premium_note'],
                    'created_by_user_id' => $actorId,
                    'updated_by_user_id' => $actorId,
                ],
            );
        }
    }
}
