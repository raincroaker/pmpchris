<?php

use App\Enums\EmployeeBirthdayVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align DB default with {@see EmployeeBirthdayVisibility::Team} for databases that already ran the initial column migration with default `private`.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'birthday_visibility')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'mysql', 'mariadb' => DB::statement(
                "ALTER TABLE employees MODIFY birthday_visibility VARCHAR(32) NOT NULL DEFAULT 'team'"
            ),
            'pgsql' => DB::statement(
                "ALTER TABLE employees ALTER COLUMN birthday_visibility SET DEFAULT 'team'"
            ),
            default => null,
        };
    }

    public function down(): void
    {
        if (! Schema::hasColumn('employees', 'birthday_visibility')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'mysql', 'mariadb' => DB::statement(
                "ALTER TABLE employees MODIFY birthday_visibility VARCHAR(32) NOT NULL DEFAULT 'private'"
            ),
            'pgsql' => DB::statement(
                "ALTER TABLE employees ALTER COLUMN birthday_visibility SET DEFAULT 'private'"
            ),
            default => null,
        };
    }
};
