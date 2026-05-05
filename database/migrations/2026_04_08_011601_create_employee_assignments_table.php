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
        Schema::create('employee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_employment_id')->constrained('employee_employments')->cascadeOnDelete();

            /*
             * XOR (exactly one non-null): either assigned to the organization as a whole, or to a specific unit.
             * - organization_id set, organizational_unit_id null → org-level assignment (not tied to a unit row).
             * - organization_id null, organizational_unit_id set → unit-level assignment (typical branch/dept).
             * Org chart payload today is built from unit IDs only; org-only rows are excluded until indexed explicitly.
             */
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units')->cascadeOnDelete();

            $table->boolean('is_primary')->default(false);
            $table->boolean('is_head')->default(false);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'start_date']);
            $table->index(['employee_employment_id', 'start_date'], 'emp_asg_emp_employment_start_idx');
            $table->index('organization_id');

            // XOR (exactly one of organization_id vs organizational_unit_id) is enforced in {@see EmployeeAssignment}
            // model events so SQLite/MySQL both behave the same without driver-specific CHECK syntax here.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_assignments');
    }
};
