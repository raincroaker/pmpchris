<?php

namespace App\Models;

use App\Enums\EmployeeHrRecordStatus;
use App\Models\Concerns\SoftDeletesWithDeletedByUser;
use Database\Factories\EmployeeLeaveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeLeave extends Model
{
    /** @use HasFactory<EmployeeLeaveFactory> */
    use HasFactory;

    use SoftDeletesWithDeletedByUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'employee_id',
        'organizational_unit_id',
        'leave_policy_id',
        'start_date',
        'end_date',
        'is_half_day_start',
        'is_half_day_end',
        'status',
        'submitted_at',
        'decided_at',
        'approver_employee_id',
        'reason',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_half_day_start' => 'boolean',
            'is_half_day_end' => 'boolean',
            'status' => EmployeeHrRecordStatus::class,
            'submitted_at' => 'date',
            'decided_at' => 'date',
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
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * @return BelongsTo<OrganizationalUnit, $this>
     */
    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /**
     * @return BelongsTo<LeavePolicy, $this>
     */
    public function leavePolicy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class);
    }

    /**
     * @return HasMany<EmployeeLeaveDay, $this>
     */
    public function leaveDays(): HasMany
    {
        return $this->hasMany(EmployeeLeaveDay::class, 'employee_leave_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id');
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
