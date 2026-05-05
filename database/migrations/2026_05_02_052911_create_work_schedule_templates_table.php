<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('clock_pattern', 32);
            $table->json('days');
            $table->json('segments')->nullable();
            $table->char('time_in', 5);
            $table->char('time_out', 5);
            $table->boolean('is_overnight')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('unpaid_break_minutes')->default(0);
            $table->unsignedSmallInteger('grace_late_arrival_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'name']);
            $table->index(['organization_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_schedule_templates');
    }
};
