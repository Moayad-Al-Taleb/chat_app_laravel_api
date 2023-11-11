<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $table = "chats";
    protected $guarded = ['id'];


    public function participants()
    {
        return $this->hasMany(ChatParticipant::class, 'chat_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'chat_id');
    }

    public function lastMessage()
    {
        return $this->hasOne(ChatMessage::class, 'chat_id')->latest('updated_at');
    }

    /**
     * Scope to filter chats based on whether they have a specific participant.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId The ID of the participant to check for.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHasParticipant($query, int $userId)
    {
        return $query->whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

}
