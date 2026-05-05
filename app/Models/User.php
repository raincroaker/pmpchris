<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'avatar_path',
        'password',
        'employee_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * @return HasMany<BranchManager, $this>
     */
    public function branchManagerAssignments(): HasMany
    {
        return $this->hasMany(BranchManager::class);
    }

    /**
     * @return BelongsToMany<OrganizationalUnit, $this>
     */
    public function managedBranchRoots(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganizationalUnit::class,
            'branch_managers',
            'user_id',
            'root_unit_id',
        )
            ->withPivot(['is_active', 'starts_at', 'ends_at', 'assigned_by_user_id', 'notes'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<CalendarEventCategory, $this>
     */
    public function createdCalendarEventCategories(): HasMany
    {
        return $this->hasMany(CalendarEventCategory::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<CalendarEventCategory, $this>
     */
    public function updatedCalendarEventCategories(): HasMany
    {
        return $this->hasMany(CalendarEventCategory::class, 'updated_by_user_id');
    }

    /**
     * @return HasMany<CompanyCalendarEvent, $this>
     */
    public function setCompanyCalendarEvents(): HasMany
    {
        return $this->hasMany(CompanyCalendarEvent::class, 'set_by_user_id');
    }

    /**
     * @return HasMany<CompanyCalendarEvent, $this>
     */
    public function editedCompanyCalendarEvents(): HasMany
    {
        return $this->hasMany(CompanyCalendarEvent::class, 'last_edited_by_user_id');
    }

    /**
     * @return HasMany<BranchCalendarEvent, $this>
     */
    public function setBranchCalendarEvents(): HasMany
    {
        return $this->hasMany(BranchCalendarEvent::class, 'set_by_user_id');
    }

    /**
     * @return HasMany<BranchCalendarEvent, $this>
     */
    public function editedBranchCalendarEvents(): HasMany
    {
        return $this->hasMany(BranchCalendarEvent::class, 'last_edited_by_user_id');
    }

    /**
     * @return HasMany<TeamCalendarEvent, $this>
     */
    public function setTeamCalendarEvents(): HasMany
    {
        return $this->hasMany(TeamCalendarEvent::class, 'set_by_user_id');
    }

    /**
     * @return HasMany<TeamCalendarEvent, $this>
     */
    public function editedTeamCalendarEvents(): HasMany
    {
        return $this->hasMany(TeamCalendarEvent::class, 'last_edited_by_user_id');
    }

    public function hasRole(string $code): bool
    {
        return $this->roles()->where('code', $code)->exists();
    }

    /**
     * @param  list<string>  $codes
     */
    public function hasAnyRole(array $codes): bool
    {
        return $this->roles()->whereIn('code', $codes)->exists();
    }

    /**
     * Organization Chart → Edit Structure (unit-type catalog, org header, future mutations).
     */
    public function canEditOrganizationStructure(): bool
    {
        return $this->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]);
    }

    public function mustSelectBranch(): bool
    {
        if (! config('hris.branch_picker_enabled', true)) {
            return false;
        }

        /** @var list<string> $codes */
        $codes = config('hris.picker_role_codes', []);

        return $this->hasAnyRole($codes);
    }

    public function assignRole(Role|string $role): void
    {
        $model = $role instanceof Role
            ? $role
            : Role::query()->where('code', $role)->firstOrFail();

        $this->roles()->syncWithoutDetaching([$model->id]);
    }

    /**
     * @param  list<string>  $codes
     */
    public function syncRolesByCode(array $codes): void
    {
        $ids = Role::query()
            ->whereIn('code', $codes, 'and', false)
            ->orderBy('id', 'asc')
            ->pluck('id')
            ->all();
        $this->roles()->sync($ids);
    }
}
