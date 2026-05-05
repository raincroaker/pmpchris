<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employee_positions', function (Blueprint $table) {
            $table->foreignId('employee_employment_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('employee_employments')
                ->cascadeOnDelete();
            $table->index(['employee_employment_id', 'start_date'], 'emp_pos_emp_employment_start_idx');
        });

        $now = now();
        $fallbackEmploymentIdsByEmployee = [];

        $positions = DB::table('employee_positions')
            ->select(['id', 'employee_id', 'start_date'])
            ->whereNull('employee_employment_id')
            ->orderBy('id')
            ->get();

        foreach ($positions as $position) {
            $employeeId = (int) $position->employee_id;
            if ($employeeId <= 0) {
                continue;
            }

            $employment = DB::table('employee_employments')
                ->where('employee_id', $employeeId)
                ->orderByDesc('is_current')
                ->orderByDesc('hire_date')
                ->orderByDesc('id')
                ->first(['id']);

            if ($employment === null) {
                if (! isset($fallbackEmploymentIdsByEmployee[$employeeId])) {
                    $fallbackEmploymentIdsByEmployee[$employeeId] = DB::table('employee_employments')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'employee_id' => $employeeId,
                        'hire_date' => (string) ($position->start_date ?? $now->toDateString()),
                        'separation_date' => null,
                        'separation_reason' => null,
                        'employment_status' => 'active',
                        'is_current' => true,
                        'notes' => 'Auto-created while backfilling employee positions.',
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ]);
                }

                $employmentId = (int) $fallbackEmploymentIdsByEmployee[$employeeId];
            } else {
                $employmentId = (int) $employment->id;
            }

            DB::table('employee_positions')
                ->where('id', (int) $position->id)
                ->update([
                    'employee_employment_id' => $employmentId,
                    'updated_at' => $now,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_positions', function (Blueprint $table) {
            $table->dropIndex('emp_pos_emp_employment_start_idx');
            $table->dropConstrainedForeignId('employee_employment_id');
        });
    }
};
