<?php

namespace App\Models;

use App\Enums\AttendanceEntrySource;
use App\Enums\AttendanceRecordStatus;
use App\Enums\WorkScheduleClockPattern;
use App\Models\Concerns\SoftDeletesWithDeletedByUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAttendanceDay extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeAttendanceDayFactory> */
    use HasFactory;

    use SoftDeletesWithDeletedByUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'employee_id',
        'organizational_unit_id',
        'work_date',
        'work_schedule_template_id',
        'clock_pattern',
        'is_overnight_schedule',
        'ingest_key',
        'original_entry_source',
        'last_modified_source',
        'status',
        'punctuality',
        'net_hours',
        'variance_label',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'clock_pattern' => WorkScheduleClockPattern::class,
            'is_overnight_schedule' => 'boolean',
            'original_entry_source' => AttendanceEntrySource::class,
            'last_modified_source' => AttendanceEntrySource::class,
            'status' => AttendanceRecordStatus::class,
            'net_hours' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<OrganizationalUnit, $this>
     */
    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /**
     * Template governing scheduled times for this row (use {@see WorkScheduleTemplate::withTrashed()} when displaying retired templates).
     *
     * @return BelongsTo<WorkScheduleTemplate, $this>
     */
    public function workScheduleTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkScheduleTemplate::class);
    }

    /**
     * @return HasMany<EmployeeAttendanceSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(EmployeeAttendanceSegment::class)->orderBy('segment_index');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }
}
