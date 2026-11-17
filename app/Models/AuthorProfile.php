<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Public byline for a staff member who writes blog posts (REQ §4 Author Management).
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $display_name
 * @property string|null $job_title
 * @property string|null $bio
 * @property int|null $avatar_id
 * @property-read User $user
 * @property-read Media|null $avatar
 */
#[Fillable(['user_id', 'display_name', 'job_title', 'bio', 'avatar_id'])]
class AuthorProfile extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Media, $this> */
    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'avatar_id');
    }
}
