<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitChatRead extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'unit_chat_room_id',
        'user_id',
        'last_read_message_id',
        'last_read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<UnitChatRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(UnitChatRoom::class, 'unit_chat_room_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<UnitChatMessage, $this>
     */
    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(UnitChatMessage::class, 'last_read_message_id');
    }
}
