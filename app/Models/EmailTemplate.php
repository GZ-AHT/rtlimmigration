<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Reusable subject and body with {{ placeholders }} (REQ §3 "Email
 * Templates", §19.1 "Email Templates managed by Laravel").
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $category
 * @property string $subject
 * @property string $body
 * @property bool $is_active
 * @property int|null $created_by
 */
#[Fillable(['name', 'slug', 'category', 'subject', 'body', 'is_active', 'created_by'])]
class EmailTemplate extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
