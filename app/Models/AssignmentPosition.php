<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AssignmentPosition extends Model
{
    /** @use HasFactory<\Database\Factories\AssignmentPositionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'employee_assignment_id',
        'employee_position_id',
        'is_primary_for_assignment',
        'start_date',
        'end_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary_for_assignment' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AssignmentPosition $row): void {
            if (blank($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<EmployeeAssignment, $this>
     */
    public function employeeAssignment(): BelongsTo
    {
        return $this->belongsTo(EmployeeAssignment::class);
    }

    /**
     * @return BelongsTo<EmployeePosition, $this>
     */
    public function employeePosition(): BelongsTo
    {
        return $this->belongsTo(EmployeePosition::class);
    }
}
