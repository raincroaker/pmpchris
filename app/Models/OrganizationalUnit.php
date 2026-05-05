<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OrganizationalUnit extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationalUnitFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'code',
        'name',
        'unit_type_id',
        'organization_id',
        'parent_id',
        'area_id',
        'address',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OrganizationalUnit $unit): void {
            if (blank($unit->uuid)) {
                $unit->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<UnitType, $this>
     */
    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    /**
     * @return BelongsTo<OrganizationalUnit, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'parent_id');
    }

    /**
     * @return HasMany<OrganizationalUnit, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(OrganizationalUnit::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @return HasMany<EmployeeAffiliation, $this>
     */
    public function employeeAffiliationsAsRoot(): HasMany
    {
        return $this->hasMany(EmployeeAffiliation::class, 'root_unit_id');
    }

    /**
     * @return HasMany<EmployeeAssignment, $this>
     */
    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class, 'organizational_unit_id');
    }

    /**
     * @return HasMany<BranchManager, $this>
     */
    public function branchManagerAssignments(): HasMany
    {
        return $this->hasMany(BranchManager::class, 'root_unit_id');
    }

    /**
     * Branch calendar events scoped to this root unit.
     *
     * @return HasMany<BranchCalendarEvent, $this>
     */
    public function branchCalendarEventsAsRoot(): HasMany
    {
        return $this->hasMany(BranchCalendarEvent::class, 'root_unit_id');
    }

    /**
     * Team calendar events scoped to this root unit context.
     *
     * @return HasMany<TeamCalendarEvent, $this>
     */
    public function teamCalendarEventsAsRoot(): HasMany
    {
        return $this->hasMany(TeamCalendarEvent::class, 'root_unit_id');
    }

    /**
     * Team calendar events scoped to this specific team unit.
     *
     * @return HasMany<TeamCalendarEvent, $this>
     */
    public function teamCalendarEventsAsUnit(): HasMany
    {
        return $this->hasMany(TeamCalendarEvent::class, 'unit_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'branch_managers',
            'root_unit_id',
            'user_id',
        )
            ->withPivot(['is_active', 'starts_at', 'ends_at', 'assigned_by_user_id', 'notes'])
            ->withTimestamps();
    }
}
