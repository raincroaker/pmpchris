<?php

namespace App\Models;

use Database\Factories\TeamCalendarEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamCalendarEvent extends Model
{
    /** @use HasFactory<TeamCalendarEventFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'root_unit_id',
        'unit_id',
        'category_id',
        'title',
        'starts_at',
        'ends_at',
        'is_all_day',
        'location',
        'details',
        'recurrence',
        'recurrence_exceptions',
        'set_by_user_id',
        'last_edited_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
            'recurrence' => 'array',
            'recurrence_exceptions' => 'array',
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
     * @return BelongsTo<OrganizationalUnit, $this>
     */
    public function rootUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'root_unit_id');
    }

    /**
     * @return BelongsTo<OrganizationalUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'unit_id');
    }

    /**
     * @return BelongsTo<CalendarEventCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CalendarEventCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function setByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastEditedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by_user_id');
    }
}
