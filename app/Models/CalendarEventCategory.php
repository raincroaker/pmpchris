<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarEventCategory extends Model
{
    /** @use HasFactory<\Database\Factories\CalendarEventCategoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'color_key',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
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
     * @return HasMany<CompanyCalendarEvent, $this>
     */
    public function companyCalendarEvents(): HasMany
    {
        return $this->hasMany(CompanyCalendarEvent::class, 'category_id');
    }

    /**
     * @return HasMany<BranchCalendarEvent, $this>
     */
    public function branchCalendarEvents(): HasMany
    {
        return $this->hasMany(BranchCalendarEvent::class, 'category_id');
    }

    /**
     * @return HasMany<TeamCalendarEvent, $this>
     */
    public function teamCalendarEvents(): HasMany
    {
        return $this->hasMany(TeamCalendarEvent::class, 'category_id');
    }
}
