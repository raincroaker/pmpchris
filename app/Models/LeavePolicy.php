<?php

namespace App\Models;

use App\Enums\LeavePolicyAccrualCadence;
use App\Enums\LeavePolicyUnit;
use App\Models\Concerns\SoftDeletesWithDeletedByUser;
use Database\Factories\LeavePolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeavePolicy extends Model
{
    /** @use HasFactory<LeavePolicyFactory> */
    use HasFactory;

    use SoftDeletesWithDeletedByUser;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'code',
        'name',
        'unit',
        'annual_entitlement',
        'use_accrual',
        'accrual_cadence',
        'accrual_per_period',
        'max_balance',
        'carryover_allowed',
        'carryover_cap',
        'paid',
        'requires_approval',
        'applies_after_months',
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
            'unit' => LeavePolicyUnit::class,
            'annual_entitlement' => 'decimal:4',
            'use_accrual' => 'boolean',
            'accrual_cadence' => LeavePolicyAccrualCadence::class,
            'accrual_per_period' => 'decimal:4',
            'max_balance' => 'decimal:4',
            'carryover_allowed' => 'boolean',
            'carryover_cap' => 'decimal:4',
            'paid' => 'boolean',
            'requires_approval' => 'boolean',
            'applies_after_months' => 'integer',
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
     * @return HasMany<EmployeeLeave, $this>
     */
    public function employeeLeaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class);
    }

    /**
     * Shape consumed by [`Leave/Policies.vue`](resources/js/pages/Leave/Policies.vue) (`LeavePolicy` type).
     *
     * @return array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     unit: string,
     *     annualEntitlement: float,
     *     useAccrual: bool,
     *     accrualCadence: string|null,
     *     accrualPerPeriod: float|null,
     *     maxBalance: float|null,
     *     carryoverAllowed: bool,
     *     carryoverCap: float|null,
     *     paid: bool,
     *     requiresApproval: bool,
     *     appliesAfterMonths: int|null,
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
            'unit' => $this->unit->value,
            'annualEntitlement' => (float) $this->annual_entitlement,
            'useAccrual' => (bool) $this->use_accrual,
            'accrualCadence' => $this->accrual_cadence?->value,
            'accrualPerPeriod' => $this->accrual_per_period !== null ? (float) $this->accrual_per_period : null,
            'maxBalance' => $this->max_balance !== null ? (float) $this->max_balance : null,
            'carryoverAllowed' => (bool) $this->carryover_allowed,
            'carryoverCap' => $this->carryover_cap !== null ? (float) $this->carryover_cap : null,
            'paid' => (bool) $this->paid,
            'requiresApproval' => (bool) $this->requires_approval,
            'appliesAfterMonths' => $this->applies_after_months,
            'isActive' => (bool) $this->is_active,
            'notes' => $this->notes !== null && $this->notes !== '' ? (string) $this->notes : null,
        ];
    }
}
