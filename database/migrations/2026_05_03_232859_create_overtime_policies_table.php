<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organization-scoped overtime premium rules (context, multipliers, caps).
     */
    public function up(): void
    {
        Schema::create('overtime_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('context', 40);
            $table->decimal('rate_multiplier', 8, 4);
            $table->decimal('daily_threshold_hours', 8, 4);
            $table->decimal('daily_cap_hours', 8, 4)->nullable();
            $table->decimal('weekly_cap_hours', 8, 4)->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->decimal('minimum_lead_time_hours', 10, 4)->nullable();
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
        Schema::dropIfExists('overtime_policies');
    }
};
