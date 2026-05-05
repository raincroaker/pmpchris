<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR-entered leave records (join to {@see LeavePolicy}; no policy snapshot columns).
     */
    public function up(): void
    {
        Schema::create('employee_leaves', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units')->nullOnDelete();
            $table->foreignId('leave_policy_id')->constrained('leave_policies')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_half_day_start')->default(false);
            $table->boolean('is_half_day_end')->default(false);
            $table->string('status', 20);
            $table->date('submitted_at');
            $table->date('decided_at')->nullable();
            $table->foreignId('approver_employee_id')->constrained('employees')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'submitted_at']);
            $table->index(['employee_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leaves');
    }
};
