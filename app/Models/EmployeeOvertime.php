<?php

namespace App\Models;

use App\Enums\EmployeeHrRecordStatus;
use App\Models\Concerns\SoftDeletesWithDeletedByUser;
use Database\Factories\EmployeeOvertimeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeOvertime extends Model
{
    /** @use HasFactory<EmployeeOvertimeFactory> */
    use HasFactory;

    use SoftDeletesWithDeletedByUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'employee_id',
        'organizational_unit_id',
        'overtime_policy_id',
        'ot_date',
        'hours',
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
            'ot_date' => 'date',
            'hours' => 'decimal:2',
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
     * @return BelongsTo<OvertimePolicy, $this>
     */
    public function overtimePolicy(): BelongsTo
    {
        return $this->belongsTo(OvertimePolicy::class);
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
