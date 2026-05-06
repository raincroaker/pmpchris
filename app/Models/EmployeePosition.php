<?php

namespace App\Models;

use Database\Factories\EmployeePositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InvalidArgumentException;

class EmployeePosition extends Model
{
    /** @use HasFactory<EmployeePositionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'employee_id',
        'employee_employment_id',
        'position_id',
        'is_primary',
        'start_date',
        'end_date',
        'notes',
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
        static::saving(function (EmployeePosition $row): void {
            if (blank($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }

            self::assertEmploymentBelongsToEmployee($row);
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
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return HasMany<AssignmentPosition, $this>
     */
    public function assignmentPositions(): HasMany
    {
        return $this->hasMany(AssignmentPosition::class);
    }

    private static function assertEmploymentBelongsToEmployee(EmployeePosition $position): void
    {
        if ($position->employee_id === null || $position->employee_employment_id === null) {
            throw new InvalidArgumentException('employee_id and employee_employment_id are required.');
        }

        $employment = EmployeeEmployment::query()
            ->whereKey($position->employee_employment_id)
            ->first(['id', 'employee_id']);

        if ($employment === null) {
            throw new InvalidArgumentException('employee_employment_id must reference an existing employment period.');
        }

        if ((int) $employment->employee_id !== (int) $position->employee_id) {
            throw new InvalidArgumentException('employee_employment_id must belong to the same employee_id.');
        }
    }
}
