<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * How far one user has read a conversation, for unread counts.
 *
 * @property int $id
 * @property int $chat_conversation_id
 * @property int $user_id
 * @property int $last_read_message_id
 */
#[Fillable(['chat_conversation_id', 'user_id', 'last_read_message_id'])]
class ChatRead extends Model
{
    protected function casts(): array
    {
        return ['last_read_message_id' => 'integer'];
    }
}
