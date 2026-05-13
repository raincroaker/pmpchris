<?php

namespace App\Models;

use App\Enums\WorkScheduleClockPattern;
use Database\Factories\WorkScheduleTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkScheduleTemplate extends Model
{
    /** @use HasFactory<WorkScheduleTemplateFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'clock_pattern',
        'days',
        'segments',
        'time_in',
        'time_out',
        'is_overnight',
        'is_active',
        'unpaid_break_minutes',
        'grace_late_arrival_minutes',
        'notes',
        'attendance_rules',
        'overtime_rules',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clock_pattern' => WorkScheduleClockPattern::class,
            'days' => 'array',
            'segments' => 'array',
            'is_overnight' => 'boolean',
            'is_active' => 'boolean',
            'unpaid_break_minutes' => 'integer',
            'grace_late_arrival_minutes' => 'integer',
            'attendance_rules' => 'array',
            'overtime_rules' => 'array',
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
     * Shape consumed by `Attendance/Shifts` (matches `ShiftRule` on the client).
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     days: list<string>,
     *     clock_pattern: string,
     *     segments: list<array{label: string, time_in: string, time_out: string, is_overnight: bool}>|null,
     *     time_in: string,
     *     time_out: string,
     *     is_overnight: bool,
     *     is_active: bool,
     *     unpaid_break_minutes: int,
     *     grace_late_arrival_minutes: int,
     *     notes: string|null,
     *     attendance_rules: array<string, mixed>|null,
     *     overtime_rules: array<string, mixed>|null
     * }
     */
    public function toShiftRuleArray(): array
    {
        $segments = $this->segments;

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'days' => array_values($this->days ?? []),
            'clock_pattern' => $this->clock_pattern->value,
            'segments' => is_array($segments) ? $segments : null,
            'time_in' => $this->time_in,
            'time_out' => $this->time_out,
            'is_overnight' => $this->is_overnight,
            'is_active' => $this->is_active,
            'unpaid_break_minutes' => (int) $this->unpaid_break_minutes,
            'grace_late_arrival_minutes' => (int) $this->grace_late_arrival_minutes,
            'notes' => $this->notes,
            'attendance_rules' => $this->attendance_rules,
            'overtime_rules' => $this->overtime_rules,
        ];
    }
}
