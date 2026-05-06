<?php

namespace App\Models;

use App\Enums\OvertimePolicyContext;
use App\Models\Concerns\SoftDeletesWithDeletedByUser;
use Database\Factories\OvertimePolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimePolicy extends Model
{
    /** @use HasFactory<OvertimePolicyFactory> */
    use HasFactory;

    use SoftDeletesWithDeletedByUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'context',
        'rate_multiplier',
        'daily_threshold_hours',
        'daily_cap_hours',
        'weekly_cap_hours',
        'requires_approval',
        'minimum_lead_time_hours',
        'is_active',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => OvertimePolicyContext::class,
            'rate_multiplier' => 'decimal:4',
            'daily_threshold_hours' => 'decimal:4',
            'daily_cap_hours' => 'decimal:4',
            'weekly_cap_hours' => 'decimal:4',
            'requires_approval' => 'boolean',
            'minimum_lead_time_hours' => 'decimal:4',
            'is_active' => 'boolean',
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
     * @return BelongsTo<User, $this>
     */
    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    /**
     * @return HasMany<EmployeeOvertime, $this>
     */
    public function employeeOvertimes(): HasMany
    {
        return $this->hasMany(EmployeeOvertime::class);
    }

    /**
     * Shape consumed by [`Overtime/Policies.vue`](resources/js/pages/Overtime/Policies.vue) (`OvertimePolicy` type).
     *
     * @return array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     context: string,
     *     rateMultiplier: float,
     *     dailyThresholdHours: float,
     *     dailyCapHours: float|null,
     *     weeklyCapHours: float|null,
     *     requiresApproval: bool,
     *     minimumLeadTimeHours: float|null,
     *     isActive: bool,
     *     notes: string|null
     * }
     */
    public function toPolicyPageProps(): array
    {
        return [
            'id' => (int) $this->id,
            'code' => (string) $this->code,
            'name' => (string) $this->name,
            'context' => $this->context->value,
            'rateMultiplier' => (float) $this->rate_multiplier,
            'dailyThresholdHours' => (float) $this->daily_threshold_hours,
            'dailyCapHours' => $this->daily_cap_hours !== null ? (float) $this->daily_cap_hours : null,
            'weeklyCapHours' => $this->weekly_cap_hours !== null ? (float) $this->weekly_cap_hours : null,
            'requiresApproval' => (bool) $this->requires_approval,
            'minimumLeadTimeHours' => $this->minimum_lead_time_hours !== null ? (float) $this->minimum_lead_time_hours : null,
            'isActive' => (bool) $this->is_active,
            'notes' => $this->notes !== null && $this->notes !== '' ? (string) $this->notes : null,
        ];
    }
}
