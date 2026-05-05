<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organization-scoped holiday classification (pay display, chips).
     */
    public function up(): void
    {
        Schema::create('holiday_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('slug', 120);
            $table->string('name', 120);
            $table->string('kind', 20);
            $table->string('color_key', 40);
            $table->string('pay_policy', 40);
            $table->string('custom_multiplier', 32)->nullable();
            $table->text('premium_note')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_types');
    }
};
