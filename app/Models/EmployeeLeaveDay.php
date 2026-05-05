<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeaveDay extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'employee_id',
        'employee_leave_id',
        'leave_date',
        'is_half_day',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'leave_date' => 'date',
            'is_half_day' => 'boolean',
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
     * @return BelongsTo<EmployeeLeave, $this>
     */
    public function employeeLeave(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeave::class, 'employee_leave_id');
    }
}
