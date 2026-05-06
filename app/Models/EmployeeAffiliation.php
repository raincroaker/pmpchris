<?php

namespace App\Models;

use Database\Factories\EmployeeAffiliationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

/**
 * Links an employee to an {@see Organization} (required) and optionally to a root {@see OrganizationalUnit}.
 *
 * - {@see EmployeeAffiliation::$organization_id} is always set: the org this affiliation belongs to.
 * - When {@see EmployeeAffiliation::$root_unit_id} is null, the affiliation is org-wide (no specific root branch).
 * - When {@see EmployeeAffiliation::$root_unit_id} is set, it should be a root-capable unit; its {@see OrganizationalUnit::$organization_id}
 *   must match this row's organization_id (enforced on save via {@see EmployeeAffiliation::booted} and should be mirrored in Form Requests).
 *
 * Root unit type rules (e.g. {@see UnitType::$can_be_root}) remain application-level validation.
 */
class EmployeeAffiliation extends Model
{
    /** @use HasFactory<EmployeeAffiliationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'employee_employment_id',
        'organization_id',
        'root_unit_id',
        'is_primary',
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
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmployeeAffiliation $affiliation): void {
            self::assertEmploymentBelongsToEmployee($affiliation);

            if ($affiliation->root_unit_id !== null) {
                $unit = OrganizationalUnit::query()->find($affiliation->root_unit_id);
                if ($unit === null) {
                    throw new InvalidArgumentException('root_unit_id must reference an existing organizational unit.');
                }
                $affiliation->organization_id = $unit->organization_id;

                return;
            }

            if ($affiliation->organization_id === null) {
                throw new InvalidArgumentException('organization_id is required when root_unit_id is null.');
            }
        });

        static::updating(function (EmployeeAffiliation $affiliation): void {
            self::assertEmploymentBelongsToEmployee($affiliation);

            if ($affiliation->root_unit_id !== null) {
                $unit = OrganizationalUnit::query()->find($affiliation->root_unit_id);
                if ($unit === null) {
                    throw new InvalidArgumentException('root_unit_id must reference an existing organizational unit.');
                }
                $affiliation->organization_id = $unit->organization_id;
            }
        });
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
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Root branch / HO unit when set; null for org-wide affiliation only.
     *
     * @return BelongsTo<OrganizationalUnit, $this>
     */
    public function rootUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'root_unit_id');
    }

    private static function assertEmploymentBelongsToEmployee(EmployeeAffiliation $affiliation): void
    {
        if ($affiliation->employee_id === null || $affiliation->employee_employment_id === null) {
            throw new InvalidArgumentException('employee_id and employee_employment_id are required.');
        }

        $employment = EmployeeEmployment::query()
            ->whereKey($affiliation->employee_employment_id)
            ->first(['id', 'employee_id']);

        if ($employment === null) {
            throw new InvalidArgumentException('employee_employment_id must reference an existing employment period.');
        }

        if ((int) $employment->employee_id !== (int) $affiliation->employee_id) {
            throw new InvalidArgumentException('employee_employment_id must belong to the same employee_id.');
        }
    }
}
