<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scheduled vs actual clock segments for an {@see EmployeeAttendanceDay} (single pair or split sessions).
     */
    public function up(): void
    {
        Schema::create('employee_attendance_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_attendance_day_id')->constrained('employee_attendance_days')->cascadeOnDelete();
            $table->unsignedTinyInteger('segment_index');
            $table->string('label', 80)->nullable();
            $table->char('scheduled_in', 5);
            $table->char('scheduled_out', 5);
            $table->char('actual_in', 5)->nullable();
            $table->char('actual_out', 5)->nullable();
            $table->timestamps();

            $table->unique(
                ['employee_attendance_day_id', 'segment_index'],
                'ea_attendance_segments_day_segment_uidx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_segments');
    }
};
