<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Explicit calendar days counted toward an {@see EmployeeLeave} header (units = 1 or ½ per row).
     *
     * Denormalized `organization_id` / `employee_id` mirror the parent for reporting filters
     * (e.g. days used in a month by employee) without always joining `employee_leaves`.
     */
    public function up(): void
    {
        Schema::create('employee_leave_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('employee_leave_id')->constrained('employee_leaves')->cascadeOnDelete();
            $table->date('leave_date');
            $table->boolean('is_half_day')->default(false);
            $table->timestamps();

            $table->unique(['employee_leave_id', 'leave_date']);
            $table->index(['organization_id', 'leave_date']);
            $table->index(['employee_id', 'leave_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leave_days');
    }
};
