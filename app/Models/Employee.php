<?php

namespace App\Models;

use App\Enums\EmployeeBirthdayVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id_number',
        'attendance_id',
        'work_schedule_template_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'birthdate',
        'birthday_visibility',
        'sex',
        'civil_status',
        'nationality',
        'religion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'birthday_visibility' => EmployeeBirthdayVisibility::class,
            'work_schedule_template_id' => 'integer',
        ];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return BelongsTo<WorkScheduleTemplate, $this>
     */
    public function workScheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkScheduleTemplate::class);
    }

    /**
     * @return HasMany<EmployeeContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(EmployeeContact::class);
    }

    /**
     * @return HasMany<EmployeeAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(EmployeeAddress::class);
    }

    /**
     * @return HasMany<EmployeeAffiliation, $this>
     */
    public function affiliations(): HasMany
    {
        return $this->hasMany(EmployeeAffiliation::class);
    }

    /**
     * @return HasMany<EmployeeAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    /**
     * @return HasMany<EmployeePosition, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }

    /**
     * @return HasMany<EmployeeEmployment, $this>
     */
    public function employments(): HasMany
    {
        return $this->hasMany(EmployeeEmployment::class);
    }

    /**
     * @return HasOne<EmployeeEmployment, $this>
     */
    public function currentEmployment(): HasOne
    {
        return $this->hasOne(EmployeeEmployment::class)->where('is_current', true);
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
