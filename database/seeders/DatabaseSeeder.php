<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(OrganizationalStructureSeeder::class);
        $this->call(RoleSeeder::class);

        if (app()->environment('local')) {
            $this->call(DemoCooperativeSeeder::class);
            $this->call(DevelopmentUserSeeder::class);
            $this->call(DevelopmentEmployeeEmploymentSeeder::class);
            $this->call(DevelopmentEmployeeProfileSeeder::class);
            $this->call(DevelopmentEmployeeAffiliationSeeder::class);
            $this->call(DevelopmentEmployeePositionSeeder::class);
            $this->call(DevelopmentBranchManagerSeeder::class);
            $this->call(CalendarEventsSeeder::class);
            $this->call(HolidayTypesSeeder::class);
            $this->call(OrganizationHolidaysSeeder::class);
            $this->call(WorkScheduleTemplatesSeeder::class);
            $this->call(DevelopmentEmployeeAttendanceProfileSeeder::class);
            $this->call(DevelopmentEmployeeAttendanceDaysSeeder::class);
            $this->call(LeaveAndOvertimePoliciesSeeder::class);
            $this->call(DevelopmentTeamHrMay2026LeaveOvertimeSeeder::class);
        }

        // User::factory(10)->create();
    }
}
