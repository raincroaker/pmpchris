<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One calendar work day of attendance per employee, with the work schedule template
     * captured at insert time (not derived from the employee's current assignment).
     */
    public function up(): void
    {
        Schema::create('employee_attendance_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('organizational_unit_id')->nullable()->constrained('organizational_units')->nullOnDelete();
            $table->date('work_date');
            $table->foreignId('work_schedule_template_id')->constrained('work_schedule_templates')->restrictOnDelete();
            /** Snapshot of template clock pattern at insert (mirrors {@see WorkScheduleClockPattern}). */
            $table->string('clock_pattern', 32);
            /** Snapshot of template overnight flag at insert (Team Attendance filter parity). */
            $table->boolean('is_overnight_schedule')->default(false);
            /**
             * Optional stable key from device/import pipeline (distinct from `employees.attendance_id`).
             */
            $table->string('ingest_key', 80)->nullable();
            /** How the row was first created (immutable in app; not enforced in DB). */
            $table->string('original_entry_source', 20);
            /** How the row was last persisted (manual correction, ingest, import, etc.). */
            $table->string('last_modified_source', 20);
            $table->string('status', 20);
            $table->string('punctuality', 24)->nullable();
            $table->decimal('net_hours', 8, 2)->nullable();
            $table->string('variance_label', 128)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'work_date']);
            $table->unique(['organization_id', 'ingest_key']);
            $table->index(['organization_id', 'work_date']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_days');
    }
};
