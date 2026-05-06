<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'code',
        'name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            if (blank($organization->uuid)) {
                $organization->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return HasMany<Area, $this>
     */
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    /**
     * @return HasMany<OrganizationalUnit, $this>
     */
    public function organizationalUnits(): HasMany
    {
        return $this->hasMany(OrganizationalUnit::class);
    }

    /**
     * Employee affiliations scoped to this organization (always has organization_id; root unit optional).
     *
     * @return HasMany<EmployeeAffiliation, $this>
     */
    public function employeeAffiliations(): HasMany
    {
        return $this->hasMany(EmployeeAffiliation::class);
    }

    /**
     * Employee assignments at organization scope only (XOR: these rows have organizational_unit_id null).
     * Unit-level assignments for units under this org are on {@see OrganizationalUnit::employeeAssignments()}.
     *
     * @return HasMany<EmployeeAssignment, $this>
     */
    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    /**
     * Shared categories used by company, branch, and team calendars.
     *
     * @return HasMany<CalendarEventCategory, $this>
     */
    public function calendarEventCategories(): HasMany
    {
        return $this->hasMany(CalendarEventCategory::class);
    }

    /**
     * @return HasMany<CompanyCalendarEvent, $this>
     */
    public function companyCalendarEvents(): HasMany
    {
        return $this->hasMany(CompanyCalendarEvent::class);
    }

    /**
     * @return HasMany<BranchCalendarEvent, $this>
     */
    public function branchCalendarEvents(): HasMany
    {
        return $this->hasMany(BranchCalendarEvent::class);
    }

    /**
     * @return HasMany<TeamCalendarEvent, $this>
     */
    public function teamCalendarEvents(): HasMany
    {
        return $this->hasMany(TeamCalendarEvent::class);
    }

    /**
     * Holiday types for the organization holiday calendar (pay labels, colors).
     *
     * @return HasMany<HolidayType, $this>
     */
    public function holidayTypes(): HasMany
    {
        return $this->hasMany(HolidayType::class);
    }

    /**
     * Organization-wide holiday entries (no branch scope).
     *
     * @return HasMany<OrganizationHoliday, $this>
     */
    public function organizationHolidays(): HasMany
    {
        return $this->hasMany(OrganizationHoliday::class);
    }

    /**
     * Definable work schedule templates (attendance expectations) for the organization.
     *
     * @return HasMany<WorkScheduleTemplate, $this>
     */
    public function workScheduleTemplates(): HasMany
    {
        return $this->hasMany(WorkScheduleTemplate::class);
    }

    /**
     * @return HasMany<LeavePolicy, $this>
     */
    public function leavePolicies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }

    /**
     * @return HasMany<OvertimePolicy, $this>
     */
    public function overtimePolicies(): HasMany
    {
        return $this->hasMany(OvertimePolicy::class);
    }

    /**
     * @return HasMany<EmployeeLeave, $this>
     */
    public function employeeLeaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class);
    }

    /**
     * @return HasMany<EmployeeOvertime, $this>
     */
    public function employeeOvertimes(): HasMany
    {
        return $this->hasMany(EmployeeOvertime::class);
    }

    /**
     * @return HasMany<EmployeeAttendanceDay, $this>
     */
    public function employeeAttendanceDays(): HasMany
    {
        return $this->hasMany(EmployeeAttendanceDay::class);
    }
}
