<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('team_calendar_events', function (Blueprint $table) {
            $table->dropForeign(['root_unit_id']);
            $table->dropForeign(['unit_id']);

            $table->unsignedBigInteger('root_unit_id')->nullable()->change();
            $table->unsignedBigInteger('unit_id')->nullable()->change();

            $table->foreign('root_unit_id')->references('id')->on('organizational_units')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('organizational_units')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('team_calendar_events')
            ->whereNull('root_unit_id')
            ->orWhereNull('unit_id')
            ->delete();

        Schema::table('team_calendar_events', function (Blueprint $table) {
            $table->dropForeign(['root_unit_id']);
            $table->dropForeign(['unit_id']);

            $table->unsignedBigInteger('root_unit_id')->nullable(false)->change();
            $table->unsignedBigInteger('unit_id')->nullable(false)->change();

            $table->foreign('root_unit_id')->references('id')->on('organizational_units')->cascadeOnDelete();
            $table->foreign('unit_id')->references('id')->on('organizational_units')->cascadeOnDelete();
        });
    }
};
