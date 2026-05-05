<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('employee_employments')) {
            return;
        }

        DB::table('employee_employments')
            ->where('employment_status', 'terminated')
            ->whereNotNull('separation_reason')
            ->whereRaw('LOWER(separation_reason) LIKE ?', ['%contract ended%'])
            ->update([
                'employment_status' => 'contract_ended',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('employee_employments')) {
            return;
        }

        DB::table('employee_employments')
            ->where('employment_status', 'contract_ended')
            ->update([
                'employment_status' => 'terminated',
                'updated_at' => now(),
            ]);
    }
};
