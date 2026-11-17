<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Models\Concerns\HasSeo;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * REQ §4 blog post. Content is HTML cleaned by RichText::clean() on save.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $content
 * @property int|null $featured_image_id
 * @property int|null $blog_category_id
 * @property int|null $author_id
 * @property PublishStatus $status
 * @property CarbonInterface|null $published_at
 * @property bool $is_featured
 * @property bool $allow_comments
 * @property int $views
 * @property int $reading_minutes
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Media|null $featuredImage
 * @property-read BlogCategory|null $category
 * @property-read User|null $author
 * @property-read Collection<int, BlogTag> $tags
 */
#[Fillable(['title', 'slug', 'excerpt', 'content', 'featured_image_id', 'blog_category_id', 'author_id', 'status', 'published_at', 'is_featured', 'allow_comments', 'reading_minutes'])]
class BlogPost extends Model
{
    use HasSeo, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'allow_comments' => 'boolean',
            'views' => 'integer',
            'reading_minutes' => 'integer',
        ];
    }

    /**
     * Live on the website: published and its date has come.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', PublishStatus::Published)->where('published_at', '<=', now());
    }

    public function isLive(): bool
    {
        return $this->status === PublishStatus::Published && $this->published_at !== null && ! $this->published_at->isFuture();
    }

    /** @return BelongsTo<Media, $this> */
    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    /** @return BelongsTo<BlogCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsToMany<BlogTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    /** @return HasMany<BlogComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }
}
