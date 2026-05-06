<?php

namespace Database\Factories;

use App\Models\CompanyDocument;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyDocument>
 */
class CompanyDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true).'.pdf';

        return [
            'organization_id' => Organization::factory(),
            'folder_id' => null,
            'original_name' => $name,
            'stored_name' => fake()->uuid().'.pdf',
            'disk' => 'local',
            'path' => 'company-documents/'.fake()->numberBetween(1, 10).'/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => fake()->numberBetween(1024, 5 * 1024 * 1024),
            'checksum_sha256' => hash('sha256', (string) fake()->uuid()),
            'uploaded_by_user_id' => User::factory(),
            'access_mode' => 'private',
            'status' => 'approved',
            'submitted_at' => now(),
            'decided_at' => now(),
            'decided_by_user_id' => User::factory(),
            'decision_note' => null,
            'tags' => ['Policy'],
            'notes' => null,
        ];
    }
}
