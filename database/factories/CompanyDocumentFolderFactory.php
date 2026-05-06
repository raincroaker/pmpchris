<?php

namespace Database\Factories;

use App\Models\CompanyDocumentFolder;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyDocumentFolder>
 */
class CompanyDocumentFolderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'parent_id' => null,
            'name' => fake()->words(2, true),
            'created_by_user_id' => User::factory(),
            'updated_by_user_id' => User::factory(),
        ];
    }
}
