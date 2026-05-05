<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_schedule_templates', function (Blueprint $table): void {
            $table->json('attendance_rules')->nullable()->after('notes');
            $table->json('overtime_rules')->nullable()->after('attendance_rules');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedule_templates', function (Blueprint $table): void {
            $table->dropColumn(['attendance_rules', 'overtime_rules']);
        });
    }
};
