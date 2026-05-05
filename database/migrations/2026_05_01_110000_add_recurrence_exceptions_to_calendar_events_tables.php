<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_calendar_events', function (Blueprint $table): void {
            $table->json('recurrence_exceptions')->nullable()->after('recurrence');
        });

        Schema::table('branch_calendar_events', function (Blueprint $table): void {
            $table->json('recurrence_exceptions')->nullable()->after('recurrence');
        });

        Schema::table('team_calendar_events', function (Blueprint $table): void {
            $table->json('recurrence_exceptions')->nullable()->after('recurrence');
        });
    }

    public function down(): void
    {
        Schema::table('company_calendar_events', function (Blueprint $table): void {
            $table->dropColumn('recurrence_exceptions');
        });

        Schema::table('branch_calendar_events', function (Blueprint $table): void {
            $table->dropColumn('recurrence_exceptions');
        });

        Schema::table('team_calendar_events', function (Blueprint $table): void {
            $table->dropColumn('recurrence_exceptions');
        });
    }
};
