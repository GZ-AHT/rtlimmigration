<?php

namespace App\Models;

use App\Enums\CommentStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reader's comment, shown only once approved (REQ §4 Blog Comments).
 *
 * @property int $id
 * @property int $blog_post_id
 * @property int|null $parent_id
 * @property int|null $user_id
 * @property string $name
 * @property string $email
 * @property string $body
 * @property CommentStatus $status
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonInterface|null $created_at
 * @property-read BlogPost $post
 */
#[Fillable(['blog_post_id', 'parent_id', 'user_id', 'name', 'email', 'body', 'status', 'ip_address', 'user_agent'])]
class BlogComment extends Model
{
    protected function casts(): array
    {
        return ['status' => CommentStatus::class];
    }

    /** @return BelongsTo<BlogPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    /** @return HasMany<BlogComment, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(BlogComment::class, 'parent_id');
    }
}
