<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizationHoliday extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'holiday_type_id',
        'name',
        'start_date',
        'end_date',
        'notes',
        'recurrence',
        'set_by_user_id',
        'last_edited_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'recurrence' => 'array',
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
     * @return BelongsTo<HolidayType, $this>
     */
    public function holidayType(): BelongsTo
    {
        return $this->belongsTo(HolidayType::class);
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
