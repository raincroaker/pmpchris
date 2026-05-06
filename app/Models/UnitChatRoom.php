<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitChatRoom extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organizational_unit_id',
        'last_message_id',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OrganizationalUnit, $this>
     */
    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    /**
     * @return BelongsTo<UnitChatMessage, $this>
     */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(UnitChatMessage::class, 'last_message_id');
    }

    /**
     * @return HasMany<UnitChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(UnitChatMessage::class);
    }

    /**
     * @return HasMany<UnitChatRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(UnitChatRead::class);
    }
}
