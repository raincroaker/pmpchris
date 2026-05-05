<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organization-wide holiday calendar entries (no branch scope — visible across the org).
     */
    public function up(): void
    {
        Schema::create('organization_holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('holiday_type_id')->constrained('holiday_types')->restrictOnDelete();
            $table->string('name', 160);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('notes')->nullable();
            $table->json('recurrence')->nullable();
            $table->foreignId('set_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'start_date']);
            $table->index(['organization_id', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_holidays');
    }
};
