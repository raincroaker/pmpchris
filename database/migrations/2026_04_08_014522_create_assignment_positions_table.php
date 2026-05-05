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
        Schema::create('assignment_positions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('employee_assignment_id')->constrained('employee_assignments')->cascadeOnDelete();
            $table->foreignId('employee_position_id')->constrained('employee_positions')->cascadeOnDelete();
            $table->boolean('is_primary_for_assignment')->default(false);
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_assignment_id', 'start_date']);
            $table->index('employee_position_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_positions');
    }
};
