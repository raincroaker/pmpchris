<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR-entered overtime records (join to {@see OvertimePolicy}; no policy snapshot columns).
     */
    public function up(): void
    {
        Schema::create('employee_overtimes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units')->nullOnDelete();
            $table->foreignId('overtime_policy_id')->constrained('overtime_policies')->restrictOnDelete();
            $table->date('ot_date');
            $table->decimal('hours', 8, 2);
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
            $table->index(['employee_id', 'ot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_overtimes');
    }
};
