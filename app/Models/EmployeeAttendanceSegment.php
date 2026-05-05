<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAttendanceSegment extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeAttendanceSegmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_attendance_day_id',
        'segment_index',
        'label',
        'scheduled_in',
        'scheduled_out',
        'actual_in',
        'actual_out',
    ];

    /**
     * @return BelongsTo<EmployeeAttendanceDay, $this>
     */
    public function employeeAttendanceDay(): BelongsTo
    {
        return $this->belongsTo(EmployeeAttendanceDay::class);
    }
}
