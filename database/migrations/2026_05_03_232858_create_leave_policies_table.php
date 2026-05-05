<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organization-scoped leave policy catalog (entitlement, accrual, carryover).
     */
    public function up(): void
    {
        Schema::create('leave_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('unit', 20);
            $table->decimal('annual_entitlement', 10, 4);
            $table->boolean('use_accrual')->default(false);
            $table->string('accrual_cadence', 20)->nullable();
            $table->decimal('accrual_per_period', 10, 4)->nullable();
            $table->decimal('max_balance', 12, 4)->nullable();
            $table->boolean('carryover_allowed')->default(false);
            $table->decimal('carryover_cap', 12, 4)->nullable();
            $table->boolean('paid')->default(true);
            $table->boolean('requires_approval')->default(true);
            $table->unsignedTinyInteger('applies_after_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
    }
};
