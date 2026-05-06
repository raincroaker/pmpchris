<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitChatMessage extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'unit_chat_room_id',
        'sender_user_id',
        'body',
    ];

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
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
