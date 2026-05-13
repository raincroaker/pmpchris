<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Canonical bootstrap: {@see PhaseOneInitialSystemSeeder} (PMPC org, roles, structure, policies, two accounts).
     * {@see DemoCooperativeSeeder} remains available for feature tests that need a richer PAN/TAG demo tree.
     */
    public function run(): void
    {
        $this->call(PhaseOneInitialSystemSeeder::class);
    }
}
