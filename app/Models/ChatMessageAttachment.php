<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file sent with a portal message. Stored on the private documents disk
 * and only served through an authorised route.
 *
 * @property int $id
 * @property int $chat_message_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property-read ChatMessage $message
 */
#[Fillable(['chat_message_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class ChatMessageAttachment extends Model
{
    protected static function booted(): void
    {
        static::deleted(fn (self $a) => Storage::disk($a->disk)->delete($a->path));
    }

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<ChatMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}
