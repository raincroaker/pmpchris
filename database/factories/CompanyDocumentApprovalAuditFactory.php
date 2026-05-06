<?php

namespace Database\Factories;

use App\Models\CompanyDocument;
use App\Models\CompanyDocumentApprovalAudit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyDocumentApprovalAudit>
 */
class CompanyDocumentApprovalAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_document_id' => CompanyDocument::factory(),
            'action' => 'auto_approved',
            'actor_user_id' => User::factory(),
            'note' => null,
            'metadata' => ['status' => 'approved', 'access_mode' => 'private'],
            'created_at' => now(),
        ];
    }
}
