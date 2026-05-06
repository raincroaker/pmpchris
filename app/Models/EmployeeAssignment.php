<?php

namespace App\Models;

use Database\Factories\EmployeeAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * Places an employee in either an {@see Organization} (whole-org assignment) or an {@see OrganizationalUnit} (unit assignment), not both.
 *
 * XOR invariant (enforced by DB check on `employee_assignments`): exactly one of `organization_id` or `organizational_unit_id` is non-null.
 * Org-chart indexing in {@see OrganizationChartDataService} is unit-based today; rows with only `organization_id` are excluded from unit nodes until explicitly supported.
 *
 * XOR is validated on save via {@see EmployeeAssignment::assertOrganizationOrUnitXor}.
 */
class EmployeeAssignment extends Model
{
    /** @use HasFactory<EmployeeAssignmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'employee_employment_id',
        'organization_id',
        'organizational_unit_id',
        'is_primary',
        'is_head',
        'start_date',
        'end_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_head' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (EmployeeAssignment $assignment): void {
            self::assertOrganizationOrUnitXor($assignment);
            self::assertEmploymentBelongsToEmployee($assignment);
        });
    }

    /**
     * Exactly one of organization_id or organizational_unit_id must be set.
     */
    private static function assertOrganizationOrUnitXor(EmployeeAssignment $assignment): void
    {
        $hasOrganization = $assignment->organization_id !== null;
        $hasUnit = $assignment->organizational_unit_id !== null;

        if ($hasOrganization === $hasUnit) {
            throw new InvalidArgumentException(
                'Employee assignment requires exactly one of organization_id (org-level) or organizational_unit_id (unit-level).'
            );
        }
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<EmployeeEmployment, $this>
     */
    public function employmentPeriod(): BelongsTo
    {
        return $this->belongsTo(EmployeeEmployment::class, 'employee_employment_id');
    }

    /**
     * Set when this is an org-level assignment (no unit); null for unit-level rows.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Set for unit-level assignments; null when {@see EmployeeAssignment::$organization_id} is used instead.
     *
     * @return BelongsTo<OrganizationalUnit, $this>
     */
    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /**
     * @return HasMany<AssignmentPosition, $this>
     */
    public function assignmentPositions(): HasMany
    {
        return $this->hasMany(AssignmentPosition::class);
    }

    private static function assertEmploymentBelongsToEmployee(EmployeeAssignment $assignment): void
    {
        if ($assignment->employee_id === null || $assignment->employee_employment_id === null) {
            throw new InvalidArgumentException('employee_id and employee_employment_id are required.');
        }

        $employment = EmployeeEmployment::query()
            ->whereKey($assignment->employee_employment_id)
            ->first(['id', 'employee_id']);

        if ($employment === null) {
            throw new InvalidArgumentException('employee_employment_id must reference an existing employment period.');
        }

        if ((int) $employment->employee_id !== (int) $assignment->employee_id) {
            throw new InvalidArgumentException('employee_employment_id must belong to the same employee_id.');
        }
    }
}
